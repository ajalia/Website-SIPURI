<?php
namespace Tests\Feature;
use App\Enums\Role;
use App\Models\{User,FinancialReport};
use App\Services\CashBookService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use Tests\TestCase;
class CashReportConcurrencyTest extends TestCase {
 use DatabaseMigrations;
 protected function beforeRefreshingDatabase(): void {if(!str_starts_with(DB::connection()->getDatabaseName(),'sipuri_test_'))throw new \RuntimeException('Isolated MariaDB test database required.');}
 private function fixture(): array {
  Storage::fake('cash_proofs');$actor=User::factory()->create(['role'=>Role::Admin,'registration_status'=>'approved']);$service=app(CashBookService::class);$service->configure(['starts_on'=>'2026-01-01','opening_balance'=>0],$actor->id);
  $id=DB::table('cash_transactions')->insertGetId(['request_key'=>(string)Str::uuid(),'kind'=>'income','transaction_date'=>'2026-01-02','description'=>'Internal test','amount'=>10000,'recorded_by'=>$actor->id,'created_at'=>now()]);
  Storage::disk('cash_proofs')->put('fixture.pdf',"%PDF-1.4\nfixture");DB::table('cash_proofs')->insert(['source_kind'=>'manual','source_id'=>$id,'path'=>'fixture.pdf','mime'=>'application/pdf','file_size'=>16,'uploaded_by'=>$actor->id,'created_at'=>now()]);
  $s=$service->locked(fn($book)=>$service->statement(2026,1,$book));$preview=['year'=>2026,'month'=>1,'fingerprint'=>$s['fingerprint'],'rows'=>$service->publicRows($s,[$id=>'Penerimaan lingkungan'])];
  return [$actor,$preview,(string)Str::uuid()];
 }
 private function race(User $actor,array $preview,string $token,callable $first): void {
  $file=tempnam(sys_get_temp_dir(),'sipuri-cash-race-');$result=tempnam(sys_get_temp_dir(),'sipuri-cash-result-');$process=null;
  $script= <<<'PHP'
<?php
$i=json_decode(trim(fgets(STDIN)),true,512,JSON_THROW_ON_ERROR);require $i['root'].'/vendor/autoload.php';$app=require $i['root'].'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app['config']->set('database.connections.cash_race',$i['connection']);Illuminate\Support\Facades\DB::setDefaultConnection('cash_race');$app['config']->set('filesystems.disks.cash_proofs',$i['disk']);Illuminate\Support\Facades\DB::statement('SET innodb_lock_wait_timeout=8');
fwrite(STDOUT,"attempt\n");fflush(STDOUT);
try {app(App\Services\CashBookService::class)->publish($i['preview'],$i['token'],$i['actor']);$result='unexpected-success';}
catch(Illuminate\Validation\ValidationException $e){$result='blocked:'.array_key_first($e->errors());}catch(Throwable $e){$result='error:'.get_class($e).':'.$e->getCode();}
file_put_contents($i['result'],$result);fwrite(STDOUT,$result."\n");
PHP;
  file_put_contents($file,$script);
  try {
   DB::beginTransaction();$first();
   $process=proc_open([PHP_BINARY,$file],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,base_path());$this->assertIsResource($process);
   fwrite($pipes[0],json_encode(['root'=>base_path(),'connection'=>DB::connection()->getConfig(),'disk'=>['driver'=>'local','root'=>Storage::disk('cash_proofs')->path(''),'throw'=>true],'preview'=>$preview,'token'=>$token,'actor'=>$actor->id,'result'=>$result],JSON_THROW_ON_ERROR)."\n");fclose($pipes[0]);stream_set_timeout($pipes[1],10);
   $this->assertSame("attempt\n",fgets($pipes[1]));usleep(150000);$this->assertSame('',file_get_contents($result),'Second connection must wait for the cash-book row lock.');DB::commit();
   $this->assertSame("blocked:publication\n",fgets($pipes[1]));fclose($pipes[1]);fclose($pipes[2]);proc_close($process);$process=null;
  }finally{if(DB::transactionLevel()>0)DB::rollBack();if(is_resource($process)){proc_terminate($process);proc_close($process);}if(is_file($file))unlink($file);if(is_file($result))unlink($result);}
 }
 public function test_two_connections_publishing_same_request_create_only_one_version(): void {
  [$a,$preview,$token]=$this->fixture();$this->race($a,$preview,$token,fn()=>app(CashBookService::class)->publish($preview,$token,$a->id));$this->assertDatabaseCount('financial_reports',1);$this->assertSame(1,FinancialReport::first()->version);
 }
 public function test_publication_waits_for_cash_writer_and_refuses_old_preview_after_commit(): void {
  [$a,$preview,$token]=$this->fixture();$this->race($a,$preview,$token,function()use($a){app(CashBookService::class)->locked(function()use($a){DB::table('cash_transactions')->insert(['request_key'=>(string)Str::uuid(),'kind'=>'expense','transaction_date'=>'2026-01-03','description'=>'New source','amount'=>3000,'recorded_by'=>$a->id,'created_at'=>now()]);});});$this->assertDatabaseCount('financial_reports',0);
 }
}
