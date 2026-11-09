<?php
namespace Tests\Feature;
use App\Enums\Role;
use App\Models\{User,Rt,FinancialReport};
use App\Services\StaffAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Hash};
use Tests\TestCase;
class AdminStaffTest extends TestCase {
 use RefreshDatabase;
 protected function beforeRefreshingDatabase(): void {if(!str_starts_with(DB::connection()->getDatabaseName(),'sipuri_test_'))throw new \RuntimeException('Isolated MariaDB test database required');}
 private function user(Role $role=Role::Admin,array $extra=[]): User {return User::factory()->create(array_merge(['role'=>$role,'registration_status'=>'approved'],$extra));}
 private function rt(int $number=1,bool $active=true): Rt {return Rt::firstOrCreate(['number'=>$number],['is_active'=>$active]);}
 private function payload(Rt $rt,array $extra=[]): array {return array_merge(['name'=>'Nama Pengurus Baru','username'=>'petugas_baru','role'=>'rt','rt_id'=>$rt->id],$extra);}
 private function key(User $admin): string {return $this->actingAs($admin)->get('/admin/akun')->assertOk()->viewData('requestKey');}
 private function statusKey(User $admin,User $target,bool $active): string {return $this->actingAs($admin)->get(route('admin.accounts.status',['target'=>$target->id,'operation'=>$active?'activate':'deactivate']))->assertOk()->viewData('actionKey');}
 private function setStatus(User $admin,User $target,bool $active): void {$key=$this->statusKey($admin,$target,$active);$this->post(route($active?'admin.accounts.activate':'admin.accounts.deactivate',$target),['request_key'=>$key,'confirmed'=>1])->assertSessionHasNoErrors()->assertRedirect();}
 public function test_only_admin_can_access_every_staff_operation_and_targets_cannot_be_admin_or_resident(): void {
  $rt=$this->rt();$target=$this->user(Role::Rt,['rt_id'=>$rt->id]);
  foreach([Role::Resident,Role::Rt,Role::Treasurer] as $role){$actor=$this->user($role,['rt_id'=>$role===Role::Rt?$rt->id:null]);$this->actingAs($actor);
   foreach(['/admin/akun',route('admin.accounts.manage',$target),route('admin.accounts.status',['target'=>$target->id,'operation'=>'deactivate']),'/admin/akun/pemulihan-warga'] as $url)$this->get($url)->assertForbidden();
   $this->post('/admin/akun',[])->assertForbidden();$this->put(route('admin.accounts.rename',$target),[])->assertForbidden();$this->post(route('admin.accounts.deactivate',$target),[])->assertForbidden();$this->post(route('admin.accounts.activate',$target),[])->assertForbidden();
  }
  $admin=$this->user();$resident=$this->user(Role::Resident);foreach([$admin,$resident] as $invalid){$this->actingAs($admin)->get(route('admin.accounts.manage',$invalid))->assertForbidden();$this->put(route('admin.accounts.rename',$invalid),['name'=>'Tamper'])->assertForbidden();$this->post(route('admin.accounts.deactivate',$invalid),[])->assertForbidden();}
  $this->delete(route('admin.accounts.manage',$target))->assertStatus(405);
 }
 public function test_database_list_summary_search_filters_pagination_and_resident_recovery_are_separate(): void {
  $admin=$this->user();$rt=$this->rt();$resident=$this->user(Role::Resident,['username'=>'resident_private']);
  for($n=1;$n<=12;$n++)$this->user(Role::Rt,['name'=>'Pengurus '.$n,'username'=>'rt_staff_'.$n,'rt_id'=>$rt->id,'is_active'=>false]);
  $b=$this->user(Role::Treasurer,['name'=>'Bendahara Aktif']);$this->actingAs($admin);
  $r=$this->get('/admin/akun?role=rt&status=inactive&q=rt_staff')->assertOk()->assertViewHas('summary',['total'=>13,'active'=>1,'inactive'=>12])->assertViewHas('accounts',fn($p)=>$p->total()===12&&$p->count()===10)->assertDontSee($resident->username);
  $next=$r->viewData('accounts')->nextPageUrl();$this->assertStringContainsString('status=inactive',$next);$this->assertStringContainsString('role=rt',$next);$this->get($next)->assertViewHas('accounts',fn($p)=>$p->count()===2&&$p->total()===12);
  $this->get('/admin/akun?q=Bendahara&status=active&role=bendahara')->assertViewHas('accounts',fn($p)=>$p->total()===1)->assertSee($b->username);
  $this->get('/admin/akun?q=notfound')->assertSee('Tidak Ada Akun Pengurus Ditemukan');
  $this->get('/admin/akun/pemulihan-warga')->assertSee($resident->username)->assertDontSee($b->username);
  $this->get('/admin')->assertOk()->assertSee('Buka Kelola Akun')->assertSee(route('admin.accounts'))->assertDontSee('kos');
 }
 public function test_creation_normalizes_username_hashes_random_password_and_displays_it_once_without_persistence(): void {
  $admin=$this->user();$rt=$this->rt();$key=$this->key($admin);
  $response=$this->post('/admin/akun',$this->payload($rt,['request_key'=>$key,'username'=>'  RT.New_01  ']))->assertOk()->assertHeader('Pragma','no-cache')->assertSee('Akun Pengurus Berhasil Dibuat');
  $temporary=$response->viewData('temporary');$u=User::where('username','rt.new_01')->firstOrFail();
  $this->assertTrue(Hash::check($temporary,$u->password));$this->assertTrue($u->must_change_password);$this->assertTrue($u->is_active);$this->assertSame(Role::Rt,$u->role);$this->assertSame($rt->id,$u->rt_id);
  $this->assertMatchesRegularExpression('/\A(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9]).{8,128}\z/',$temporary);
  $this->assertFalse(str_contains(serialize(session()->all()),$temporary));$this->assertStringNotContainsString($temporary,json_encode(DB::table('staff_account_events')->get()));
  $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));$this->assertSame(1,$response->viewData('summary')['total']);
  $this->post('/admin/akun',$this->payload($rt,['request_key'=>$key,'username'=>'rt.new_01']))->assertRedirect(route('admin.accounts'));$this->assertDatabaseCount('staff_account_events',1);$this->assertSame(1,User::where('role','rt')->count());
  $this->get('/admin/akun')->assertDontSee($temporary,false);
  $this->post('/keluar');$this->post('/masuk',['username'=>$u->username,'password'=>$temporary])->assertRedirect(route('dashboard'));$this->get('/rt')->assertRedirect(route('password.initial'));
 }
 public function test_username_collisions_include_resident_admin_and_inactive_accounts(): void {
  $admin=$this->user();$rt=$this->rt();$this->user(Role::Resident,['username'=>'old.resident','is_active'=>false]);$key=$this->key($admin);
  foreach([' OLD.RESIDENT ',$admin->username] as $username)$this->post('/admin/akun',$this->payload($rt,['request_key'=>$key,'username'=>$username]))->assertSessionHasErrors('username');
  foreach(['bad name','_startswrong','x','český'] as $username)$this->post('/admin/akun',$this->payload($rt,['request_key'=>$key,'username'=>$username]))->assertSessionHasErrors('username');
  $this->assertDatabaseCount('staff_account_events',0);
 }
 public function test_rt_must_be_active_and_treasurer_has_no_rt_and_only_one_active_slot(): void {
  $admin=$this->user();$inactive=$this->rt(3,false);$rt=$this->rt();$key=$this->key($admin);
  foreach([$inactive->id,999999] as $id)$this->post('/admin/akun',$this->payload($rt,['request_key'=>$key,'rt_id'=>$id]))->assertSessionHasErrors('rt_id');
  $this->post('/admin/akun',$this->payload($rt,['request_key'=>$key,'role'=>'admin']))->assertSessionHasErrors('role');
  $this->post('/admin/akun',$this->payload($rt,['request_key'=>$key,'role'=>'bendahara']))->assertSessionHasErrors('rt_id');
  $this->post('/admin/akun',['request_key'=>$key,'name'=>'Bendahara Baru','username'=>'bendahara_baru','role'=>'bendahara'])->assertOk();$b=User::where('username','bendahara_baru')->firstOrFail();$this->assertNull($b->rt_id);$this->assertTrue($b->must_change_password);
  $key=$this->key($admin);$this->post('/admin/akun',['request_key'=>$key,'name'=>'Bendahara Kedua','username'=>'bendahara_kedua','role'=>'bendahara'])->assertSessionHasErrors('staff_conflict');$this->assertSame(1,User::where('role','bendahara')->count());
 }
 public function test_active_rt_creation_refuses_replacement_without_deactivating_old_account(): void {
  $admin=$this->user();$rt=$this->rt();$old=$this->user(Role::Rt,['rt_id'=>$rt->id]);$key=$this->key($admin);
  $this->post('/admin/akun',$this->payload($rt,['request_key'=>$key]))->assertSessionHasErrors('staff_conflict');$this->assertTrue($old->fresh()->is_active);$this->assertSame(1,User::where('role','rt')->count());
  $this->setStatus($admin,$old,false);$key=$this->key($admin);$this->post('/admin/akun',$this->payload($rt,['request_key'=>$key]))->assertOk();$this->assertFalse($old->fresh()->is_active);$this->assertSame(1,User::where('role','rt')->where('is_active',true)->count());
 }
 public function test_rename_only_changes_name_and_preserves_financial_audit_ownership_and_readonly_fields(): void {
  $admin=$this->user();$target=$this->user(Role::Treasurer);$report=FinancialReport::create(['year'=>2026,'month'=>1,'version'=>1,'source_fingerprint'=>str_repeat('a',64),'request_hash'=>str_repeat('b',64),'summary'=>['opening'=>0,'income'=>0,'expense'=>0,'closing'=>0],'public_rows'=>[],'published_by'=>$target->id,'published_at'=>now()]);$before=$report->fresh()->getAttributes();
  $page=$this->actingAs($admin)->get(route('admin.accounts.manage',['target'=>$target->id,'role'=>'bendahara','status'=>'active']))->assertOk();$key=$page->viewData('actionKey');$url=route('admin.accounts.rename',['target'=>$target->id,'role'=>'bendahara','status'=>'active']);
  $this->put($url,['request_key'=>$key,'name'=>'New Name','role'=>'admin','rt_id'=>999])->assertSessionHasErrors(['role','rt_id']);
  $this->put($url,['request_key'=>$key,'name'=>'New Name'])->assertSessionHasNoErrors()->assertRedirect();$fresh=$target->fresh();$this->assertSame('New Name',$fresh->name);$this->assertSame($target->username,$fresh->username);$this->assertNull($fresh->rt_id);$this->assertSame(Role::Treasurer,$fresh->role);$this->assertSame($target->password,$fresh->password);$this->assertSame($before,$report->fresh()->getAttributes());
  $event=DB::table('staff_account_events')->first();$this->assertSame('renamed',$event->kind);$this->assertSame($admin->id,(int)$event->actor_id);$this->assertArrayNotHasKey('password',json_decode($event->after_state,true));
 }
 public function test_deactivation_revokes_sessions_remember_tokens_and_next_request_and_login_are_denied(): void {
  $admin=$this->user();$target=$this->user(Role::Rt,['rt_id'=>$this->rt()->id,'remember_token'=>'old-remember']);$password=$target->password;
  foreach([['target-a',$target],['target-b',$target],['admin-session',$admin]] as [$id,$u])DB::table('sessions')->insert(['id'=>$id,'user_id'=>$u->id,'payload'=>'test-only','last_activity'=>time()]);
  $key=$this->statusKey($admin,$target,false);$this->post(route('admin.accounts.deactivate',$target),['request_key'=>$key])->assertSessionHasErrors('confirmed');
  $this->post(route('admin.accounts.deactivate',$target),['request_key'=>$key,'confirmed'=>1])->assertSessionHasNoErrors();$fresh=$target->fresh();$this->assertFalse($fresh->is_active);$this->assertSame($password,$fresh->password);$this->assertNotSame('old-remember',$fresh->remember_token);$this->assertSame(1,$fresh->credentials_version);
  $this->assertSame(0,DB::table('sessions')->where('user_id',$target->id)->count());$this->assertSame(1,DB::table('sessions')->where('user_id',$admin->id)->count());
  $this->actingAs($target)->withSession(['credentials.'.$target->id=>0])->get('/rt')->assertRedirect(route('login'));$this->assertGuest();
  $this->post('/masuk',['username'=>$target->username,'password'=>'password123'])->assertSessionHasErrors('username');$this->assertGuest();
 }
 public function test_activation_checks_replacement_and_preserves_initial_password_requirement(): void {
  $admin=$this->user();$rt=$this->rt();$old=$this->user(Role::Rt,['rt_id'=>$rt->id,'is_active'=>false,'must_change_password'=>true]);$replacement=$this->user(Role::Rt,['rt_id'=>$rt->id]);$key=$this->statusKey($admin,$old,true);
  $this->post(route('admin.accounts.activate',$old),['request_key'=>$key,'confirmed'=>1])->assertSessionHasErrors('staff_conflict');$this->assertFalse($old->fresh()->is_active);
  $this->setStatus($admin,$replacement,false);$this->setStatus($admin,$old,true);$this->assertTrue($old->fresh()->is_active);$this->assertTrue($old->fresh()->must_change_password);$this->assertSame($rt->id,$old->fresh()->rt_id);
  $b=$this->user(Role::Treasurer,['is_active'=>false]);$other=$this->user(Role::Treasurer);$key=$this->statusKey($admin,$b,true);$this->post(route('admin.accounts.activate',$b),['request_key'=>$key,'confirmed'=>1])->assertSessionHasErrors('staff_conflict');$this->assertTrue($other->fresh()->is_active);
 }
 public function test_reset_inactive_staff_uses_existing_service_and_does_not_activate_it(): void {
  $admin=$this->user();$b=$this->user(Role::Treasurer,['is_active'=>false]);$this->actingAs($admin)->get(route('account.reset.confirm',$b))->assertOk();$token=session('reset_challenge.'.$b->id)['token'];
  $response=$this->post(route('account.reset.store',$b),['token'=>$token,'confirmed'=>1])->assertOk();$this->assertFalse($b->fresh()->is_active);$this->assertTrue($b->fresh()->must_change_password);$this->assertTrue(Hash::check($response->viewData('temporary'),$b->fresh()->password));$this->assertDatabaseCount('credential_events',1);
 }
 public function test_legacy_conflicts_are_reported_without_automatic_changes(): void {
  $admin=$this->user();$rt=$this->rt();$a=$this->user(Role::Rt,['rt_id'=>$rt->id]);$b=$this->user(Role::Rt,['rt_id'=>$rt->id]);$this->user(Role::Treasurer);$this->user(Role::Treasurer);
  $this->actingAs($admin)->get('/admin/akun')->assertOk()->assertSee('Konflik data lama')->assertViewHas('conflicts',fn($c)=>count($c)===2);$this->assertTrue($a->fresh()->is_active);$this->assertTrue($b->fresh()->is_active);$this->assertDatabaseCount('staff_account_events',0);
 }
 public function test_action_challenges_bind_target_action_actor_and_expire_and_status_replay_does_not_toggle(): void {
  $admin=$this->user();$a=$this->user(Role::Rt,['rt_id'=>$this->rt()->id]);$b=$this->user(Role::Rt,['rt_id'=>$this->rt(2)->id]);$key=$this->statusKey($admin,$a,false);
  $this->post(route('admin.accounts.deactivate',$b),['request_key'=>$key,'confirmed'=>1])->assertSessionHasErrors('request_key');
  $this->post(route('admin.accounts.activate',$a),['request_key'=>$key,'confirmed'=>1])->assertSessionHasErrors('request_key');
  $this->post(route('admin.accounts.deactivate',$a),['request_key'=>$key,'confirmed'=>1])->assertSessionHasNoErrors();
  $this->post(route('admin.accounts.deactivate',$a),['request_key'=>$key,'confirmed'=>1])->assertSessionHasNoErrors();$this->assertSame(1,$a->fresh()->credentials_version);$this->assertDatabaseCount('staff_account_events',1);
  $expired=$this->statusKey($admin,$b,false);$this->travel(21)->minutes();$this->post(route('admin.accounts.deactivate',$b),['request_key'=>$expired,'confirmed'=>1])->assertSessionHasErrors('request_key');$this->assertTrue($b->fresh()->is_active);
 }
}
