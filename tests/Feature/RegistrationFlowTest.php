<?php
namespace Tests\Feature;
use App\Enums\Role;
use App\Models\{Rt,User,House};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Hash};
use Tests\TestCase;
class RegistrationFlowTest extends TestCase {
 use RefreshDatabase;
 private function rt(int $number=1): Rt { return Rt::firstOrCreate(['number'=>$number],['is_active'=>true]); }
 private function officer(int $number=1): User {return User::factory()->create(['role'=>Role::Rt,'rt_id'=>$this->rt($number)->id,'registration_status'=>'approved']);}
 private function applicant(int $number=1,array $extra=[]): User {return User::factory()->create(array_merge(['rt_id'=>$this->rt($number)->id,'block'=>'B3','house_number'=>'14'],$extra));}
 private function payload(array $extra=[]): array {return array_merge(['name'=>'Budi Santoso','username'=>'budi.santoso','rt_id'=>$this->rt()->id,'block'=>'Blok b3','house_number'=>'No. 014','password'=>'Password123','password_confirmation'=>'Password123'],$extra);}
 public function test_public_forms_render_and_guest_cannot_open_portal(): void {
  $this->rt();$this->get('/')->assertOk();$this->get('/masuk')->assertOk()->assertSee('Masuk ke SIPURI');$this->get('/daftar')->assertOk()->assertSee('Pilih RT');$this->get('/rt')->assertRedirect(route('login'));
 }
 public function test_registration_is_pending_normalized_and_cannot_choose_admin_role(): void {
  $this->post('/daftar',$this->payload(['role'=>'admin','registration_status'=>'approved','is_active'=>true,'must_change_password'=>false]))->assertRedirect(route('registration.status'));
  $user=User::where('username','budi.santoso')->firstOrFail();$this->assertSame(Role::Resident,$user->role);$this->assertSame('pending',$user->registration_status);$this->assertSame('B3',$user->block);$this->assertSame('14',$user->house_number);$this->assertTrue(Hash::check('Password123',$user->password));$this->assertDatabaseCount('houses',0);
  $this->get('/pendaftaran/status')->assertOk()->assertSee('Sedang Ditinjau');$this->get('/warga')->assertRedirect(route('registration.status'));$this->get('/admin')->assertRedirect(route('registration.status'));
 }
 public function test_duplicate_username_and_inactive_rt_rejected(): void {
  $this->applicant(extra:['username'=>'budi.santoso']);$this->post('/daftar',$this->payload())->assertSessionHasErrors('username');
  $this->rt()->update(['is_active'=>false]);$this->post('/daftar',$this->payload(['username'=>'baru']))->assertSessionHasErrors('rt_id');
 }
 public function test_rt_can_approve_creating_house_without_preloaded_data(): void {
  $officer=$this->officer();$resident=$this->applicant();$this->actingAs($officer)->get(route('rt.registrations.show',$resident))->assertOk();
  $this->post(route('rt.registrations.decide',$resident),['decision'=>'approved'])->assertRedirect(route('rt.registrations.show',$resident));
  $this->assertDatabaseHas('users',['id'=>$resident->id,'registration_status'=>'approved']);$this->assertDatabaseHas('houses',['current_user_id'=>$resident->id,'block'=>'B3','house_number'=>'14']);$this->assertDatabaseHas('registration_reviews',['user_id'=>$resident->id,'reviewer_id'=>$officer->id,'decision'=>'approved']);
  $this->actingAs($resident->fresh())->get('/warga')->assertOk()->assertSee('B3');
 }
 public function test_rt_cannot_read_or_decide_registration_from_other_rt(): void {
  $officer=$this->officer(1);$resident=$this->applicant(2);$this->actingAs($officer)->get(route('rt.registrations.show',$resident))->assertForbidden();$this->post(route('rt.registrations.decide',$resident),['decision'=>'approved'])->assertForbidden();$this->assertDatabaseCount('houses',0);$this->get('/rt/pendaftaran')->assertDontSee($resident->name);
 }
 public function test_one_house_cannot_have_two_current_accounts_and_repeated_approval_does_not_duplicate(): void {
  $officer=$this->officer();$first=$this->applicant();$second=$this->applicant();
  $this->actingAs($officer)->post(route('rt.registrations.decide',$first),['decision'=>'approved'])->assertSessionHasErrors('block');
  $this->assertDatabaseCount('houses',0);$this->assertDatabaseCount('registration_reviews',0);
  $this->post(route('rt.registrations.decide',$second),['decision'=>'rejected','reason'=>'Pendaftaran ganda; perlu konfirmasi alamat.'])->assertSessionHasNoErrors();
  $this->post(route('rt.registrations.decide',$first),['decision'=>'approved'])->assertSessionHasNoErrors();
  $this->post(route('rt.registrations.decide',$first),['decision'=>'approved'])->assertSessionHasErrors('decision');
  $this->assertDatabaseCount('houses',1);$this->assertDatabaseCount('registration_reviews',2);$this->assertSame('rejected',$second->fresh()->registration_status);
 }
 public function test_rejection_requires_reason_and_warga_can_correct_and_resubmit(): void {
  $officer=$this->officer();$resident=$this->applicant();$this->actingAs($officer)->post(route('rt.registrations.decide',$resident),['decision'=>'rejected'])->assertSessionHasErrors('reason');
  $this->post(route('rt.registrations.decide',$resident),['decision'=>'rejected','reason'=>'Periksa nomor rumah.'])->assertRedirect();
  $this->actingAs($resident->fresh())->get('/pendaftaran/status')->assertOk()->assertSee('Periksa nomor rumah.');$this->get('/pendaftaran/perbaiki')->assertOk();
  $this->put('/pendaftaran/perbaiki',['name'=>$resident->name,'rt_id'=>$resident->rt_id,'block'=>'b3','house_number'=>'15','username'=>'gantiusername','role'=>'admin'])->assertRedirect(route('registration.status'));
  $resident->refresh();$this->assertSame('pending',$resident->registration_status);$this->assertSame('15',$resident->house_number);$this->assertSame(Role::Resident,$resident->role);$this->assertNull($resident->rejection_reason);$this->assertDatabaseCount('houses',0);$this->assertDatabaseCount('registration_reviews',1);$this->get('/pendaftaran/perbaiki')->assertForbidden();
 }
 public function test_warga_cannot_approve_accounts_and_approved_cannot_resubmit(): void {
  $resident=$this->applicant(extra:['registration_status'=>'approved']);$other=$this->applicant();$this->actingAs($resident)->post(route('rt.registrations.decide',$other),['decision'=>'approved'])->assertForbidden();$this->put('/pendaftaran/perbaiki',$this->payload())->assertSessionHasErrors('registration');
 }
 public function test_login_routes_by_role_and_regenerates_authentication(): void {
  $user=$this->officer();$this->post('/masuk',['username'=>$user->username,'password'=>'password123'])->assertRedirect(route('dashboard'));$this->assertAuthenticatedAs($user);$this->get('/dashboard')->assertRedirect(route('rt.dashboard'));
 }
 public function test_staff_must_change_initial_password_before_dashboard(): void {
  $user=$this->officer();$user->must_change_password=true;$user->save();$this->actingAs($user)->get('/rt')->assertRedirect(route('password.initial'));$this->get('/kata-sandi-awal')->assertOk();
  $this->post('/kata-sandi-awal',['current_password'=>'wrong','password'=>'NewPassword123','password_confirmation'=>'NewPassword123'])->assertSessionHasErrors('current_password');
  $this->post('/kata-sandi-awal',['current_password'=>'password123','password'=>'NewPassword123','password_confirmation'=>'NewPassword123'])->assertRedirect(route('dashboard'));
  $this->assertFalse($user->fresh()->must_change_password);$this->assertTrue(Hash::check('NewPassword123',$user->fresh()->password));$this->get('/rt')->assertOk();
 }
 public function test_inactive_account_is_logged_out_and_cannot_log_in(): void {
  $user=$this->officer();$user->is_active=false;$user->save();$this->actingAs($user)->get('/rt')->assertRedirect(route('login'));$this->assertGuest();$this->post('/masuk',['username'=>$user->username,'password'=>'password123'])->assertSessionHasErrors('username');$this->assertGuest();
 }
 public function test_failed_logins_are_limited_for_fifteen_minutes(): void {
  $user=$this->officer();$payload=['username'=>$user->username,'password'=>'incorrect'];for($i=0;$i<5;$i++)$this->post('/masuk',$payload)->assertSessionHasErrors('username');
  $this->post('/masuk',['username'=>$user->username,'password'=>'password123'])->assertSessionHasErrors('username');$this->assertGuest();$this->travel(16)->minutes();$this->post('/masuk',['username'=>$user->username,'password'=>'password123'])->assertRedirect(route('dashboard'));$this->assertAuthenticatedAs($user);
 }
 public function test_approved_portal_pages_render_and_cross_role_access_is_forbidden(): void {
  foreach([[Role::Admin,'/admin'],[Role::Treasurer,'/bendahara'],[Role::Rt,'/rt'],[Role::Resident,'/warga']]as[$role,$url]){
   $user=$this->applicant(extra:['role'=>$role,'registration_status'=>'approved']);$this->actingAs($user)->get($url)->assertOk();$this->get('/dashboard')->assertRedirect($url);if($role!==Role::Admin)$this->get('/admin')->assertForbidden();
  }
  $this->actingAs($this->officer())->get('/rt/data-warga')->assertOk();
 }
 public function test_logout_invalidates_session(): void {
  $this->actingAs($this->officer())->post('/keluar')->assertRedirect(route('login'));$this->assertGuest();$this->get('/rt')->assertRedirect(route('login'));
 }
}
