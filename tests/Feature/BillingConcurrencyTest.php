<?php
namespace Tests\Feature;
use App\Enums\Role;
use App\Models\{User,Rt,House,HouseResidency,Invoice,PaymentSubmission};
use App\Services\DuesService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BillingConcurrencyTest extends TestCase {
 use DatabaseMigrations;
 protected function beforeRefreshingDatabase(): void {
  if(DB::connection()->getDriverName()==='mariadb'&&!str_starts_with(DB::connection()->getDatabaseName(),'sipuri_test_'))throw new \RuntimeException('Requires isolated sipuri_test_ database.');
 }
 private function fixture(): User {
  $rt=Rt::create(['number'=>1,'is_active'=>true]);$u=User::factory()->create(['role'=>Role::Resident,'registration_status'=>'approved','rt_id'=>$rt->id,'block'=>'A2','house_number'=>'2']);
  $h=House::create(['rt_id'=>$rt->id,'block'=>'A2','house_number'=>'2','current_user_id'=>$u->id]);
  HouseResidency::create(['house_id'=>$h->id,'user_id'=>$u->id,'resident_name'=>$u->name,'block'=>'A2','house_number'=>'2','started_at'=>now()]);
  return User::factory()->create(['role'=>Role::Treasurer,'registration_status'=>'approved']);
 }
 private function race(array $payload,callable $first,string $expected): void {
  if(DB::connection()->getDriverName()!=='mariadb')$this->markTestSkipped('InnoDB race requires MariaDB test configuration.');
  $file=tempnam(sys_get_temp_dir(),'sipuri-billing-race-');$result=tempnam(sys_get_temp_dir(),'sipuri-billing-result-');$process=null;
  $script= <<<'PHP'
<?php
$i=json_decode(trim(fgets(STDIN)),true,512,JSON_THROW_ON_ERROR);
require $i['root'].'/vendor/autoload.php';$app=require $i['root'].'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app['config']->set('database.connections.race_worker',$i['connection']);Illuminate\Support\Facades\DB::setDefaultConnection('race_worker');Illuminate\Support\Facades\DB::statement('SET innodb_lock_wait_timeout=5');
fwrite(STDOUT,"attempt\n");fflush(STDOUT);
try {
 $service=app(App\Services\DuesService::class);
 if($i['mode']==='issue')$service->issue(['month'=>1,'year'=>2026,'amount'=>125000,'due_date'=>'2026-01-10'],$i['actor']);
 else $service->decide(App\Models\PaymentSubmission::findOrFail($i['payment']),'accepted',$i['actor'],null);
 $result='unexpected-success';
} catch(Illuminate\Validation\ValidationException $e){$result='blocked:'.array_key_first($e->errors());}
catch(Throwable $e){$result='error:'.get_class($e).':'.$e->getCode();}
file_put_contents($i['result'],$result);fwrite(STDOUT,$result."\n");
PHP;
  file_put_contents($file,$script);
  try {
   DB::beginTransaction();$first();
   $process=proc_open([PHP_BINARY,$file],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,base_path());$this->assertIsResource($process);
   fwrite($pipes[0],json_encode($payload+['root'=>base_path(),'connection'=>DB::connection()->getConfig(),'result'=>$result],JSON_THROW_ON_ERROR)."\n");fclose($pipes[0]);stream_set_timeout($pipes[1],8);
   $this->assertSame("attempt\n",fgets($pipes[1]));usleep(150000);
   $this->assertSame('',file_get_contents($result),'Second transaction must wait for the row lock.');
   DB::commit();$this->assertSame($expected."\n",fgets($pipes[1]));
   fclose($pipes[1]);fclose($pipes[2]);proc_close($process);$process=null;
  } finally {if(DB::transactionLevel()>0)DB::rollBack();if(is_resource($process)){proc_terminate($process);proc_close($process);}if(is_file($file))unlink($file);if(is_file($result))unlink($result);}
 }
 public function test_simultaneous_period_issuance_creates_one_period_and_one_bill_per_house(): void {
  $t=$this->fixture();$service=app(DuesService::class);
  $this->race(['mode'=>'issue','actor'=>$t->id],fn()=>$service->issue(['month'=>1,'year'=>2026,'amount'=>125000,'due_date'=>'2026-01-10'],$t->id),'blocked:month');
  $this->assertDatabaseCount('dues_periods',1);$this->assertDatabaseCount('invoices',1);$this->assertDatabaseCount('dues_income_entries',0);
 }
 public function test_simultaneous_acceptance_cannot_record_income_twice(): void {
  $t=$this->fixture();$service=app(DuesService::class);$period=$service->issue(['month'=>1,'year'=>2026,'amount'=>125000,'due_date'=>'2026-01-10'],$t->id);$i=$period->invoices()->first();
  $p=PaymentSubmission::create(['invoice_id'=>$i->id,'user_id'=>$i->user_id,'paid_on'=>'2026-01-15','amount'=>125000,'proof_path'=>'test-only.png','mime'=>'image/png','file_size'=>100,'bank_name'=>'Bank Uji','account_number'=>'001','account_holder'=>'Pengurus Uji','status'=>'pending','pending_invoice_id'=>$i->id]);
  $this->race(['mode'=>'accept','actor'=>$t->id,'payment'=>$p->id],fn()=>$service->decide($p,'accepted',$t->id,null),'blocked:decision');
  $this->assertSame('paid',$i->fresh()->status);$this->assertSame('accepted',$p->fresh()->status);$this->assertDatabaseCount('dues_income_entries',1);
  $this->assertSame(125000,(int)DB::table('dues_income_entries')->sum('amount'));
 }
}
