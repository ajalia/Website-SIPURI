<?php
namespace Tests\Feature;
use App\Enums\Role;
use App\Models\{User,Rt,House,HouseResidency,PaymentSubmission,FinancialReport};
use App\Services\{CashBookService,DuesService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use Tests\TestCase;
class CashReportTest extends TestCase {
 use RefreshDatabase;
 protected function setUp(): void {parent::setUp();if(!str_starts_with(DB::connection()->getDatabaseName(),'sipuri_test_'))throw new \RuntimeException('Isolated MariaDB test database required');Storage::fake('cash_proofs');Storage::fake('payment_proofs');}
 private function actor(Role $role=Role::Treasurer,array $extra=[]): User {return User::factory()->create(array_merge(['role'=>$role,'registration_status'=>'approved'],$extra));}
 private function png(): UploadedFile {return UploadedFile::fake()->createWithContent('user-filename.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jN1sAAAAASUVORK5CYII='));}
 private function opening(User $t,string $date='2025-01-01',int $balance=1000000): void {$this->actingAs($t)->post('/bendahara/kas-laporan/saldo-pembukaan',['starts_on'=>$date,'opening_balance'=>$balance,'opening_confirmed'=>1])->assertSessionHasNoErrors()->assertRedirect();}
 private function manual(User $t,string $date='2026-01-15',int $amount=100000,string $kind='income',string $description='Catatan internal'): int {
  $page=$this->actingAs($t)->get('/bendahara/kas-laporan?month=1&year=2026')->assertOk();$key=$page->viewData('requestKey');
  $this->post('/bendahara/kas-laporan/transaksi?month=1&year=2026',['request_key'=>$key,'kind'=>$kind,'transaction_date'=>$date,'amount'=>$amount,'description'=>$description,'proof'=>$this->png()])->assertSessionHasNoErrors()->assertRedirect();return (int)DB::table('cash_transactions')->max('id');
 }
 private function payment(User $t,string $date='2026-01-10',string $decision='accepted'): PaymentSubmission {
  $rt=Rt::firstOrCreate(['number'=>1],['is_active'=>true]);$n=House::count()+1;
  $u=$this->actor(Role::Resident,['rt_id'=>$rt->id,'block'=>'A2','house_number'=>(string)$n,'name'=>'Private Payer '.$n]);
  $h=House::create(['rt_id'=>$rt->id,'block'=>'A2','house_number'=>(string)$n,'current_user_id'=>$u->id]);HouseResidency::create(['house_id'=>$h->id,'user_id'=>$u->id,'resident_name'=>$u->name,'block'=>'A2','house_number'=>(string)$n,'started_at'=>'2024-01-01']);
  $period=\App\Models\DuesPeriod::first()??app(DuesService::class)->issue(['year'=>2026,'month'=>7,'amount'=>125000,'due_date'=>'2026-07-10'],$t->id);
  $i=$period->invoices()->where('user_id',$u->id)->first();
  if(!$i){$base=$period->invoices()->first()->replicate();$base->house_id=$h->id;$base->house_residency_id=HouseResidency::where('house_id',$h->id)->value('id');$base->user_id=$u->id;$base->resident_name=$u->name;$base->house_number=(string)$n;$base->status='unpaid';$base->settled_at=null;$base->save();$i=$base;}
  $path=$this->png()->store('proofs','payment_proofs');$p=PaymentSubmission::create(['invoice_id'=>$i->id,'user_id'=>$u->id,'paid_on'=>$date,'amount'=>125000,'proof_path'=>$path,'mime'=>'image/png','file_size'=>68,'bank_name'=>'PrivateBank','account_number'=>'998877','account_holder'=>'PrivateHolder','status'=>'pending','pending_invoice_id'=>$i->id]);
  if($decision!=='pending')app(DuesService::class)->decide($p,$decision,$t->id,$decision==='rejected'?'Tidak jelas':null);return $p->fresh();
 }
 private function statement(int $year=2026,int $month=1): array {$service=app(CashBookService::class);return $service->locked(fn($book)=>$service->statement($year,$month,$book));}
 private function preview(User $admin,int $year=2026,int $month=1,array $descriptions=[]): string {
  $r=$this->actingAs($admin)->post('/admin/laporan/tinjau?'.http_build_query(compact('year','month')),['privacy_reviewed'=>1,'descriptions'=>$descriptions])->assertOk();return $r->viewData('token');
 }
 private function publish(User $admin,string $token): FinancialReport {$this->actingAs($admin)->post('/admin/laporan/terbitkan',['token'=>$token,'confirmed'=>1])->assertSessionHasNoErrors()->assertRedirect();return FinancialReport::orderByDesc('id')->firstOrFail();}
 public function test_opening_is_required_covers_earliest_income_and_cannot_be_changed(): void {
  $t=$this->actor();$this->payment($t,'2025-12-31');$a=$this->actor(Role::Admin);
  $this->actingAs($t)->get('/bendahara/kas-laporan')->assertOk()->assertSee('Saldo awal belum diatur');
  $this->actingAs($a)->post('/admin/laporan/tinjau?year=2025&month=12',['privacy_reviewed'=>1])->assertSessionHasErrors('publication');
  $this->actingAs($t)->post('/bendahara/kas-laporan/saldo-pembukaan',['starts_on'=>'2026-01-01','opening_balance'=>123,'opening_confirmed'=>1])->assertSessionHasErrors('starts_on');
  $this->opening($t,'2025-12-31',700000);
  $this->post('/bendahara/kas-laporan/saldo-pembukaan',['starts_on'=>'2025-01-01','opening_balance'=>0,'opening_confirmed'=>1])->assertSessionHasErrors('opening');
  $this->assertSame(700000,(int)DB::table('cash_books')->value('opening_balance'));
  $beforeStart=$this->statement(2025,1);$this->assertFalse($beforeStart['balanceAvailable']);$this->assertFalse($beforeStart['ready']);
 }
 public function test_integer_balances_cross_years_use_transaction_dates_and_verified_dues_once(): void {
  $t=$this->actor();$this->opening($t);$this->manual($t,'2025-12-30',300000);$this->manual($t,'2026-01-10',40000,'expense');
  $accepted=$this->payment($t,'2025-12-31');$this->payment($t,'2026-01-12','pending');$this->payment($t,'2026-01-13','rejected');
  $s=$this->statement();$this->assertSame(['opening'=>1425000,'income'=>0,'expense'=>40000,'closing'=>1385000],$s['summary']);
  $this->assertCount(1,$s['period']);$this->assertDatabaseCount('dues_income_entries',1);$this->assertDatabaseCount('cash_transactions',2);
  try {app(DuesService::class)->decide($accepted,'accepted',$t->id,null);$this->fail('Cannot accept twice');}catch(\Illuminate\Validation\ValidationException $e){$this->assertArrayHasKey('decision',$e->errors());}
  $this->assertSame($s['summary'],$this->statement()['summary']);$this->assertSame(125000,$this->statement(2025,12)['summary']['income']-300000);
 }
 public function test_manual_validation_proof_mime_dates_positive_integers_and_session_key(): void {
  $t=$this->actor();$this->opening($t,'2026-01-01');$page=$this->actingAs($t)->get('/bendahara/kas-laporan');$key=$page->viewData('requestKey');
  $base=['request_key'=>$key,'kind'=>'expense','transaction_date'=>'2026-01-02','description'=>'Pengeluaran sah','amount'=>10000,'proof'=>$this->png()];
  foreach([['amount'=>0],['amount'=>-2],['amount'=>'12.5'],['kind'=>'dues'],['transaction_date'=>'2025-12-31'],['description'=>'   '],['request_key'=>(string)Str::uuid()],['proof'=>null],['proof'=>UploadedFile::fake()->create('big.pdf',5121,'application/pdf')],['proof'=>UploadedFile::fake()->createWithContent('fake.png','<html>not an image</html>')]] as $extra){$this->post('/bendahara/kas-laporan/transaksi',array_merge($base,$extra))->assertSessionHasErrors();}
  $this->assertDatabaseCount('cash_transactions',0);$this->assertCount(0,Storage::disk('cash_proofs')->allFiles());
 }
 public function test_repeated_submission_stores_one_transaction_and_one_private_proof(): void {
  $t=$this->actor();$this->opening($t);$key=$this->actingAs($t)->get('/bendahara/kas-laporan')->viewData('requestKey');
  for($n=0;$n<2;$n++)$this->post('/bendahara/kas-laporan/transaksi',['request_key'=>$key,'kind'=>'income','transaction_date'=>'2026-01-02','description'=>'Donasi lingkungan','amount'=>5000,'proof'=>$this->png()])->assertSessionHasNoErrors();
  $this->assertDatabaseCount('cash_transactions',1);$this->assertDatabaseCount('cash_proofs',1);$this->assertCount(1,Storage::disk('cash_proofs')->allFiles());
  $proof=DB::table('cash_proofs')->first();$this->assertStringNotContainsString('user-filename',$proof->path);$this->assertFalse(config('filesystems.disks.cash_proofs.serve'));
  $this->assertNotNull(app(CashBookService::class)->evidence('manual',$proof->source_id),json_encode($proof));$this->get('/bendahara/kas-laporan/bukti/manual/'.$proof->source_id)->assertOk()->assertHeader('X-Content-Type-Options','nosniff')->assertHeader('Content-Security-Policy',"default-src 'none'; sandbox");
  $this->actingAs($this->actor(Role::Admin))->get('/bendahara/kas-laporan/bukti/manual/1')->assertForbidden();
  $this->actingAs($this->actor(Role::Resident))->get('/bendahara/kas-laporan/bukti/manual/1')->assertForbidden();
  $this->get('/storage/'.$proof->path)->assertStatus(403);
 }
 public function test_missing_legacy_dues_proof_blocks_publication_and_can_be_supplemented_without_changing_payment(): void {
  $t=$this->actor();$this->opening($t);$p=$this->payment($t);$before=$p->getAttributes();Storage::disk('payment_proofs')->delete($p->proof_path);
  $s=$this->statement();$this->assertFalse($s['ready']);$this->assertCount(1,$s['missing']);$id=$s['missing'][0]['id'];
  $this->actingAs($t)->get('/bendahara/kas-laporan?year=2026&month=1')->assertSee('Bukti belum tersedia')->assertSee('Lengkapi Bukti');
  $this->post('/bendahara/kas-laporan/lengkapi-bukti',['source'=>'dues:'.$id,'proof'=>$this->png()])->assertSessionHasNoErrors()->assertRedirect();
  $checked=$this->statement();$this->assertTrue($checked['ready'],json_encode($checked['problems']));$this->assertSame($before,$p->fresh()->getAttributes());
  $this->post('/bendahara/kas-laporan/lengkapi-bukti',['source'=>'dues:'.$id,'proof'=>$this->png()])->assertSessionHasErrors('source');$this->assertDatabaseCount('cash_proofs',1);
  $this->get('/bendahara/kas-laporan/bukti/dues/'.$id)->assertOk();
 }
 public function test_admin_must_review_public_descriptions_and_confirm_publication(): void {
  $t=$this->actor();$this->opening($t);$id=$this->manual($t);$a=$this->actor(Role::Admin);
  $this->actingAs($a)->post('/admin/laporan/tinjau?year=2026&month=1',['descriptions'=>[$id=>'Aman']])->assertSessionHasErrors('privacy_reviewed');
  $this->post('/admin/laporan/tinjau?year=2026&month=1',['privacy_reviewed'=>1])->assertSessionHasErrors('descriptions.'.$id);
  $token=$this->preview($a,2026,1,[$id=>'Donasi kegiatan lingkungan']);
  $this->post('/admin/laporan/terbitkan',['token'=>$token])->assertRedirect(route('admin.reports',['year'=>(int)now('Asia/Jakarta')->year,'month'=>(int)now('Asia/Jakarta')->month]))->assertSessionHasErrors('confirmed');
  $report=$this->publish($a,$token);$this->assertSame('Donasi kegiatan lingkungan',$report->public_rows[0]['description']);
  $this->assertSame('Catatan internal',DB::table('cash_transactions')->where('id',$id)->value('description'));
  $this->post('/admin/laporan/terbitkan',['token'=>$token,'confirmed'=>1])->assertSessionHasErrors('publication');$this->assertDatabaseCount('financial_reports',1);
 }
 public function test_changed_source_blocks_stale_preview_and_keeps_published_snapshots_and_versions(): void {
  $t=$this->actor();$this->opening($t);$id=$this->manual($t);$a=$this->actor(Role::Admin);$token=$this->preview($a,2026,1,[$id=>'Donasi umum']);
  $second=$this->manual($t,'2026-01-20',25000,'expense','Rahasia internal');
  $this->actingAs($a)->post('/admin/laporan/terbitkan',['token'=>$token,'confirmed'=>1])->assertSessionHasErrors('publication');$this->assertDatabaseCount('financial_reports',0);
  $first=$this->publish($a,$this->preview($a,2026,1,[$id=>'Donasi umum',$second=>'Perawatan lingkungan']));$baseline=$first->getAttributes();
  $third=$this->manual($t,'2026-01-21',30000,'income','Catatan privat');
  $this->actingAs($a)->get('/admin/laporan?year=2026&month=1')->assertOk()->assertViewHas('stale',true)->assertSee('Perlu Diperbarui');
  $u=$this->actor(Role::Resident);$this->actingAs($u)->get('/warga/laporan-keuangan?year=2026&month=1')->assertViewHas('report',fn($r)=>$r->id===$first->id)->assertDontSee('Catatan privat')->assertDontSee('Rahasia internal');
  $latest=$this->publish($a,$this->preview($a,2026,1,[$id=>'Donasi umum',$second=>'Perawatan lingkungan',$third=>'Sumbangan umum']));
  $this->assertSame(2,$latest->version);$this->assertSame($baseline,$first->fresh()->getAttributes());$this->assertDatabaseCount('financial_reports',2);
  $this->actingAs($u)->get('/warga/laporan-keuangan?year=2026&month=1&version=1')->assertViewHas('report',fn($r)=>$r->id===$latest->id)->assertSee('Sumbangan umum');
  $this->actingAs($a)->get('/admin/laporan?year=2026&month=1&version=1')->assertViewHas('historic',fn($r)=>$r->id===$first->id);
 }
 public function test_public_dues_are_grouped_by_payment_date_without_personal_data_and_totals_match(): void {
  $t=$this->actor();$this->opening($t);$this->payment($t);$this->payment($t);$this->payment($t,'2026-01-11');$a=$this->actor(Role::Admin);
  $r=$this->publish($a,$this->preview($a));$this->assertCount(2,$r->public_rows);$this->assertSame(375000,array_sum(array_column($r->public_rows,'income')));$this->assertSame($this->statement()['summary'],$r->summary);
  $json=json_encode($r->public_rows);foreach(['Private Payer','PrivateBank','998877','PrivateHolder','house','proof','username','invoice'] as $private)$this->assertStringNotContainsString($private,$json);
  $u=$this->actor(Role::Resident);$this->actingAs($u)->get('/warga/laporan-keuangan?year=2026&month=1')->assertOk()->assertSee('Penerimaan iuran warga')->assertDontSee('Private Payer')->assertDontSee('Bukti')->assertDontSee('Terbitkan');
  $this->get('/warga/laporan-keuangan?year=2026&month=2')->assertSee('Laporan periode ini belum tersedia');
 }
 public function test_print_includes_all_pages_and_public_print_uses_snapshot_only(): void {
  $t=$this->actor();$this->opening($t);$descriptions=[];
  for($n=1;$n<=12;$n++){$id=$this->manual($t,'2026-01-'.str_pad((string)$n,2,'0',STR_PAD_LEFT),1000,'income','Internal row '.$n);$descriptions[$id]='Public row '.$n;}
  $this->actingAs($t)->get('/bendahara/kas-laporan?year=2026&month=1&page=2')->assertViewHas('rows',fn($p)=>$p->total()===12&&$p->count()===2);
  $print=$this->get('/bendahara/kas-laporan/cetak?year=2026&month=1&page=2')->assertOk()->assertViewHas('rows',fn($r)=>count($r)===12)->assertSee('Internal row 1')->assertSee('Internal row 12')->assertSee('Rp12.000')->assertDontSee('/bukti/');
  $a=$this->actor(Role::Admin);$report=$this->publish($a,$this->preview($a,2026,1,$descriptions));$this->manual($t,'2026-01-13',7000);
  $this->actingAs($this->actor(Role::Resident))->get('/warga/laporan-keuangan/cetak?year=2026&month=1')->assertOk()->assertViewHas('rows',fn($r)=>count($r)===12)->assertViewHas('summary',$report->summary)->assertDontSee('Internal row')->assertSee('Public row 12')->assertDontSee('/bukti/');
 }
 public function test_permissions_empty_period_and_pending_resident_are_enforced_server_side(): void {
  $t=$this->actor();$a=$this->actor(Role::Admin);$u=$this->actor(Role::Resident);$rt=Rt::firstOrCreate(['number'=>1],['is_active'=>true]);$officer=$this->actor(Role::Rt,['rt_id'=>$rt->id]);
  foreach([$u,$officer] as $wrong){$this->actingAs($wrong)->get('/bendahara/kas-laporan')->assertForbidden();$this->post('/bendahara/kas-laporan/transaksi',[])->assertForbidden();$this->post('/bendahara/kas-laporan/saldo-pembukaan',[])->assertForbidden();$this->get('/admin/laporan')->assertForbidden();$this->post('/admin/laporan/terbitkan',[])->assertForbidden();}
  $this->actingAs($a)->get('/bendahara/kas-laporan')->assertForbidden();$this->actingAs($t)->get('/admin/laporan')->assertForbidden();
  $this->opening($t);$this->actingAs($a)->post('/admin/laporan/tinjau?year=2026&month=1',['privacy_reviewed'=>1])->assertSessionHasErrors('publication');
  $this->actingAs($u)->get('/warga/laporan-keuangan/cetak?year=2026&month=1')->assertNotFound();
  $pending=$this->actor(Role::Resident,['registration_status'=>'pending']);$this->actingAs($pending)->get('/warga/laporan-keuangan')->assertRedirect(route('registration.status'));
  $this->get('/warga/laporan-keuangan/cetak')->assertRedirect(route('registration.status'));
 }
 public function test_new_accepted_income_before_start_is_rejected_without_mutating_payment(): void {
  $t=$this->actor();$this->opening($t,'2026-01-01');$p=$this->payment($t,'2025-12-31','pending');$original=$p->getAttributes();
  $this->actingAs($t)->post(route('treasurer.payments.decide',$p),['decision'=>'accepted','confirmed'=>1])->assertSessionHasErrors('decision');
  $this->assertSame($original,$p->fresh()->getAttributes());$this->assertDatabaseCount('dues_income_entries',0);
 }

 public function test_prior_period_cash_changes_opening_and_invalidates_preview_status_filters_and_expiry(): void {
  $t=$this->actor();$this->opening($t);$id=$this->manual($t);$a=$this->actor(Role::Admin);$first=$this->publish($a,$this->preview($a,2026,1,[$id=>'Donasi umum']));
  $this->actingAs($a)->get('/admin/laporan?year=2026&month=1&publication_status=published')->assertViewHas('matches',true);
  $this->get('/admin/laporan?year=2026&month=1&publication_status=unpublished')->assertViewHas('matches',false);
  $token=$this->preview($a,2026,1,[$id=>'Donasi umum']);$this->manual($t,'2025-12-31',7000);
  $this->actingAs($a)->post('/admin/laporan/terbitkan',['token'=>$token,'confirmed'=>1])->assertSessionHasErrors('publication');
  $this->get('/admin/laporan?year=2026&month=1&publication_status=stale')->assertViewHas('matches',true);
  $this->assertSame($first->summary['opening']+7000,$this->statement()['summary']['opening']);
  $expired=$this->preview($a,2026,1,[$id=>'Donasi umum']);$this->travel(21)->minutes();
  $this->post('/admin/laporan/terbitkan',['token'=>$expired,'confirmed'=>1])->assertSessionHasErrors('publication');$this->assertDatabaseCount('financial_reports',1);
 }
 public function test_preview_cannot_be_published_by_another_admin_and_identical_versions_are_blocked(): void {
  $t=$this->actor();$this->opening($t);$id=$this->manual($t);$a=$this->actor(Role::Admin);$b=$this->actor(Role::Admin);$token=$this->preview($a,2026,1,[$id=>'Aman']);
  $this->actingAs($b)->post('/admin/laporan/terbitkan',['token'=>$token,'confirmed'=>1])->assertSessionHasErrors('publication');
  $this->publish($a,$token);$otherToken=$this->preview($a,2026,1,[$id=>'Aman']);
  $this->post('/admin/laporan/terbitkan',['token'=>$otherToken,'confirmed'=>1])->assertSessionHasErrors('publication');$this->assertDatabaseCount('financial_reports',1);
 }
 public function test_missing_manual_proof_and_pdf_upload_are_protected(): void {
  $t=$this->actor();$this->opening($t);$id=$this->manual($t);$before=DB::table('cash_transactions')->where('id',$id)->first();$proof=DB::table('cash_proofs')->where('source_id',$id)->first();Storage::disk('cash_proofs')->delete($proof->path);
  $this->assertFalse($this->statement()['ready']);$this->actingAs($t)->get('/bendahara/kas-laporan/bukti/manual/'.$id)->assertNotFound();
  $pdf=UploadedFile::fake()->createWithContent('receipt.pdf',"%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF");
  $this->post('/bendahara/kas-laporan/lengkapi-bukti',['source'=>'manual:'.$id,'proof'=>$pdf])->assertRedirect()->assertSessionHasNoErrors();
  $r=$this->get('/bendahara/kas-laporan/bukti/manual/'.$id)->assertOk()->assertHeader('Content-Type','application/pdf');$this->assertStringStartsWith('attachment;',$r->headers->get('Content-Disposition'));
  $this->assertEquals($before,DB::table('cash_transactions')->where('id',$id)->first());$this->assertDatabaseCount('cash_proofs',2);
  $this->post('/bendahara/kas-laporan/lengkapi-bukti',['source'=>'manual:999999','proof'=>$this->png()])->assertNotFound();
 }

 public function test_saving_backdated_transaction_opens_its_period_and_page(): void {
  $t=$this->actor();$this->opening($t);for($n=0;$n<11;$n++)$this->manual($t,'2026-01-20',1000);
  $key=$this->actingAs($t)->get('/bendahara/kas-laporan?year=2026&month=2')->viewData('requestKey');
  $response=$this->post('/bendahara/kas-laporan/transaksi?year=2026&month=2',['request_key'=>$key,'kind'=>'expense','transaction_date'=>'2026-01-01','description'=>'Visible backdated expense','amount'=>5000,'proof'=>$this->png()])->assertRedirect(route('treasurer.reports',['year'=>2026,'month'=>1,'page'=>2]));
  $this->get($response->headers->get('Location'))->assertSee('Visible backdated expense')->assertViewHas('statement',fn($s)=>$s['summary']['income']===11000&&$s['summary']['expense']===5000);
 }
}
