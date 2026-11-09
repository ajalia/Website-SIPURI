<?php
namespace App\Services;
use App\Models\{House,HouseResidency,DuesPeriod,Invoice,PaymentSubmission};
use App\Support\HouseAddress;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class DuesService {
 public function targets() {
  $houses=House::with(['resident','rt'])->whereNotNull('current_user_id')->whereHas('resident',fn($q)=>$q->where('role','warga')->where('registration_status','approved'))->orderBy('id')->get();
  $seen=[];$targets=collect();
  foreach($houses as $house){
   $block=HouseAddress::block($house->block);$number=HouseAddress::number($house->house_number);$key=$block.'|'.$number;
   if(!HouseAddress::valid($block,$number)||isset($seen[$key]))throw ValidationException::withMessages(['targets'=>'Ada alamat rumah aktif tidak valid atau ganda di RW 07. Minta pengurus RT memperbaikinya sebelum menerbitkan tagihan.']);
   $seen[$key]=true;
   $records=HouseResidency::where('house_id',$house->id)->where('user_id',$house->current_user_id)->whereNull('moved_at')->get();
   if($records->count()!==1)throw ValidationException::withMessages(['targets'=>'Hubungan rumah dan penanggung jawab perlu diperiksa oleh pengurus RT.']);
   $targets->push(['house'=>$house,'residency'=>$records->first(),'block'=>$block,'house_number'=>$number]);
  }
  return $targets;
 }
 public function issue(array $data,int $actor,?string $expectedFingerprint=null): DuesPeriod {
  return AddressGuard::transaction(function()use($data,$actor,$expectedFingerprint){
   if(DuesPeriod::where('year',$data['year'])->where('month',$data['month'])->exists())throw ValidationException::withMessages(['month'=>'Tagihan periode ini sudah diterbitkan. Tidak ada tagihan tambahan dibuat.']);
   $targets=$this->targets();
   if($targets->isEmpty())throw ValidationException::withMessages(['targets'=>'Belum ada rumah masih tinggal dengan akun disetujui RT.']);
   if($expectedFingerprint!==null&&!hash_equals($expectedFingerprint,$this->fingerprint($targets)))throw ValidationException::withMessages(['targets'=>'Sasaran berubah sejak ditinjau. Tinjau kembali sebelum menerbitkan.']);
   $period=DuesPeriod::create($data+['target_count'=>$targets->count(),'issued_by'=>$actor,'issued_at'=>now()]);
   foreach($targets as $target){$h=$target['house'];Invoice::create(['dues_period_id'=>$period->id,'house_id'=>$h->id,'house_residency_id'=>$target['residency']->id,'user_id'=>$h->current_user_id,'resident_name'=>$h->resident->name,'block'=>$target['block'],'house_number'=>$target['house_number'],'rt_number'=>$h->rt->number,'amount'=>$period->amount,'due_date'=>$period->due_date,'status'=>'unpaid']);}
   return $period;
  });
 }
 public function fingerprint($targets): string {return hash('sha256',json_encode($targets->map(fn($t)=>[$t['house']->id,$t['house']->current_user_id,$t['residency']->id,$t['block'],$t['house_number']])->values()->all(),JSON_THROW_ON_ERROR));}
 public function decide(PaymentSubmission $submission,string $decision,int $actor,?string $reason): void {
  DB::transaction(function()use($submission,$decision,$actor,$reason){
   $book=DB::table('cash_books')->where('id',1)->lockForUpdate()->first();
   $invoice=Invoice::whereKey($submission->invoice_id)->lockForUpdate()->firstOrFail();
   $current=PaymentSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
   if($invoice->status!=='unpaid'||$current->status!=='pending'||(int)$invoice->submissions()->max('id')!==$current->id)throw ValidationException::withMessages(['decision'=>'Pengajuan sudah diproses atau bukan pengajuan terkini. Muat ulang halaman.']);
   $current->status=$decision;$current->pending_invoice_id=null;$current->reviewed_by=$actor;$current->reviewed_at=now();
   if($decision==='accepted'){
    if($book?->starts_on&&$current->paid_on->toDateString()<$book->starts_on)throw ValidationException::withMessages(['decision'=>'Tanggal pembayaran sebelum tanggal mulai pembukuan. Minta pengurus memeriksa tanggal/saldo pembukaan; pembayaran tidak diubah otomatis.']);
    if($current->amount!==$invoice->amount)throw ValidationException::withMessages(['decision'=>'Nominal tidak sesuai tagihan.']);
    $current->accepted_invoice_id=$invoice->id;
    $invoice->status='paid';$invoice->settled_at=now();$invoice->save();
    DB::table('dues_income_entries')->insert(['invoice_id'=>$invoice->id,'payment_submission_id'=>$current->id,'amount'=>$invoice->amount,'transaction_date'=>$current->paid_on->toDateString(),'recorded_by'=>$actor,'recorded_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
   }else{$current->rejection_reason=$reason;}
   $current->save();
  },3);
 }
 public function summary($query): array {
  // A single SQL snapshot prevents status transitions from being counted in two groups.
  $pending="EXISTS (SELECT 1 FROM payment_submissions ps WHERE ps.invoice_id=invoices.id AND ps.status='pending')";
  $row=(clone $query)->selectRaw("COUNT(*) AS bill_count, COALESCE(SUM(invoices.amount),0) AS bill_amount,
   COALESCE(SUM(CASE WHEN invoices.status='paid' THEN 1 ELSE 0 END),0) AS paid_count,
   COALESCE(SUM(CASE WHEN invoices.status='paid' THEN invoices.amount ELSE 0 END),0) AS paid_amount,
   COALESCE(SUM(CASE WHEN invoices.status='unpaid' AND $pending THEN 1 ELSE 0 END),0) AS pending_count,
   COALESCE(SUM(CASE WHEN invoices.status='unpaid' AND $pending THEN invoices.amount ELSE 0 END),0) AS pending_amount")->toBase()->first();
  return ['count'=>(int)$row->bill_count,'amount'=>(int)$row->bill_amount,
   'paid'=>['count'=>(int)$row->paid_count,'amount'=>(int)$row->paid_amount],
   'pending'=>['count'=>(int)$row->pending_count,'amount'=>(int)$row->pending_amount],
   'followup'=>['count'=>(int)$row->bill_count-(int)$row->paid_count-(int)$row->pending_count,'amount'=>(int)$row->bill_amount-(int)$row->paid_amount-(int)$row->pending_amount]];
 }
}
