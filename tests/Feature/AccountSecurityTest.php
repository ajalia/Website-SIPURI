<?php
namespace Tests\Feature;
use App\Enums\Role;
use App\Models\{User,Rt,House,HouseResidency,Invoice,PaymentSubmission};
use App\Services\{CredentialService,DuesService};
use App\Support\PasswordPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Hash,Artisan};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountSecurityTest extends TestCase {
 use RefreshDatabase;
 private function rt(int $n=1): Rt {return Rt::firstOrCreate(['number'=>$n],['is_active'=>true]);}
 private function actor(Role $role=Role::Rt,int $rt=1): User {return User::factory()->create(['role'=>$role,'rt_id'=>$role===Role::Rt?$this->rt($rt)->id:null,'registration_status'=>'approved']);}
 private function resident(int $rt=1,array $extra=[]): User {return User::factory()->create(array_merge(['rt_id'=>$this->rt($rt)->id,'block'=>'A2','house_number'=>'2'],$extra));}
 private function changePayload(array $extra=[]): array {return array_merge(['current_password'=>'password123','password'=>'NewPassword123','password_confirmation'=>'NewPassword123'],$extra);}
 private function token(User $actor,User $target): string {
  $this->actingAs($actor)->get(route('account.reset.confirm',$target))->assertOk();return session('reset_challenge.'.$target->id)['token'];
 }
 public static function weakPasswords(): array {return [['lower123'],['UPPER123'],['OnlyLetters'],['Ab1'],[str_repeat('Aa1',43)],['12345678']];}
 #[DataProvider('weakPasswords')]
 public function test_password_policy_is_consistent_on_registration_initial_and_self_change(string $password): void {
  $this->post('/daftar',['name'=>'Pendaftar Uji','username'=>'new.account','rt_id'=>$this->rt()->id,'block'=>'A2','house_number'=>'2','password'=>$password,'password_confirmation'=>$password])->assertSessionHasErrors('password');
  $this->assertDatabaseCount('users',0);
  $u=$this->actor(Role::Treasurer);$u->must_change_password=true;$u->save();
  $this->actingAs($u)->post('/kata-sandi-awal',$this->changePayload(['password'=>$password,'password_confirmation'=>$password]))->assertSessionHasErrors('password');
  $u->must_change_password=false;$u->save();
  $this->put(route('account.password'),$this->changePayload(['password'=>$password,'password_confirmation'=>$password]))->assertSessionHasErrors('password');
  $this->assertTrue(Hash::check('password123',$u->fresh()->password));
 }
 public function test_valid_128_character_password_and_symbols_optional(): void {
  $password=str_repeat('Aa1',42).'Aa';
  $this->post('/daftar',['name'=>'Pendaftar Uji','username'=>'long.account','rt_id'=>$this->rt()->id,'block'=>'A2','house_number'=>'2','password'=>$password,'password_confirmation'=>$password])->assertSessionHasNoErrors();
  $u=User::where('username','long.account')->firstOrFail();$this->assertTrue(Hash::check($password,$u->password));
  $this->get('/pendaftaran/status')->assertOk();
 }
 public function test_legacy_password_can_login_and_public_help_cannot_reset_by_username(): void {
  $u=$this->actor(Role::Treasurer);
  $this->post('/masuk',['username'=>$u->username,'password'=>'password123'])->assertRedirect(route('dashboard'));$this->assertAuthenticatedAs($u);
  $this->get(route('account.settings'))->assertOk()->assertSee(PasswordPolicy::HELP);
  $before=$u->password;$this->post('/keluar');
  $this->get('/masuk')->assertSee(route('password.help'),false);
  $this->get('/lupa-kata-sandi')->assertOk()->assertSee('Hubungi pengurus RT')->assertSee('Hubungi Admin')->assertDontSee('name="username"',false);
  $this->post('/lupa-kata-sandi',['username'=>$u->username])->assertStatus(405);
  $this->assertTrue($before===$u->fresh()->password);
 }
 public function test_wrong_current_confirmation_same_password_and_fields_are_not_refilled(): void {
  $u=$this->actor(Role::Treasurer);$this->actingAs($u);
  $this->from(route('account.settings'))->put(route('account.password'),$this->changePayload(['current_password'=>'incorrect']))->assertSessionHasErrors('current_password');
  $this->put(route('account.password'),$this->changePayload(['password_confirmation'=>'Different123']))->assertSessionHasErrors('password');
  $this->get(route('account.settings'))->assertOk()->assertDontSee('value="NewPassword123"',false)->assertDontSee('value="password123"',false);
  $u->password='Existing123';$u->save();
  $this->put(route('account.password'),['current_password'=>'Existing123','password'=>'Existing123','password_confirmation'=>'Existing123'])->assertSessionHasErrors('password');
  $this->assertDatabaseCount('credential_events',0);
 }
 public function test_change_preserves_current_session_revokes_other_sessions_and_remember_token(): void {
  $u=$this->actor(Role::Treasurer);$other=$this->actor(Role::Admin);$before=$u->remember_token;
  foreach([['one',$u->id],['two',$u->id],['other',$other->id]] as [$id,$uid])DB::table('sessions')->insert(['id'=>$id,'user_id'=>$uid,'payload'=>'','last_activity'=>time()]);
  $this->actingAs($u)->get(route('account.settings'))->assertOk();$oldId=session()->getId();
  $this->put(route('account.password'),$this->changePayload())->assertRedirect(route('account.settings'))->assertSessionHas('success');
  $this->assertAuthenticatedAs($u);$this->assertTrue($oldId!==session()->getId());
  $this->assertTrue(Hash::check('NewPassword123',$u->fresh()->password));$this->assertTrue($before!==$u->fresh()->remember_token);
  $this->assertSame(0,DB::table('sessions')->where('user_id',$u->id)->count());$this->assertSame(1,DB::table('sessions')->where('user_id',$other->id)->count());
  $this->get(route('account.settings'))->assertOk();
  $this->withSession(['credentials'=>[$u->id=>0]])->get('/bendahara')->assertRedirect(route('login'));$this->assertGuest();
 }
 public function test_authorized_reset_displays_once_without_plaintext_persistence_and_preserves_status(): void {
  $actor=$this->actor();$u=$this->resident(extra:['registration_status'=>'rejected','is_active'=>false]);$old=$u->password;$remember=$u->remember_token;
  DB::table('sessions')->insert(['id'=>'target-session','user_id'=>$u->id,'payload'=>'','last_activity'=>time()]);
  $token=$this->token($actor,$u);
  $this->post(route('account.reset.store',$u),['token'=>$token])->assertSessionHasErrors('confirmed');$this->assertTrue($old===$u->fresh()->password);
  $r=$this->post(route('account.reset.store',$u),['token'=>$token,'confirmed'=>1])->assertOk()->assertHeader('Pragma','no-cache');
  $temporary=$r->viewData('temporary');$this->assertTrue((bool)preg_match('/\A(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9]).{8,128}\z/',$temporary));
  $this->assertTrue(str_contains($r->headers->get('Cache-Control'),'no-store'));
  $this->assertTrue(Hash::check($temporary,$u->fresh()->password));$this->assertTrue($u->fresh()->must_change_password);
  $this->assertFalse($u->fresh()->is_active);$this->assertSame('rejected',$u->fresh()->registration_status);$this->assertTrue($remember!==$u->fresh()->remember_token);
  $this->assertSame(0,DB::table('sessions')->where('user_id',$u->id)->count());
  $event=DB::table('credential_events')->first();$this->assertSame($actor->id,$event->actor_id);$this->assertSame($u->id,$event->target_id);
  $this->assertFalse(str_contains(json_encode($event),$temporary));$this->assertFalse(str_contains(serialize(session()->all()),$temporary));
  $this->post(route('account.reset.store',$u),['token'=>$token,'confirmed'=>1])->assertSessionHasErrors('confirmed');$this->assertDatabaseCount('credential_events',1);
  $this->get(route('account.reset.confirm',$u))->assertDontSee($temporary,false);
 }
 public function test_rt_and_admin_permissions_are_checked_against_manipulated_ids(): void {
  $rt=$this->actor();$own=$this->resident(extra:['registration_status'=>'approved']);$foreign=$this->resident(2);$otherRt=$this->actor(Role::Rt,2);$admin=$this->actor(Role::Admin);$treasurer=$this->actor(Role::Treasurer);
  foreach([$foreign,$otherRt,$admin,$treasurer] as $target){$this->actingAs($rt)->get(route('account.reset.confirm',$target))->assertForbidden();$this->post(route('account.reset.store',$target),['confirmed'=>1,'token'=>str_repeat('a',40)])->assertForbidden();}
  $this->actingAs($own)->get(route('account.reset.confirm',$own))->assertForbidden();$this->actingAs($treasurer)->get(route('account.reset.confirm',$own))->assertForbidden();
  $this->actingAs($admin)->get(route('admin.accounts'))->assertOk()->assertDontSee($own->username)->assertSee($treasurer->username)->assertDontSee('password123');
  $this->get(route('admin.resident-recovery'))->assertOk()->assertSee($own->username)->assertDontSee($treasurer->username);
  foreach([$own,$rt,$treasurer] as $target){$token=$this->token($admin,$target);$this->post(route('account.reset.store',$target),['confirmed'=>1,'token'=>$token])->assertOk();}
  $this->get(route('account.reset.confirm',$admin))->assertForbidden();$this->assertDatabaseCount('credential_events',3);
 }
 public function test_temporary_password_forces_change_then_pending_and_rejected_still_need_rt_approval(): void {
  foreach(['pending','rejected'] as $status){
   $actor=$this->actor();$u=$this->resident(extra:['registration_status'=>$status]);$token=$this->token($actor,$u);
   $r=$this->post(route('account.reset.store',$u),['token'=>$token,'confirmed'=>1])->assertOk();$temporary=$r->viewData('temporary');
   $this->post('/keluar');$this->post('/masuk',['username'=>$u->username,'password'=>$temporary])->assertRedirect(route('dashboard'));
   $this->get('/warga')->assertRedirect(route('password.initial'));
   $this->post('/kata-sandi-awal',['current_password'=>$temporary,'password'=>'MyPersonal123','password_confirmation'=>'MyPersonal123'])->assertRedirect(route('dashboard'));
   $this->get('/dashboard')->assertRedirect(route('registration.status'));$this->get(route('account.settings'))->assertOk()->assertSee('Untuk memperbaiki alamat, hubungi pengurus RT Anda.');
   $this->assertSame($status,$u->fresh()->registration_status);$this->assertFalse($u->fresh()->must_change_password);
   $this->post('/keluar');
  }
 }
 public function test_local_admin_recovery_has_no_web_endpoint_and_noninteractive_command_is_rejected(): void {
  $u=$this->actor(Role::Admin);$old=$u->password;
  $this->assertSame(1,Artisan::call('sipuri:recover-admin',['--no-interaction'=>true]));$this->assertTrue($old===$u->fresh()->password);
  $temporary=app(CredentialService::class)->recoverAdminLocally($u);
  $this->assertTrue(Hash::check($temporary,$u->fresh()->password));$this->assertSame('local_admin_recovery',DB::table('credential_events')->value('kind'));
 }
 public function test_all_roles_have_account_settings_and_pending_resident_can_read_only_address(): void {
  foreach([Role::Admin,Role::Rt,Role::Treasurer,Role::Resident] as $role){
   $u=$role===Role::Resident?$this->resident(extra:['registration_status'=>'approved']):$this->actor($role);
   $this->actingAs($u)->get(route('account.settings'))->assertOk()->assertSee('Pengaturan Akun')->assertSee('data-bs-toggle="dropdown"',false)->assertSee('name="current_password"',false)->assertSee(PasswordPolicy::HELP);
  }
  $pending=$this->resident();$this->actingAs($pending)->get(route('account.settings'))->assertOk()->assertSee('readonly',false)->assertSee('Untuk memperbaiki alamat, hubungi pengurus RT Anda.');
  $this->get('/warga')->assertRedirect(route('registration.status'));
 }
 public function test_expired_challenge_and_changed_rt_permission_cannot_reset_target(): void {
  $actor=$this->actor();$u=$this->resident();$before=$u->password;$token=$this->token($actor,$u);
  $this->travel(11)->minutes();$this->post(route('account.reset.store',$u),['confirmed'=>1,'token'=>$token])->assertSessionHasErrors('confirmed');
  $this->assertTrue($before===$u->fresh()->password);$this->travelBack();
  $token=$this->token($actor,$u);$u->rt_id=$this->rt(2)->id;$u->save();
  $this->post(route('account.reset.store',$u),['confirmed'=>1,'token'=>$token])->assertForbidden();
  $this->assertTrue($before===$u->fresh()->password);$this->assertDatabaseCount('credential_events',0);
 }
 public function test_reset_invalidates_an_old_session_version_without_reactivating_target(): void {
  $actor=$this->actor();$u=$this->resident(extra:['registration_status'=>'approved']);$token=$this->token($actor,$u);
  $this->post(route('account.reset.store',$u),['confirmed'=>1,'token'=>$token])->assertOk();
  $this->actingAs($u->fresh())->withSession(['credentials'=>[$u->id=>0]])->get('/warga')->assertRedirect(route('login'));$this->assertGuest();
 }
 public function test_address_correction_keeps_account_house_identity_and_old_invoice_snapshots(): void {
  $actor=$this->actor();$u=$this->resident(extra:['registration_status'=>'approved']);$h=House::create(['rt_id'=>$u->rt_id,'current_user_id'=>$u->id,'block'=>'A2','house_number'=>'2']);$r=HouseResidency::create(['house_id'=>$h->id,'user_id'=>$u->id,'resident_name'=>$u->name,'block'=>'A2','house_number'=>'2','started_at'=>now()->subDay()]);
  $treasurer=$this->actor(Role::Treasurer);$period=app(DuesService::class)->issue(['month'=>1,'year'=>2026,'amount'=>125000,'due_date'=>'2026-01-10'],$treasurer->id);$invoice=$period->invoices()->first();$snapshot=$invoice->getAttributes();
  $payment=PaymentSubmission::create(['invoice_id'=>$invoice->id,'user_id'=>$u->id,'paid_on'=>'2026-01-15','amount'=>125000,'proof_path'=>'test-only-proof.png','mime'=>'image/png','file_size'=>100,'bank_name'=>'Bank Uji','account_number'=>'001234','account_holder'=>'Pengurus Uji','status'=>'pending','pending_invoice_id'=>$invoice->id]);$paymentSnapshot=$payment->fresh()->getAttributes();
  $this->actingAs($actor)->put(route('rt.houses.update',$r),['name'=>'Nama Terkoreksi','block'=>'f012','house_number'=>'No. 002','change_reason'=>'Koreksi salah ketik sesuai catatan RT.'])->assertSessionHasNoErrors();
  $this->assertSame($h->id,$r->fresh()->house_id);$this->assertSame('F12',$h->fresh()->block);$this->assertSame('F12',$u->fresh()->block);$this->assertSame('2',$r->fresh()->house_number);$this->assertSame($snapshot,$invoice->fresh()->getAttributes());$this->assertSame($paymentSnapshot,$payment->fresh()->getAttributes());
  $this->assertDatabaseCount('residency_changes',1);
  $foreign=$this->resident(2,['block'=>'D4','house_number'=>'4']);
  $this->put(route('rt.houses.update',$r),['name'=>$u->name,'block'=>'D4','house_number'=>'04','change_reason'=>'Uji benturan'])->assertSessionHasErrors(['block'],null,'edit');
  $this->assertSame('F12',$r->fresh()->block);$this->assertSame($snapshot,$invoice->fresh()->getAttributes());$this->assertSame($paymentSnapshot,$payment->fresh()->getAttributes());
 }
}
