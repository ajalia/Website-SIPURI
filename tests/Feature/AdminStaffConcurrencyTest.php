<?php
namespace Tests\Feature;
use App\Enums\Role;
use App\Models\{User,Rt};
use App\Services\StaffAccountService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
class AdminStaffConcurrencyTest extends TestCase {
 use DatabaseMigrations;
 protected function beforeRefreshingDatabase(): void {if(!str_starts_with(DB::connection()->getDatabaseName(),'sipuri_test_'))throw new \RuntimeException('Isolated MariaDB test database required');}
 private function admin(): User {return User::factory()->create(['role'=>Role::Admin,'registration_status'=>'approved']);}
 private function data(int $rt,string $username='worker_user'): array {return ['name'=>'Pengurus uji konkurensi','username'=>$username,'role'=>'rt','rt_id'=>$rt];}
 private function race(array $payload,callable $first,string $expected): void {
  $file=tempnam(sys_get_temp_dir(),'sipuri-staff-race-');$result=tempnam(sys_get_temp_dir(),'sipuri-staff-result-');$process=null;
  $script= <<<'PHP'
<?php
$i=json_decode(trim(fgets(STDIN)),true,512,JSON_THROW_ON_ERROR);require $i['root'].'/vendor/autoload.php';$app=require $i['root'].'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app['config']->set('database.connections.staff_race',$i['connection']);Illuminate\Support\Facades\DB::setDefaultConnection('staff_race');Illuminate\Support\Facades\DB::statement('SET innodb_lock_wait_timeout=8');$actor=App\Models\User::findOrFail($i['actor']);$service=app(App\Services\StaffAccountService::class);
fwrite(STDOUT,"attempt\n");fflush(STDOUT);
try {
 if($i['mode']==='create'){$value=$service->create($actor,$i['data'],$i['nonce']);$result=$value['replayed']&&$value['temporary']===null?'replayed':'unexpected-success';}
 else {$service->status($actor,App\Models\User::findOrFail($i['target']),true,$i['nonce'],$i['fingerprint']);$result='unexpected-success';}
}catch(Illuminate\Validation\ValidationException $e){$result='blocked:'.array_key_first($e->errors());}catch(Throwable $e){$result='error:'.get_class($e).':'.$e->getCode();}
// No generated credential is written to this result file or stdout.
file_put_contents($i['result'],$result);fwrite(STDOUT,$result."\n");
PHP;
  file_put_contents($file,$script);
  try {
   DB::beginTransaction();$first();
   $process=proc_open([PHP_BINARY,$file],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,base_path());$this->assertIsResource($process);fwrite($pipes[0],json_encode($payload+['root'=>base_path(),'connection'=>DB::connection()->getConfig(),'result'=>$result],JSON_THROW_ON_ERROR)."\n");fclose($pipes[0]);stream_set_timeout($pipes[1],10);
   $this->assertSame("attempt\n",fgets($pipes[1]));usleep(150000);$this->assertSame('',file_get_contents($result),'Second connection must wait on staff-account mutex.');DB::commit();$this->assertSame($expected."\n",fgets($pipes[1]));fclose($pipes[1]);fclose($pipes[2]);proc_close($process);$process=null;
  }finally{if(DB::transactionLevel()>0)DB::rollBack();if(is_resource($process)){proc_terminate($process);proc_close($process);}if(is_file($file))unlink($file);if(is_file($result))unlink($result);}
 }
 public function test_two_rt_creations_for_same_rt_cannot_both_become_active(): void {
  $a=$this->admin();$rt=Rt::create(['number'=>1,'is_active'=>true]);$service=app(StaffAccountService::class);
  $this->race(['mode'=>'create','actor'=>$a->id,'data'=>$this->data($rt->id),'nonce'=>(string)Str::uuid()],fn()=>$service->create($a,$this->data($rt->id,'first_user'),(string)Str::uuid()),'blocked:staff_conflict');
  $this->assertSame(1,User::where('role','rt')->where('is_active',true)->count());$this->assertDatabaseCount('staff_account_events',1);
 }
 public function test_two_treasurer_creations_cannot_both_become_active(): void {
  $a=$this->admin();$service=app(StaffAccountService::class);$data=['name'=>'Treasurer test','username'=>'first_treasurer','role'=>'bendahara'];
  $this->race(['mode'=>'create','actor'=>$a->id,'data'=>array_merge($data,['username'=>'second_treasurer']),'nonce'=>(string)Str::uuid()],fn()=>$service->create($a,$data,(string)Str::uuid()),'blocked:staff_conflict');$this->assertSame(1,User::where('role','bendahara')->count());
 }
 public function test_same_username_across_different_rt_and_same_request_replay_are_not_duplicated(): void {
  $a=$this->admin();$rt=Rt::create(['number'=>1,'is_active'=>true]);$other=Rt::create(['number'=>2,'is_active'=>true]);$service=app(StaffAccountService::class);
  $this->race(['mode'=>'create','actor'=>$a->id,'data'=>$this->data($other->id,'unique_name'),'nonce'=>(string)Str::uuid()],fn()=>$service->create($a,$this->data($rt->id,'unique_name'),(string)Str::uuid()),'blocked:username');
  $nonce=(string)Str::uuid();$data=$this->data($other->id,'idempotent_name');$this->race(['mode'=>'create','actor'=>$a->id,'data'=>$data,'nonce'=>$nonce],fn()=>$service->create($a,$data,$nonce),'replayed');$this->assertSame(1,User::where('username','idempotent_name')->count());
 }
 public function test_simultaneous_activations_for_same_rt_refuse_the_second_account(): void {
  $a=$this->admin();$rt=Rt::create(['number'=>1,'is_active'=>true]);$first=User::factory()->create(['role'=>Role::Rt,'rt_id'=>$rt->id,'is_active'=>false]);$second=User::factory()->create(['role'=>Role::Rt,'rt_id'=>$rt->id,'is_active'=>false]);$service=app(StaffAccountService::class);
  $this->race(['mode'=>'activate','actor'=>$a->id,'target'=>$second->id,'fingerprint'=>$service->fingerprint($second),'nonce'=>(string)Str::uuid()],fn()=>$service->status($a,$first,true,(string)Str::uuid(),$service->fingerprint($first)),'blocked:staff_conflict');$this->assertTrue($first->fresh()->is_active);$this->assertFalse($second->fresh()->is_active);
 }
}
