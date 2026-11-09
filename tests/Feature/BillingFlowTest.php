<?php
namespace Tests\Feature;
use App\Enums\Role;
use App\Models\{User,Rt,House,HouseResidency,DuesPeriod,Invoice,PaymentSubmission,PaymentAccount};
use App\Services\DuesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Storage};
use Tests\TestCase;

class BillingFlowTest extends TestCase {
 use RefreshDatabase;
 private function treasurer(): User {return User::factory()->create(['role'=>Role::Treasurer,'registration_status'=>'approved']);}
 private function occupant(string $block='B3',string $number='14',int $rtNumber=1): User {
  $rt=Rt::firstOrCreate(['number'=>$rtNumber],['is_active'=>true]);
  $user=User::factory()->create(['role'=>Role::Resident,'registration_status'=>'approved','rt_id'=>$rt->id,'block'=>$block,'house_number'=>$number]);
  $house=House::create(['rt_id'=>$rt->id,'block'=>$block,'house_number'=>$number,'current_user_id'=>$user->id]);
  HouseResidency::create(['house_id'=>$house->id,'user_id'=>$user->id,'resident_name'=>$user->name,'block'=>$block,'house_number'=>$number,'started_at'=>now()->subYear()]);return $user;
 }
 private function period(User $treasurer,int $month=1,int $amount=125000): DuesPeriod {return app(DuesService::class)->issue(['month'=>$month,'year'=>2026,'amount'=>$amount,'due_date'=>'2026-'.str_pad((string)$month,2,'0',STR_PAD_LEFT).'-10'],$treasurer->id);}
 private function account(User $treasurer): void {PaymentAccount::create(['id'=>1,'bank_name'=>'Bank Uji Lokal','account_number'=>'0001234567','account_holder'=>'Pengurus Uji','updated_by'=>$treasurer->id]);}
 private function proof(string $name='transfer.png'): UploadedFile {return UploadedFile::fake()->createWithContent($name,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jN1sAAAAASUVORK5CYII='));}
 private function submit(User $user,Invoice $invoice,array $extra=[]): PaymentSubmission {
  $this->actingAs($user)->from(route('resident.invoices.pay',$invoice))->post(route('resident.payments.submit',$invoice),array_merge(['paid_on'=>'2026-01-15','amount'=>$invoice->amount,'proof'=>$this->proof()],$extra))->assertRedirect(route('resident.payments.status',$invoice))->assertSessionHasNoErrors();
  return $invoice->submissions()->latest('id')->firstOrFail();
 }

 public function test_manual_preview_and_issuance_snapshot_targets_and_duplicate_periods(): void {
  $treasurer=$this->treasurer();$a=$this->occupant();$b=$this->occupant('F12','2',2);
  User::factory()->create(['role'=>Role::Resident,'registration_status'=>'pending']);
  $this->actingAs($treasurer)->post(route('treasurer.periods.preview'),['month'=>1,'year'=>2026,'amount'=>125000,'due_date'=>'2026-01-10'])->assertOk()->assertSee('2 Rumah')->assertSee('Rp250.000');
  $previews=session('dues_preview');$token=array_key_first($previews);
  $this->post(route('treasurer.periods.issue'),['token'=>$token,'confirmed'=>1])->assertSessionHasNoErrors()->assertRedirect();
  $this->assertDatabaseCount('invoices',2);$this->assertDatabaseCount('dues_income_entries',0);
  $this->post(route('treasurer.periods.issue'),['token'=>$token,'confirmed'=>1])->assertSessionHasErrors('preview');
  $this->post(route('treasurer.periods.preview'),['month'=>1,'year'=>2026,'amount'=>140000,'due_date'=>'2026-01-20'])->assertOk();
  $token=array_key_last(session('dues_preview'));$this->post(route('treasurer.periods.issue'),['token'=>$token,'confirmed'=>1])->assertSessionHasErrors('month');
  $this->assertDatabaseCount('dues_periods',1);$this->assertDatabaseCount('invoices',2);
  $this->period($treasurer,2,140000);
  $this->assertSame(125000,Invoice::whereHas('period',fn($q)=>$q->where('month',1))->first()->amount);
  $this->assertSame('2026-01-10',Invoice::first()->due_date->toDateString());
 }
 public function test_newly_approved_and_new_occupant_never_inherit_or_backfill_old_invoices(): void {
  $treasurer=$this->treasurer();$first=$this->occupant();$period=$this->period($treasurer);$invoice=$period->invoices()->first();
  $residency=HouseResidency::where('user_id',$first->id)->first();$residency->moved_at=now()->toDateString();$residency->move_note='Pindah';$residency->save();$house=$residency->house;$house->current_user_id=null;$house->save();
  $new=User::factory()->create(['registration_status'=>'approved','rt_id'=>$first->rt_id,'block'=>'B3','house_number'=>'14']);$house->current_user_id=$new->id;$house->save();
  HouseResidency::create(['house_id'=>$house->id,'user_id'=>$new->id,'resident_name'=>$new->name,'block'=>'B3','house_number'=>'14','started_at'=>now()]);
  $this->assertSame($first->id,$invoice->fresh()->user_id);
  $this->actingAs($new)->get(route('resident.invoices.pay',$invoice))->assertForbidden();
  $this->actingAs($first)->get(route('resident.invoices.pay',$invoice))->assertOk();
  $next=$this->period($treasurer,2);$this->assertSame($new->id,$next->invoices()->first()->user_id);
  $this->assertSame(1,Invoice::where('user_id',$first->id)->count());$this->assertSame(1,Invoice::where('user_id',$new->id)->count());
 }
 public function test_missing_account_blocks_submission_and_only_treasurer_can_configure_it(): void {
  Storage::fake('payment_proofs');$treasurer=$this->treasurer();$user=$this->occupant();$invoice=$this->period($treasurer)->invoices()->first();
  $this->actingAs($user)->get(route('resident.invoices.pay',$invoice))->assertSee('Rekening tujuan belum tersedia')->assertSee('disabled',false);
  $this->post(route('resident.payments.submit',$invoice),['paid_on'=>'2026-01-15','amount'=>125000,'proof'=>$this->proof()])->assertSessionHasErrors('account');
  $this->assertDatabaseCount('payment_submissions',0);$this->assertCount(0,Storage::disk('payment_proofs')->allFiles());
  $data=['bank_name'=>'Bank Uji','account_number'=>'001234','account_holder'=>'Pengurus Uji'];
  $this->put(route('treasurer.account.save'),$data)->assertForbidden();
  $this->actingAs($treasurer)->put(route('treasurer.account.save'),$data)->assertSessionHasNoErrors();
  $this->assertSame('001234',PaymentAccount::find(1)->account_number);
 }
 public function test_upload_pending_private_owner_access_and_double_submission(): void {
  Storage::fake('payment_proofs');$treasurer=$this->treasurer();$user=$this->occupant();$stranger=$this->occupant('C2','10');$this->account($treasurer);$invoice=$this->period($treasurer)->invoices()->where('user_id',$user->id)->first();
  $payment=$this->submit($user,$invoice);Storage::disk('payment_proofs')->assertExists($payment->proof_path);
  $this->assertStringNotContainsString('transfer.png',$payment->proof_path);$this->assertFalse(config('filesystems.disks.payment_proofs.serve'));
  $this->assertSame('unpaid',$invoice->fresh()->status);$this->assertDatabaseCount('dues_income_entries',0);
  $this->get(route('payments.proof',$payment))->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
  $this->post(route('resident.payments.submit',$invoice),['paid_on'=>'2026-01-15','amount'=>125000,'proof'=>$this->proof()])->assertSessionHasErrors('proof');
  $this->assertDatabaseCount('payment_submissions',1);$this->assertCount(1,Storage::disk('payment_proofs')->allFiles());
  $this->actingAs($stranger)->get(route('payments.proof',$payment))->assertForbidden();
  $this->get(route('resident.payments.status',$invoice))->assertForbidden();
  $this->post(route('resident.payments.submit',$invoice),[])->assertForbidden();
  $this->actingAs($treasurer)->get(route('payments.proof',$payment))->assertOk();
  $officer=User::factory()->create(['role'=>Role::Rt,'rt_id'=>$user->rt_id,'registration_status'=>'approved']);
  $this->actingAs($officer)->get(route('payments.proof',$payment))->assertForbidden();
 }
 public function test_upload_validation_rejects_wrong_amount_future_date_html_and_oversize(): void {
  Storage::fake('payment_proofs');$t=$this->treasurer();$u=$this->occupant();$this->account($t);$i=$this->period($t)->invoices()->first();$this->actingAs($u);
  $base=['paid_on'=>'2026-01-15','amount'=>125000,'proof'=>$this->proof()];
  $this->post(route('resident.payments.submit',$i),array_merge($base,['amount'=>124999]))->assertSessionHasErrors('amount');
  $this->post(route('resident.payments.submit',$i),array_merge($base,['paid_on'=>now('Asia/Jakarta')->addDay()->toDateString()]))->assertSessionHasErrors('paid_on');
  $this->post(route('resident.payments.submit',$i),array_merge($base,['proof'=>UploadedFile::fake()->createWithContent('fake.png','<html><script>alert(1)</script></html>')]))->assertSessionHasErrors('proof');
  $this->post(route('resident.payments.submit',$i),array_merge($base,['proof'=>UploadedFile::fake()->create('big.pdf',5121,'application/pdf')]))->assertSessionHasErrors('proof');
  $this->assertDatabaseCount('payment_submissions',0);$this->assertCount(0,Storage::disk('payment_proofs')->allFiles());
 }
 public function test_pdf_is_private_and_returned_as_download_not_html(): void {
  Storage::fake('payment_proofs');$t=$this->treasurer();$u=$this->occupant();$this->account($t);$i=$this->period($t)->invoices()->first();
  $pdf=UploadedFile::fake()->createWithContent('proof.pdf',"%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF");
  $p=$this->submit($u,$i,['proof'=>$pdf]);$response=$this->get(route('payments.proof',$p))->assertOk()->assertHeader('Content-Type','application/pdf');
  $this->assertStringStartsWith('attachment;',$response->headers->get('Content-Disposition'));
 }
 public function test_rejection_replacement_and_acceptance_keep_history_and_record_one_income(): void {
  Storage::fake('payment_proofs');$t=$this->treasurer();$u=$this->occupant();$this->account($t);$i=$this->period($t)->invoices()->first();$old=$this->submit($u,$i);
  $this->actingAs($t)->post(route('treasurer.payments.decide',$old),['decision'=>'rejected','reason'=>' '])->assertSessionHasErrors('reason');
  $this->post(route('treasurer.payments.decide',$old),['decision'=>'rejected','reason'=>'Bukti kurang jelas.'])->assertSessionHasNoErrors();
  $this->assertSame('unpaid',$i->fresh()->status);$this->assertDatabaseCount('dues_income_entries',0);
  $this->actingAs($u)->get(route('resident.payments.status',$i))->assertSee('Bukti kurang jelas.')->assertSee('tidak perlu membayar ulang');
  $new=$this->submit($u,$i);Storage::disk('payment_proofs')->assertExists($old->proof_path);Storage::disk('payment_proofs')->assertExists($new->proof_path);
  $this->actingAs($t)->post(route('treasurer.payments.decide',$old),['decision'=>'accepted','confirmed'=>1])->assertSessionHasErrors('decision');
  $this->assertDatabaseCount('dues_income_entries',0);
  $this->post(route('treasurer.payments.decide',$new),['decision'=>'accepted'])->assertSessionHasErrors('confirmed');
  $this->post(route('treasurer.payments.decide',$new),['decision'=>'accepted','confirmed'=>1])->assertSessionHasNoErrors();
  $this->assertSame('paid',$i->fresh()->status);$this->assertSame('accepted',$new->fresh()->status);$this->assertSame('rejected',$old->fresh()->status);
  $entry=DB::table('dues_income_entries')->first();$this->assertSame('2026-01-15',$entry->transaction_date);$this->assertSame($t->id,$entry->recorded_by);
  $this->post(route('treasurer.payments.decide',$new),['decision'=>'accepted','confirmed'=>1])->assertSessionHasErrors('decision');
  $this->assertDatabaseCount('dues_income_entries',1);
  $this->actingAs($u)->get(route('resident.payments.status',$i))->assertSee('Pembayaran Sudah Disetujui')->assertSee('Riwayat Pengajuan Bukti');
 }
 public function test_dashboard_groups_are_disjoint_and_filter_by_invoice_period(): void {
  Storage::fake('payment_proofs');$t=$this->treasurer();$this->account($t);
  $users=collect([$this->occupant('A1','1'),$this->occupant('B2','2'),$this->occupant('C3','3'),$this->occupant('D4','4')]);$period=$this->period($t);$invoices=$period->invoices()->orderBy('id')->get();
  $a=$this->submit($users[0],$invoices[0]);$this->actingAs($t)->post(route('treasurer.payments.decide',$a),['decision'=>'accepted','confirmed'=>1])->assertSessionHasNoErrors();
  $this->submit($users[1],$invoices[1]);$rejected=$this->submit($users[2],$invoices[2]);$this->actingAs($t)->post(route('treasurer.payments.decide',$rejected),['decision'=>'rejected','reason'=>'Bukti tidak jelas'])->assertSessionHasNoErrors();
  $this->get('/bendahara?month=1&year=2026')->assertOk()->assertViewHas('summary',fn($s)=>$s['count']===4&&$s['paid']['count']===1&&$s['pending']['count']===1&&$s['followup']['count']===2&&$s['amount']===500000)->assertViewHas('pending',fn($p)=>$p->count()===1);
  $this->get('/bendahara/pembayaran?month=1&year=2026')->assertOk()->assertViewHas('payments',fn($p)=>$p->total()===1);
  $this->get('/bendahara?month=2&year=2026')->assertViewHas('summary',fn($s)=>$s['count']===0);
  $this->actingAs($users[1])->get('/warga?month=1&year=2026')->assertOk()->assertViewHas('summary',fn($s)=>$s['count']===1&&$s['pending']['count']===1&&$s['amount']===125000);
 }
 public function test_preview_detects_changed_targets_and_legacy_address_collisions(): void {
  $t=$this->treasurer();$this->occupant();
  $this->actingAs($t)->post(route('treasurer.periods.preview'),['month'=>1,'year'=>2026,'amount'=>125000,'due_date'=>'2026-01-10'])->assertOk();$token=array_key_first(session('dues_preview'));
  $this->occupant('F12','2');$this->post(route('treasurer.periods.issue'),['token'=>$token,'confirmed'=>1])->assertSessionHasErrors('targets');$this->assertDatabaseCount('invoices',0);
  $this->occupant('B3','14',2);
  $this->post(route('treasurer.periods.preview'),['month'=>1,'year'=>2026,'amount'=>125000,'due_date'=>'2026-01-10'])->assertSessionHasErrors('targets');$this->assertDatabaseCount('dues_periods',0);
 }
 public function test_all_pages_render_with_real_records_and_wrong_roles_cannot_access(): void {
  Storage::fake('payment_proofs');$t=$this->treasurer();$u=$this->occupant();$this->account($t);$i=$this->period($t)->invoices()->first();
  $this->actingAs($u);foreach(['/warga','/warga/tagihan','/warga/laporan-keuangan',route('resident.invoices.pay',$i)] as $url)$this->get($url)->assertOk();
  $this->get('/bendahara')->assertForbidden();$this->post(route('treasurer.periods.issue'),[])->assertForbidden();
  $p=$this->submit($u,$i);$this->get(route('resident.payments.status',$i))->assertOk();
  $this->post(route('treasurer.payments.decide',$p),['decision'=>'accepted','confirmed'=>1])->assertForbidden();
  $this->actingAs($t);foreach(['/bendahara','/bendahara/tagihan?month=1&year=2026','/bendahara/iuran/buat','/bendahara/pembayaran?month=1&year=2026','/bendahara/rekening','/bendahara/kas-laporan',route('treasurer.payments.review',$p)] as $url)$this->get($url)->assertOk();
  $this->get('/warga/tagihan')->assertForbidden();
 }

 public function test_submission_filters_all_months_years_search_status_empty_and_pagination(): void {
  Storage::fake('payment_proofs');$t=$this->treasurer();$this->account($t);$u=$this->occupant();
  $this->travelTo(\Carbon\Carbon::parse('2026-11-08 12:00:00','Asia/Jakarta'));
  $payments=[];
  foreach([[2025,1],[2025,2],[2026,1],[2026,2]] as [$year,$month]){
   $period=app(DuesService::class)->issue(['month'=>$month,'year'=>$year,'amount'=>125000,'due_date'=>sprintf('%d-%02d-10',$year,$month)],$t->id);
   $payments[]=$this->submit($u,$period->invoices()->first());
  }
  $this->actingAs($t);
  foreach([['','',4],['1','',2],['','2025',2],['2','2026',1],['3','2025',0]] as [$month,$year,$count]){
   $this->get('/bendahara/pembayaran?'.http_build_query(['month'=>$month,'year'=>$year,'status'=>'pending']))->assertOk()->assertViewHas('payments',fn($p)=>$p->total()===$count)->assertViewHas('filters',fn($f)=>$f['month']===($month===''?null:(int)$month)&&$f['year']===($year===''?null:(int)$year));
  }
  $this->get('/bendahara/pembayaran')->assertViewHas('filters',fn($f)=>$f['month']===11&&$f['year']===2026&&$f['status']==='pending');
  $this->get('/bendahara/pembayaran?month=1&year=2025')->assertViewHas('payments',fn($p)=>$p->total()===1);
  $this->get('/bendahara/pembayaran?month=&year=&q=unmatched')->assertViewHas('payments',fn($p)=>$p->total()===0)->assertSee('Tidak Ada Pengajuan');
  $this->get('/bendahara/pembayaran?month=0&year=2025')->assertSessionHasErrors('month');
  $this->get('/bendahara/pembayaran?month=&year=0')->assertSessionHasErrors('year');
  $this->post(route('treasurer.payments.decide',$payments[0]),['decision'=>'rejected','reason'=>'Bukti tidak jelas'])->assertSessionHasNoErrors();
  $this->get('/bendahara/pembayaran?month=&year=&status=rejected')->assertViewHas('payments',fn($p)=>$p->total()===1);
  $this->get('/bendahara/pembayaran?month=&year=&status=pending')->assertViewHas('payments',fn($p)=>$p->total()===3);
  // More than one page, with payment dates deliberately unrelated to invoice periods.
  for($month=3;$month<=12;$month++){
   $this->travel(1)->minutes();$period=$this->period($t,$month);$this->submit($u,$period->invoices()->first());
  }
  $url='/bendahara/pembayaran?month=&year=&status=pending&q='.urlencode($u->name);
  $response=$this->actingAs($t)->get($url)->assertOk()->assertViewHas('payments',fn($p)=>$p->total()===13&&$p->count()===10)->assertViewHas('years',fn($y)=>$y->all()===[2026,2025]);
  $page=$response->viewData('payments');parse_str(parse_url($page->nextPageUrl(),PHP_URL_QUERY),$query);
  $this->assertSame('',$query['month']);$this->assertSame('',$query['year']);$this->assertSame($u->name,$query['q']);$this->assertSame('pending',$query['status']);
  $second=$this->get($page->nextPageUrl())->assertViewHas('payments',fn($p)=>$p->total()===13&&$p->count()===3);
  $payment=$second->viewData('payments')->first();$nav=$second->viewData('navigationFilters');
  $review=$this->get(route('treasurer.payments.review',array_merge(['payment'=>$payment->id],$nav)))->assertOk()->assertViewHas('backFilters',fn($f)=>$f['month']===''&&$f['year']===''&&$f['page']==='2'&&$f['q']===$u->name);
  $saved=$this->post(route('treasurer.payments.decide',array_merge(['payment'=>$payment->id],$nav)),['decision'=>'rejected','reason'=>'Perbaiki bukti'])->assertRedirect();
  parse_str(parse_url($saved->headers->get('Location'),PHP_URL_QUERY),$savedFilters);$this->assertEquals($nav,$savedFilters);
  $response->assertSee(route('treasurer.payments.index',['month'=>'','year'=>'','status'=>'pending']));
  $this->get('/bendahara/pembayaran?month=&year=2027')->assertOk()->assertSee('2027 (belum ada periode)')->assertViewHas('payments',fn($p)=>$p->total()===0);
 }
 public function test_shared_logout_dialog_for_every_role_and_post_csrf_contract(): void {
  $rt=Rt::firstOrCreate(['number'=>1],['is_active'=>true]);
  foreach([Role::Admin,Role::Rt,Role::Treasurer,Role::Resident] as $role){
   $u=User::factory()->create(['role'=>$role,'rt_id'=>$rt->id,'registration_status'=>'approved']);
   $response=$this->actingAs($u)->get(route('account.settings'))->assertOk()->assertSee('Keluar dari SIPURI?')->assertSee('Anda yakin ingin keluar dari akun ini?')->assertSee('Ya, Keluar');
   $this->assertSame(1,substr_count($response->getContent(),'id="logout-confirmation"'));
   $response->assertSee('data-logout-trigger-form',false)->assertSee("data-busy-label=\"Keluar\u{2026}\"",false)->assertSee('name="_token"',false);
  }
  $this->get(route('logout'))->assertStatus(405);
  // Laravel skips CSRF automatically in tests; enable the real check for this request.
  $this->app['env']='local';
  try {
   $this->withSession(['_token'=>'csrf-test-token'])->post(route('logout'))->assertStatus(419);
   $this->assertAuthenticatedAs($u);
   $this->post(route('logout'),['_token'=>'csrf-test-token'])->assertRedirect(route('login'));
   $this->assertGuest();
  } finally {$this->app['env']='testing';}
 }
 public function test_resident_status_markup_preserves_pending_as_unpaid_and_distinct_summary(): void {
  Storage::fake('payment_proofs');$t=$this->treasurer();$u=$this->occupant();$this->account($t);
  $a=$this->period($t,1)->invoices()->first();$b=$this->period($t,2)->invoices()->first();$c=$this->period($t,3)->invoices()->first();
  $this->submit($u,$a);$rejected=$this->submit($u,$b);$accepted=$this->submit($u,$c);
  $this->actingAs($t)->post(route('treasurer.payments.decide',$rejected),['decision'=>'rejected','reason'=>'Tidak jelas']);
  $this->post(route('treasurer.payments.decide',$accepted),['decision'=>'accepted','confirmed'=>1]);
  foreach(['/warga?month=&year=2026','/warga/tagihan?month=&year=2026'] as $url){
   $this->actingAs($u)->get($url)->assertOk()->assertViewHas('summary',fn($s)=>$s['paid']['count']===1&&$s['pending']['count']===1&&$s['followup']['count']===1)->assertSee('finance-stat-paid')->assertSee('finance-stat-pending')->assertSee('finance-stat-followup')->assertSee('Belum Lunas')->assertSee('Menunggu Verifikasi')->assertSee('Bukti Ditolak');
  }
  $this->assertSame('unpaid',$a->fresh()->status);$this->assertSame('unpaid',$b->fresh()->status);
 }
}
