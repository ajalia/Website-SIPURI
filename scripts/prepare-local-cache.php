<?php
// Bantuan untuk lingkungan Windows yang melaporkan is_writable=false walau penulisan tersedia.
// Hanya membangun ulang manifest cache dari paket terpasang, tanpa mengubah vendor.
require __DIR__.'/../vendor/autoload.php';
$root=dirname(__DIR__);
$installed=json_decode(file_get_contents($root.'/vendor/composer/installed.json'),true,512,JSON_THROW_ON_ERROR);
$packages=[];
foreach($installed['packages']as$package)if(isset($package['extra']['laravel']))$packages[$package['name']]=$package['extra']['laravel'];
file_put_contents($root.'/bootstrap/cache/packages.php','<?php return '.var_export($packages,true).';');
$app=require $root.'/bootstrap/app.php';
(new Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables)->bootstrap($app);
(new Illuminate\Foundation\Bootstrap\LoadConfiguration)->bootstrap($app);
$reflection=new ReflectionClass(Illuminate\Foundation\Bootstrap\RegisterProviders::class);
$merge=$reflection->getStaticProperties()['merge'];
$all=array_merge(Illuminate\Support\ServiceProvider::defaultProviders()->toArray(),$merge,require $root.'/bootstrap/providers.php');
$providers=collect($all)->partition(fn($p)=>str_starts_with($p,'Illuminate\\'));
$providers->splice(1,0,[$app->make(Illuminate\Foundation\PackageManifest::class)->providers()]);
$manifest=['providers'=>$providers->collapse()->toArray(),'eager'=>[],'deferred'=>[],'when'=>[]];
foreach($manifest['providers']as$class){$provider=new $class($app);if($provider->isDeferred()){foreach($provider->provides()as$service)$manifest['deferred'][$service]=$class;$manifest['when'][$class]=$provider->when();}else $manifest['eager'][]=$class;}
file_put_contents($root.'/bootstrap/cache/services.php','<?php return '.var_export($manifest,true).';');
echo "Manifest lokal siap.\n";
