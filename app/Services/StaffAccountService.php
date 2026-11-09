<?php
namespace App\Services;
use App\Enums\Role;
use App\Models\{User,Rt};
use App\Support\PasswordPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StaffAccountService {
 public function locked(User $actor,callable $callback): mixed {
  return DB::transaction(function()use($actor,$callback){
   DB::table('staff_account_locks')->where('id',1)->lockForUpdate()->firstOrFail();
   $admin=User::whereKey($actor->id)->lockForUpdate()->firstOrFail();abort_unless($admin->role===Role::Admin&&$admin->is_active&&!$admin->must_change_password,403);
   return $callback($admin);
  },3);
 }
 public function assertStaff(User $target): void {abort_unless(in_array($target->role,[Role::Rt,Role::Treasurer],true),403);}
 public function state(User $user): array {return ['name'=>$user->name,'username'=>$user->username,'role'=>$user->role->value,'rt_id'=>$user->rt_id,'is_active'=>$user->is_active];}
 public function fingerprint(User $user): string {return hash('sha256',json_encode([$this->state($user),(int)$user->credentials_version],JSON_THROW_ON_ERROR));}
 private function event(User $actor,User $target,string $kind,string $nonce,?array $before): void {
  DB::table('staff_account_events')->insert(['actor_id'=>$actor->id,'target_id'=>$target->id,'kind'=>$kind,'request_hash'=>hash('sha256',$nonce),'before_state'=>$before===null?null:json_encode($before,JSON_THROW_ON_ERROR),'after_state'=>json_encode($this->state($target),JSON_THROW_ON_ERROR),'created_at'=>now()]);
 }
 private function previous(User $actor,string $nonce,string $kind,?int $target=null): ?object {
  $event=DB::table('staff_account_events')->where('request_hash',hash('sha256',$nonce))->lockForUpdate()->first();
  if($event)abort_unless((int)$event->actor_id===$actor->id&&$event->kind===$kind&&($target===null||(int)$event->target_id===$target),403);
  return $event;
 }
 private function vacancy(Role $role,?int $rt,int $except=0): void {
  if($role===Role::Rt){
   if(!$rt||!Rt::whereKey($rt)->where('is_active',true)->sharedLock()->first())throw ValidationException::withMessages(['rt_id'=>'Pilih RT aktif yang tersedia di database.']);
   $exists=User::where('role','rt')->where('rt_id',$rt)->where('is_active',true)->where('id','!=',$except)->exists();
   if($exists)throw ValidationException::withMessages(['staff_conflict'=>'RT ini sudah memiliki Pengurus RT aktif. Nonaktifkan akun petugas lama terlebih dahulu setelah konfirmasi pergantian tugas.']);
  }else{
   if($rt!==null)throw ValidationException::withMessages(['rt_id'=>'Bendahara bertugas untuk RW 07 dan tidak memiliki wilayah RT.']);
   if(User::where('role','bendahara')->where('is_active',true)->where('id','!=',$except)->exists())throw ValidationException::withMessages(['staff_conflict'=>'RW 07 sudah memiliki Bendahara aktif. Nonaktifkan akun petugas lama terlebih dahulu setelah konfirmasi pergantian tugas.']);
  }
 }
 public function conflicts(): array {
  $problems=[];
  foreach(User::where('role','rt')->where('is_active',true)->select('rt_id')->selectRaw('COUNT(*) AS total')->groupBy('rt_id')->havingRaw('COUNT(*) > 1')->get() as $group){$rt=Rt::find($group->rt_id);$problems[]='Konflik data lama: '.($rt?->label()??'wilayah RT belum ditentukan').' memiliki '.$group->total.' akun Pengurus RT aktif.';}
  $treasurers=User::where('role','bendahara')->where('is_active',true)->count();if($treasurers>1)$problems[]='Konflik data lama: RW 07 memiliki '.$treasurers.' Bendahara aktif.';
  if(User::where('role','rt')->whereNull('rt_id')->exists())$problems[]='Ada akun Pengurus RT tanpa wilayah RT. Penetapan wilayah lama perlu pemeriksaan tersendiri; formulir ini tidak memindahkan wilayah.';
  if(User::where('role','bendahara')->whereNotNull('rt_id')->exists())$problems[]='Ada akun Bendahara dengan RT tersimpan. Data lama tidak diubah otomatis; periksa penetapan tugasnya.';
  return $problems;
 }
 public function create(User $actor,array $data,string $nonce): array {
  try{return $this->locked($actor,function($admin)use($data,$nonce){
   if($event=$this->previous($admin,$nonce,'created'))return ['user'=>User::findOrFail($event->target_id),'temporary'=>null,'replayed'=>true];
   $role=Role::tryFrom($data['role']);abort_unless(in_array($role,[Role::Rt,Role::Treasurer],true),403);
   $rt=$role===Role::Rt?(int)($data['rt_id']??0):null;
   if($role===Role::Treasurer&&!empty($data['rt_id']))throw ValidationException::withMessages(['rt_id'=>'Bendahara tidak memiliki wilayah RT.']);
   $this->vacancy($role,$rt);
   if(User::where('username',$data['username'])->exists())throw ValidationException::withMessages(['username'=>'Username sudah digunakan oleh akun SIPURI lain, termasuk akun nonaktif. Gunakan username baru.']);
   $temporary=PasswordPolicy::temporary();$user=new User(['name'=>$data['name'],'username'=>$data['username'],'password'=>$temporary,'rt_id'=>$rt]);
   $user->role=$role;$user->is_active=true;$user->registration_status='approved';$user->must_change_password=true;$user->save();$this->event($admin,$user,'created',$nonce,null);
   return ['user'=>$user,'temporary'=>$temporary,'replayed'=>false];
  });}catch(QueryException $e){if(($e->errorInfo[1]??null)===1062)throw ValidationException::withMessages(['username'=>'Username sudah digunakan. Gunakan username baru.']);throw $e;}
 }
 public function rename(User $actor,User $target,string $name,string $nonce,string $expected): bool {
  return $this->locked($actor,function($admin)use($target,$name,$nonce,$expected){
   $user=User::whereKey($target->id)->lockForUpdate()->firstOrFail();$this->assertStaff($user);
   if($this->previous($admin,$nonce,'renamed',$user->id))return false;
   if(!hash_equals($expected,$this->fingerprint($user)))throw ValidationException::withMessages(['name'=>'Data akun berubah sejak formulir dibuka. Muat ulang sebelum menyimpan.']);
   $before=$this->state($user);$user->name=$name;$user->save();$this->event($admin,$user,'renamed',$nonce,$before);return true;
  });
 }
 public function status(User $actor,User $target,bool $active,string $nonce,string $expected): bool {
  return $this->locked($actor,function($admin)use($target,$active,$nonce,$expected){
   $user=User::whereKey($target->id)->lockForUpdate()->firstOrFail();$this->assertStaff($user);$kind=$active?'activated':'deactivated';
   if($this->previous($admin,$nonce,$kind,$user->id))return false;
   if(!hash_equals($expected,$this->fingerprint($user))||$user->is_active===$active)throw ValidationException::withMessages(['staff_conflict'=>'Status/data akun telah berubah. Buka ulang konfirmasi sebelum melanjutkan.']);
   if($active)$this->vacancy($user->role,$user->rt_id,$user->id);
   $before=$this->state($user);$user->is_active=$active;app(CredentialService::class)->revokeAccess($user);$this->event($admin,$user,$kind,$nonce,$before);return true;
  });
 }
}
