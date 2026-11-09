<?php
namespace Tests\Feature;
use App\Enums\Role;
use App\Models\{User,Rt,PublicContent,House};
use App\Services\PublicContentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use Tests\TestCase;
class PublicContentTest extends TestCase {
 use RefreshDatabase;
 protected function beforeRefreshingDatabase(): void {if(!str_starts_with(DB::connection()->getDatabaseName(),'sipuri_test_'))throw new \RuntimeException('Isolated database required');}
 protected function setUp(): void {parent::setUp();Storage::fake('public_content');}
 private function actor(Role $role=Role::Admin,array $extra=[]): User {return User::factory()->create(array_merge(['role'=>$role,'registration_status'=>'approved'],$extra));}
 private function png(): UploadedFile {return UploadedFile::fake()->createWithContent('chosen-file.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jN1sAAAAASUVORK5CYII='));}
 private function data(string $section,array $extra=[]): array {
  return array_merge(match($section){'banner','profil'=>['name'=>'Judul '.$section,'description'=>"Paragraf satu.\n\nParagraf dua.",'photo'=>$this->png()],'fasilitas'=>['name'=>'Fasilitas uji','description'=>'Keterangan fasilitas','photo'=>$this->png(),'is_visible'=>1],'pengurus'=>['name'=>'Profil publik uji','job_title'=>'Sekretaris RW','area_level'=>'RW','is_visible'=>1],'kuliner'=>['name'=>'Tempat makan uji','description'=>'Keterangan tempat makan','block'=>'a02','house_number'=>'002','opens_at'=>'18:00','closes_at'=>'02:00','is_visible'=>1]},$extra);
 }
 private function key(User $admin,string $section,?PublicContent $record=null): string {
  $url=$record?route('admin.content.edit',['section'=>$section,'content'=>$record->id]):route(PublicContent::singleton($section)?'admin.content.list':'admin.content.create',['section'=>$section]);
  return $this->actingAs($admin)->get($url)->assertOk()->viewData('requestKey');
 }
 private function create(User $admin,string $section,array $extra=[]): PublicContent {
  $key=$this->key($admin,$section);$this->post(route('admin.content.store',['section'=>$section]),$this->data($section,$extra)+['request_key'=>$key])->assertSessionHasNoErrors()->assertRedirect();return PublicContent::where('section',$section)->orderByDesc('id')->firstOrFail();
 }
 private function visibility(User $admin,PublicContent $record): void {
  $key=$this->actingAs($admin)->get(route('admin.content.confirm',['section'=>$record->section,'content'=>$record->id]))->assertOk()->viewData('requestKey');
  $this->post(route('admin.content.visibility',['section'=>$record->section,'content'=>$record->id]),['request_key'=>$key,'confirmed'=>1])->assertSessionHasNoErrors()->assertRedirect();
 }
 public function test_every_management_route_is_admin_only_including_mutations(): void {
  $admin=$this->actor();$content=$this->create($admin,'pengurus');$rt=Rt::create(['number'=>1,'is_active'=>true]);
  foreach([Role::Resident,Role::Rt,Role::Treasurer] as $role){$u=$this->actor($role,['rt_id'=>$role===Role::Rt?$rt->id:null]);$this->actingAs($u);
   foreach(['/admin/konten-publik','/admin/konten-publik/banner','/admin/konten-publik/profil','/admin/konten-publik/fasilitas','/admin/konten-publik/pengurus','/admin/konten-publik/kuliner','/admin/konten-publik/kuliner/baru',route('admin.content.edit',['section'=>'pengurus','content'=>$content->id]),route('admin.content.confirm',['section'=>'pengurus','content'=>$content->id])] as $url)$this->get($url)->assertForbidden();
   foreach(array_keys(PublicContent::SECTIONS) as $section)$this->post('/admin/konten-publik/'.$section,[])->assertForbidden();
   $this->put('/admin/konten-publik/pengurus/'.$content->id,[])->assertForbidden();$this->post('/admin/konten-publik/pengurus/'.$content->id.'/tampil',[])->assertForbidden();
  }
  $this->actingAs($admin);$admin->must_change_password=true;$admin->save();$this->get('/admin/konten-publik')->assertRedirect(route('password.initial'));
 }
 public function test_banner_and_profile_create_edit_and_public_read_latest_without_mutable_cta_urls(): void {
  $a=$this->actor();foreach(['banner','profil'] as $section){$record=$this->create($a,$section);$key=$this->key($a,$section,$record);$this->put('/admin/konten-publik/'.$section.'/'.$record->id,['request_key'=>$key,'name'=>'Terbaru '.$section,'description'=>"Baris baru.\nProfil aman.",'url'=>'javascript:alert(1)'])->assertRedirect()->assertSessionHasNoErrors();$this->assertSame($record->image_path,$record->fresh()->image_path);}
  $this->post('/keluar');$this->get('/')->assertOk()->assertSee('Terbaru banner')->assertSee('Terbaru profil')->assertSee('Baris baru.')->assertSee(route('login'))->assertSee(route('register'))->assertDontSee('javascript:alert(1)')->assertDontSee('stitch-neighborhood.jpg');
 }
 public function test_new_facility_photo_required_and_successful_replacement_removes_only_owned_unused_photo(): void {
  $a=$this->actor();$key=$this->key($a,'fasilitas');$this->post('/admin/konten-publik/fasilitas',$this->data('fasilitas',['photo'=>null])+['request_key'=>$key])->assertSessionHasErrors('photo');
  $item=$this->create($a,'fasilitas');$old=$item->image_path;$this->assertStringNotContainsString('chosen-file',$old);$key=$this->key($a,'fasilitas',$item);
  $this->put('/admin/konten-publik/fasilitas/'.$item->id,$this->data('fasilitas',['name'=>'Foto diperbarui'])+['request_key'=>$key])->assertSessionHasNoErrors()->assertRedirect();
  $new=$item->fresh()->image_path;$this->assertNotSame($old,$new);Storage::disk('public_content')->assertMissing($old);Storage::disk('public_content')->assertExists($new);
  $this->post('/keluar');$r=$this->get($item->fresh()->imageUrl())->assertOk()->assertHeader('Content-Type','image/png')->assertHeader('X-Content-Type-Options','nosniff');$this->assertStringContainsString('must-revalidate',$r->headers->get('Cache-Control'));
 }
 public function test_optional_photo_officer_and_culinary_placeholders_and_account_independence(): void {
  $a=$this->actor();$rt=Rt::create(['number'=>2,'is_active'=>true]);$w=$this->actor(Role::Resident,['rt_id'=>$rt->id,'block'=>'A2','house_number'=>'2']);House::create(['rt_id'=>$rt->id,'block'=>'A2','house_number'=>'2','current_user_id'=>$w->id]);$baseline=DB::table('users')->orderBy('id')->get()->toJson();
  $officer=$this->create($a,'pengurus',['area_level'=>'RT','rt_id'=>$rt->id]);$place=$this->create($a,'kuliner');$this->assertSame('A2',$place->block);$this->assertSame('2',$place->house_number);$this->assertNull($place->image_path);$this->assertNull($officer->image_path);$this->visibility($a,$officer);$this->visibility($a,$officer->fresh());
  $this->assertSame($baseline,DB::table('users')->orderBy('id')->get()->toJson());$this->assertDatabaseCount('houses',1);$this->assertDatabaseCount('users',2);$this->assertDatabaseCount('staff_account_events',0);
  $this->post('/keluar');$this->get('/')->assertSee('Sekretaris RW')->assertSee('RT 02')->assertSee('Blok A2 No. 2')->assertSee('tutup hari berikutnya')->assertSee('Foto kuliner belum tersedia');
 }
 public function test_hidden_content_and_its_images_are_not_public_but_admin_can_preview(): void {
  $a=$this->actor();$item=$this->create($a,'fasilitas',['name'=>'Fasilitas rahasia','is_visible'=>0]);$url=$item->imageUrl();$this->get($url)->assertOk();$this->post('/keluar');
  $this->get('/')->assertDontSee('Fasilitas rahasia')->assertDontSee($url)->assertDontSee('stitch-hall.jpg');$this->get($url)->assertNotFound();
  $this->visibility($a,$item);$this->post('/keluar');$this->get('/')->assertSee('Fasilitas rahasia');$this->get($url)->assertOk();
  $this->visibility($a,$item->fresh());$this->post('/keluar');$this->get('/')->assertDontSee('Fasilitas rahasia');$this->get($url)->assertNotFound();
 }
 public function test_visibility_confirmation_is_required_and_cannot_toggle_singletons_or_another_section(): void {
  $a=$this->actor();$record=$this->create($a,'kuliner');$key=$this->get('/admin/konten-publik/kuliner/'.$record->id.'/tampil')->viewData('requestKey');
  $this->post('/admin/konten-publik/kuliner/'.$record->id.'/tampil',['request_key'=>$key])->assertSessionHasErrors('confirmed');$this->assertTrue($record->fresh()->is_visible);
  $this->put('/admin/konten-publik/pengurus/'.$record->id,[])->assertNotFound();$this->get('/admin/konten-publik/pengurus/'.$record->id.'/edit')->assertNotFound();
  $banner=$this->create($a,'banner');$this->get('/admin/konten-publik/banner/'.$banner->id.'/tampil')->assertNotFound();
 }
 public function test_rejects_bad_text_locations_times_rt_and_disguised_or_oversized_images(): void {
  $a=$this->actor();$key=$this->key($a,'kuliner');foreach([['block'=>'AA2'],['block'=>'A0'],['house_number'=>'0'],['house_number'=>'-1'],['opens_at'=>'25:00'],['closes_at'=>'9pm'],['description'=>'<script>alert(1)</script>'],['name'=>str_repeat('a',151)],['name'=>'   ']] as $extra)$this->post('/admin/konten-publik/kuliner',$this->data('kuliner',$extra)+['request_key'=>$key])->assertSessionHasErrors();
  $key=$this->key($a,'pengurus');foreach([['area_level'=>'RT','rt_id'=>99999],['area_level'=>'RW','rt_id'=>99],['job_title'=>''],['area_level'=>'XX']] as $extra)$this->post('/admin/konten-publik/pengurus',$this->data('pengurus',$extra)+['request_key'=>$key])->assertSessionHasErrors();
  $key=$this->key($a,'fasilitas');foreach([UploadedFile::fake()->createWithContent('fake.png','<html>not a photo</html>'),UploadedFile::fake()->createWithContent('fake.jpg','<svg xmlns="http://www.w3.org/2000/svg"></svg>'),UploadedFile::fake()->create('large.jpg',5121,'image/jpeg')] as $file)$this->post('/admin/konten-publik/fasilitas',$this->data('fasilitas',['photo'=>$file])+['request_key'=>$key])->assertSessionHasErrors('photo');
  $this->assertDatabaseCount('public_contents',0);$this->assertCount(0,Storage::disk('public_content')->allFiles());
 }
 public function test_legacy_dangerous_text_is_escaped_in_public_and_admin_views(): void {
  $a=$this->actor();$record=$this->create($a,'kuliner');$malicious='<script>alert("danger")</script>';DB::table('public_contents')->where('id',$record->id)->update(['name'=>$malicious,'description'=>'<img src=x onerror=alert(1)>']);
  $this->get('/admin/konten-publik/kuliner')->assertSee(e($malicious),false)->assertDontSee($malicious,false);$this->post('/keluar');$this->get('/')->assertSee(e($malicious),false)->assertDontSee($malicious,false)->assertDontSee('<img src=x onerror=alert(1)>',false);
 }
 public function test_failed_database_save_cleans_new_photo_and_keeps_original_record_and_photo(): void {
  $a=$this->actor();$item=$this->create($a,'fasilitas');$original=$item->fresh()->getAttributes();$key=$this->key($a,'fasilitas',$item);
  PublicContent::saving(function($model){if($model->name==='Simulated failure')throw new \RuntimeException('Simulated database save failure');});
  try{$this->from('/admin/konten-publik/fasilitas/'.$item->id.'/edit')->put('/admin/konten-publik/fasilitas/'.$item->id,$this->data('fasilitas',['name'=>'Simulated failure'])+['request_key'=>$key])->assertSessionHasErrors('content')->assertRedirect();}finally{PublicContent::flushEventListeners();}
  $this->assertSame($original,$item->fresh()->getAttributes());Storage::disk('public_content')->assertExists($item->image_path);$this->assertCount(1,Storage::disk('public_content')->allFiles());
 }
 public function test_shared_or_reference_photos_are_not_deleted_after_replacement(): void {
  $a=$this->actor();$first=$this->create($a,'fasilitas');$second=$this->create($a,'pengurus');DB::table('public_contents')->where('id',$second->id)->update(['image_path'=>$first->image_path]);$shared=$first->image_path;$key=$this->key($a,'fasilitas',$first);
  $this->put('/admin/konten-publik/fasilitas/'.$first->id,$this->data('fasilitas')+['request_key'=>$key])->assertSessionHasNoErrors();Storage::disk('public_content')->assertExists($shared);
  Storage::disk('public_content')->put('shared/reference.jpg','reference-not-owned');DB::table('public_contents')->where('id',$second->id)->update(['image_path'=>'shared/reference.jpg']);$key=$this->key($a,'pengurus',$second->fresh());
  $this->put('/admin/konten-publik/pengurus/'.$second->id,$this->data('pengurus',['photo'=>$this->png()])+['request_key'=>$key])->assertSessionHasNoErrors();Storage::disk('public_content')->assertExists('shared/reference.jpg');
 }
 public function test_duplicate_creation_and_stale_edits_do_not_duplicate_or_replace_saved_images(): void {
  $a=$this->actor();$key=$this->key($a,'fasilitas');for($n=0;$n<2;$n++)$this->post('/admin/konten-publik/fasilitas',$this->data('fasilitas')+['request_key'=>$key])->assertSessionHasNoErrors()->assertRedirect();$this->assertDatabaseCount('public_contents',1);$this->assertCount(1,Storage::disk('public_content')->allFiles());
  $item=PublicContent::first();$editKey=$this->key($a,'fasilitas',$item);$this->put('/admin/konten-publik/fasilitas/'.$item->id,$this->data('fasilitas',['name'=>'Latest'])+['request_key'=>$editKey])->assertSessionHasNoErrors();$saved=$item->fresh()->getAttributes();
  $this->put('/admin/konten-publik/fasilitas/'.$item->id,$this->data('fasilitas',['name'=>'Old request'])+['request_key'=>$editKey])->assertSessionHasErrors('content');$this->assertSame($saved,$item->fresh()->getAttributes());$this->assertCount(1,Storage::disk('public_content')->allFiles());
 }
 public function test_image_route_never_serves_private_disks_or_unreferenced_photos(): void {
  $a=$this->actor();$item=$this->create($a,'fasilitas');$this->post('/keluar');$this->get('/gambar-konten/../../private/file.png')->assertNotFound();$this->get('/gambar-konten/'.str_repeat('a',40).'.svg')->assertNotFound();Storage::disk('public_content')->put('images/'.str_repeat('b',40).'.png','unreferenced');$this->get('/gambar-konten/'.str_repeat('b',40).'.png')->assertNotFound();$this->assertFalse(config('filesystems.disks.public_content.serve'));$this->assertNotSame(config('filesystems.disks.public_content.root'),config('filesystems.disks.payment_proofs.root'));
 }
 public function test_saved_data_prefills_editor_and_lists_are_ordered_and_paginated(): void {
  $a=$this->actor();for($n=1;$n<=11;$n++)$this->create($a,'pengurus',['name'=>'Officer '.$n]);
  $r=$this->get('/admin/konten-publik/pengurus')->assertOk()->assertViewHas('items',fn($p)=>$p->total()===11&&$p->count()===10);$this->get($r->viewData('items')->nextPageUrl())->assertSee('Officer 11');
  $item=PublicContent::orderBy('id')->first();$this->get('/admin/konten-publik/pengurus/'.$item->id.'/edit')->assertSee('value="Officer 1"',false)->assertSee('Sekretaris RW')->assertSee('Simpan Pengurus');
  $this->get('/')->assertSeeInOrder(['Officer 1','Officer 2','Officer 3']);
 }
 public function test_edit_culinary_and_officer_fields_are_saved_without_touching_accounts_or_house_claims(): void {
  $a=$this->actor();$place=$this->create($a,'kuliner');$key=$this->key($a,'kuliner',$place);
  $this->put('/admin/konten-publik/kuliner/'.$place->id,$this->data('kuliner',['name'=>'Kedai diperbarui','block'=>' Blok F012 ','house_number'=>'No. 00012','opens_at'=>'07:30','closes_at'=>'19:00','is_visible'=>0])+['request_key'=>$key])->assertRedirect()->assertSessionHasNoErrors();
  $this->assertSame('F12',$place->fresh()->block);$this->assertSame('12',$place->fresh()->house_number);$this->assertFalse($place->fresh()->is_visible);$this->assertSame('07:30–19:00',$place->fresh()->hours());
  $rt=Rt::create(['number'=>4,'is_active'=>true]);$officer=$this->create($a,'pengurus');$key=$this->key($a,'pengurus',$officer);$this->put('/admin/konten-publik/pengurus/'.$officer->id,$this->data('pengurus',['name'=>'Nama diperbarui','job_title'=>'Pengurus RT','area_level'=>'RT','rt_id'=>$rt->id])+['request_key'=>$key])->assertRedirect()->assertSessionHasNoErrors();
  $this->assertSame($rt->id,$officer->fresh()->rt_id);$this->assertSame('Nama diperbarui',$officer->fresh()->name);$this->assertDatabaseCount('users',1);$this->assertDatabaseCount('houses',0);$this->assertDatabaseCount('address_write_locks',1);
  $key=$this->key($a,'pengurus',$officer->fresh());$this->put('/admin/konten-publik/pengurus/'.$officer->id,$this->data('pengurus',['area_level'=>'RW'])+['request_key'=>$key])->assertRedirect()->assertSessionHasNoErrors();$this->assertNull($officer->fresh()->rt_id);$this->assertSame('RW 07',$officer->fresh()->region());
 }
 public function test_valid_jpeg_and_webp_are_served_with_real_mime_types(): void {
  $a=$this->actor();$jpeg=UploadedFile::fake()->createWithContent('photo.jpg',file_get_contents(public_path('assets/images/stitch-park.jpg')));$webp=UploadedFile::fake()->createWithContent('photo.webp',base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEAAUAmJaQAA3AA/v89WAAAAA=='));
  foreach([[$jpeg,'image/jpeg'],[$webp,'image/webp']] as [$photo,$mime]){$item=$this->create($a,'fasilitas',['photo'=>$photo]);$this->get($item->imageUrl())->assertOk()->assertHeader('Content-Type',$mime);}
 }
 public function test_storage_failure_and_missing_old_photo_do_not_damage_database(): void {
  $a=$this->actor();$item=$this->create($a,'fasilitas');$original=$item->fresh()->getAttributes();$file=$this->png();
  $failing=new class($file->getRealPath(),'photo.png','image/png',null,true) extends UploadedFile {public function store($path='',$options=[]){throw new \RuntimeException('Simulated storage failure');}};
  try {app(PublicContentService::class)->save($a,'fasilitas',$item,['name'=>'Must not save'],$failing,(string)Str::uuid(),$item->version);$this->fail('Storage failure must be reported');}catch(\RuntimeException $e){$this->assertSame('Simulated storage failure',$e->getMessage());}
  $this->assertSame($original,$item->fresh()->getAttributes());Storage::disk('public_content')->assertExists($item->image_path);
  Storage::disk('public_content')->delete($item->image_path);$key=$this->key($a,'fasilitas',$item);$this->put('/admin/konten-publik/fasilitas/'.$item->id,['request_key'=>$key,'name'=>'Name','description'=>'Valid text','is_visible'=>1])->assertSessionHasErrors('photo');$this->assertSame($original,$item->fresh()->getAttributes());
 }
 public function test_expired_or_foreign_form_and_inactive_admin_image_preview_are_rejected(): void {
  $a=$this->actor();$item=$this->create($a,'fasilitas',['is_visible'=>0]);$key=$this->key($a,'fasilitas',$item);$this->travel(21)->minutes();$this->put('/admin/konten-publik/fasilitas/'.$item->id,$this->data('fasilitas')+['request_key'=>$key])->assertSessionHasErrors('content');
  $key=$this->key($a,'kuliner');$this->post('/admin/konten-publik/pengurus',$this->data('pengurus')+['request_key'=>$key])->assertSessionHasErrors('content');
  $url=$item->imageUrl();$u=$this->actor(Role::Resident);$this->actingAs($u)->get($url)->assertNotFound();$this->actingAs($a);DB::table('users')->where('id',$a->id)->update(['is_active'=>false]);$this->get($url)->assertNotFound();
 }
}
