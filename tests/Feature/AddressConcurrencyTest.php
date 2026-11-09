<?php
namespace Tests\Feature;

use App\Models\{Rt, User};
use App\Services\AddressGuard;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AddressConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function beforeRefreshingDatabase(): void
    {
        if (DB::connection()->getDriverName()==='mariadb' && !str_starts_with(DB::connection()->getDatabaseName(),'sipuri_test_')) {
            throw new \RuntimeException('Concurrency test requires an isolated sipuri_test_ database.');
        }
    }

    public function test_competing_transactions_wait_for_database_mutex_then_recheck_committed_address(): void
    {
        if (DB::connection()->getDriverName()!=='mariadb') $this->markTestSkipped('Real InnoDB concurrency is verified with MariaDB test configuration.');
        $rt=Rt::create(['number'=>1,'is_active'=>true]);
        $file=tempnam(sys_get_temp_dir(),'sipuri-race-');
        $resultFile=tempnam(sys_get_temp_dir(),'sipuri-race-result-');
        $script= <<<'PHP'
<?php
$input=json_decode(trim(fgets(STDIN)),true,512,JSON_THROW_ON_ERROR);
require $input['root'].'/vendor/autoload.php';
$app=require $input['root'].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app['config']->set('database.connections.race_worker',$input['connection']);
Illuminate\Support\Facades\DB::setDefaultConnection('race_worker');
Illuminate\Support\Facades\DB::statement('SET innodb_lock_wait_timeout=5');
fwrite(STDOUT,"attempt\n");fflush(STDOUT);
try {
    App\Services\AddressGuard::transaction(function()use($input){
        App\Services\AddressGuard::assertAvailable('A2','2');
        App\Models\User::create(['name'=>'Second Request','username'=>'race.second','password'=>'test-password','rt_id'=>$input['rt'],'block'=>'A2','house_number'=>'2']);
    });
    file_put_contents($input['result'],"unexpected-success"); fwrite(STDOUT,"unexpected-success\n");
} catch(Illuminate\Validation\ValidationException $e) {
    $result=$e->errors()['block'][0]===App\Support\HouseAddress::CONFLICT?"conflict":"wrong-error"; file_put_contents($input['result'],$result); fwrite(STDOUT,$result."\n");
} catch(Throwable $e) { file_put_contents($input['result'],"error"); fwrite(STDOUT,'error:'.get_class($e).':'.$e->getCode()."\n"); }
PHP;
        file_put_contents($file,$script);
        $process=null;$pipes=[];
        try {
            DB::beginTransaction();
            AddressGuard::transaction(function()use($rt){
                AddressGuard::assertAvailable('A2','2');
                User::create(['name'=>'First Request','username'=>'race.first','password'=>'test-password','rt_id'=>$rt->id,'block'=>'A2','house_number'=>'2']);
            });
            $process=proc_open([PHP_BINARY,$file],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,base_path());
            $this->assertIsResource($process);
            fwrite($pipes[0],json_encode(['root'=>base_path(),'connection'=>DB::connection()->getConfig(),'rt'=>$rt->id,'result'=>$resultFile],JSON_THROW_ON_ERROR)."\n");
            fclose($pipes[0]);
            stream_set_timeout($pipes[1],8);
            $this->assertSame("attempt\n",fgets($pipes[1]));
            // Windows process pipes are not reliably selectable. The worker marks completion separately.
            usleep(150000);
            $this->assertSame('',file_get_contents($resultFile),'Competing transaction must not finish while the first owns the mutex.');
            DB::commit();
            $this->assertSame("conflict\n",fgets($pipes[1]));
            fclose($pipes[1]);fclose($pipes[2]);proc_close($process);$process=null;
            $this->assertSame(1,User::where('role','warga')->count());
            $this->assertSame('race.first',User::where('role','warga')->value('username'));
        } finally {
            if(DB::transactionLevel()>0) DB::rollBack();
            if(is_resource($process)){proc_terminate($process);proc_close($process);}
            if(is_file($file))unlink($file); if(is_file($resultFile))unlink($resultFile);
        }
    }
}
