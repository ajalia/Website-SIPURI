<?php
namespace App\Services;
use App\Enums\Role;
use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Support\Facades\{DB,Hash};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CredentialService {
 public function mayReset(User $actor,User $target): bool {
  return ($actor->role===Role::Admin&&in_array($target->role,[Role::Resident,Role::Rt,Role::Treasurer],true))
   ||($actor->role===Role::Rt&&$actor->rt_id!==null&&$target->role===Role::Resident&&$actor->rt_id===$target->rt_id);
 }
 private function revoke(User $user): void {
  DB::connection(config('session.connection'))->table(config('session.table','sessions'))->where('user_id',$user->id)->delete();
 }
 // Caller holds the target row lock and owns the surrounding transaction.
 public function revokeAccess(User $user): void {
  $user->remember_token=Str::random(60);$user->credentials_version++;$user->save();$this->revoke($user);
 }
 private function event(?int $actor,User $target,string $kind,string $nonce): void {
  DB::table('credential_events')->insert(['actor_id'=>$actor,'actor_label'=>$actor===null?'terminal-local:'.(getenv('USERNAME')?:'operator-local'):null,'target_id'=>$target->id,'kind'=>$kind,'nonce_hash'=>hash('sha256',$nonce),'created_at'=>now()]);
 }
 public function change(User $target,string $current,string $password): User {
  return DB::transaction(function()use($target,$current,$password){
   $user=User::whereKey($target->id)->lockForUpdate()->firstOrFail();
   if(!Hash::check($current,$user->password))throw ValidationException::withMessages(['current_password'=>'Kata sandi saat ini tidak sesuai.']);
   if(Hash::check($password,$user->password))throw ValidationException::withMessages(['password'=>'Gunakan kata sandi yang berbeda dari kata sandi sebelumnya.']);
   $user->password=$password;$user->must_change_password=false;$user->remember_token=Str::random(60);$user->credentials_version++;$user->save();
   $this->revoke($user);$this->event($user->id,$user,'self_change',Str::uuid()->toString());return $user;
  },3);
 }
 public function reset(User $actor,User $target,string $nonce): string {
  return DB::transaction(function()use($actor,$target,$nonce){
   $actor=User::whereKey($actor->id)->firstOrFail();$user=User::whereKey($target->id)->lockForUpdate()->firstOrFail();
   abort_unless($actor->is_active&&!$actor->must_change_password&&$this->mayReset($actor,$user),403);
   return $this->performReset($actor->id,$user,'officer_reset',$nonce);
  },3);
 }
 public function recoverAdminLocally(User $target): string {
  abort_unless(app()->runningInConsole(),403);
  return DB::transaction(function()use($target){$user=User::whereKey($target->id)->lockForUpdate()->firstOrFail();abort_unless($user->role===Role::Admin,403);return $this->performReset(null,$user,'local_admin_recovery',Str::uuid()->toString());},3);
 }
 private function performReset(?int $actor,User $user,string $kind,string $nonce): string {
  if(DB::table('credential_events')->where('nonce_hash',hash('sha256',$nonce))->lockForUpdate()->first(['id']))throw ValidationException::withMessages(['confirmed'=>'Permintaan sudah diproses. Buka kembali halaman konfirmasi bila diperlukan.']);
  $temporary=PasswordPolicy::temporary();$user->password=$temporary;$user->must_change_password=true;$user->remember_token=Str::random(60);$user->credentials_version++;$user->save();
  $this->revoke($user);$this->event($actor,$user,$kind,$nonce);return $temporary;
 }
}
