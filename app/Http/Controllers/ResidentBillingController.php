<?php
namespace App\Http\Controllers;
use App\Enums\Role;
use App\Models\{Invoice,PaymentSubmission,PaymentAccount};
use App\Services\DuesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\{Rule,ValidationException};

class ResidentBillingController extends Controller {
 private function owner(Request $request,Invoice $invoice): void {abort_unless($invoice->user_id===$request->user()->id,403);}
 public function dashboard(Request $request,DuesService $service){
  $filters=$request->validate(['year'=>'nullable|integer|between:2000,2100','month'=>'nullable|integer|between:1,12']);
  $year=(int)($filters['year']??now('Asia/Jakarta')->year);$month=isset($filters['month'])?(int)$filters['month']:null;
  $query=Invoice::where('user_id',$request->user()->id)->whereHas('period',fn($q)=>$q->where('year',$year)->when($month,fn($q)=>$q->where('month',$month)));
  $summary=$service->summary($query);$invoices=(clone $query)->with(['period','latestSubmission'])->latest('id')->limit(3)->get();
  return view('billing.resident-dashboard',compact('year','month','summary','invoices'));
 }
 public function index(Request $request,DuesService $service){
  $filters=$request->validate(['year'=>'nullable|integer|between:2000,2100','month'=>'nullable|integer|between:1,12','status'=>'nullable|in:all,unpaid,paid']);
  $year=(int)($filters['year']??now('Asia/Jakarta')->year);$month=isset($filters['month'])?(int)$filters['month']:null;$status=$filters['status']??'all';
  $query=Invoice::where('user_id',$request->user()->id)->whereHas('period',fn($q)=>$q->where('year',$year)->when($month,fn($q)=>$q->where('month',$month)));
  $summary=$service->summary($query);
  $invoices=$query->when($status!=='all',fn($q)=>$q->where('status',$status))->with(['period','latestSubmission'])->latest('id')->paginate(10)->withQueryString();
  return view('billing.resident-invoices',compact('invoices','summary','year','month','status'));
 }
 public function pay(Request $request,Invoice $invoice){
  $this->owner($request,$invoice);$invoice->load(['period','latestSubmission']);
  if($invoice->status==='paid'||$invoice->latestSubmission)return redirect()->route('resident.payments.status',$invoice);
  $account=PaymentAccount::find(1);return view('billing.pay',compact('invoice','account'));
 }
 public function submit(Request $request,Invoice $invoice){
  $this->owner($request,$invoice);
  $data=$request->validate(['paid_on'=>'required|date_format:Y-m-d|before_or_equal:'.now('Asia/Jakarta')->toDateString(),'amount'=>['required','integer',Rule::in([$invoice->amount])],'proof'=>'required|file|mimes:jpg,jpeg,png,pdf|mimetypes:image/jpeg,image/png,application/pdf|max:5120'],['amount.in'=>'Nominal transfer harus sama dengan nominal tagihan.','proof.max'=>'Ukuran bukti maksimal 5 MB.','paid_on.before_or_equal'=>'Tanggal pembayaran tidak boleh melebihi hari ini.']);
  $file=$request->file('proof');$mime=$file->getMimeType();
  if(($mime==='application/pdf'&&file_get_contents($file->getRealPath(),false,null,0,5)!=='%PDF-')||($mime!=='application/pdf'&&@getimagesize($file->getRealPath())===false))throw ValidationException::withMessages(['proof'=>'Isi berkas bukan gambar atau PDF yang valid.']);
  $stored=null;
  try{DB::transaction(function()use($request,$invoice,$data,$file,$mime,&$stored){
   $locked=Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();$this->owner($request,$locked);
   if($locked->status!=='unpaid'||$locked->submissions()->where('status','pending')->exists())throw ValidationException::withMessages(['proof'=>'Tagihan sudah lunas atau ada bukti yang masih menunggu verifikasi.']);
   $account=PaymentAccount::whereKey(1)->sharedLock()->first();
   if(!$account)throw ValidationException::withMessages(['account'=>'Rekening tujuan belum tersedia. Hubungi Bendahara; bukti belum dikirim.']);
   if($stored===null)$stored=$file->store('proofs','payment_proofs');
   PaymentSubmission::create(['invoice_id'=>$locked->id,'user_id'=>$request->user()->id,'paid_on'=>$data['paid_on'],'amount'=>$locked->amount,'proof_path'=>$stored,'mime'=>$mime,'file_size'=>$file->getSize(),'bank_name'=>$account->bank_name,'account_number'=>$account->account_number,'account_holder'=>$account->account_holder,'status'=>'pending','pending_invoice_id'=>$locked->id]);
  },3);}catch(\Throwable $e){if($stored)Storage::disk('payment_proofs')->delete($stored);throw $e;}
  return redirect()->route('resident.payments.status',$invoice)->with('success','Bukti pembayaran berhasil diajukan. Tagihan tetap Belum Lunas sampai diverifikasi Bendahara.');
 }
 public function status(Request $request,Invoice $invoice){
  $this->owner($request,$invoice);$invoice->load(['period','submissions.reviewer','latestSubmission']);
  if(!$invoice->latestSubmission)return redirect()->route('resident.invoices.pay',$invoice);
  $payment=$invoice->latestSubmission;$account=PaymentAccount::find(1);return view('billing.status',compact('invoice','payment','account'));
 }
 public function proof(Request $request,PaymentSubmission $payment){
  abort_unless($request->user()->role===Role::Treasurer||($request->user()->role===Role::Resident&&$payment->user_id===$request->user()->id&&$payment->invoice->user_id===$request->user()->id),403);
  $disk=Storage::disk('payment_proofs');abort_unless($disk->exists($payment->proof_path),404);
  $extension=match($payment->mime){'image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf',default=>abort(404)};
  $headers=['Content-Type'=>$payment->mime,'X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store','Content-Security-Policy'=>"default-src 'none'; sandbox"];
  return response()->file($disk->path($payment->proof_path),$headers+['Content-Disposition'=>($extension==='pdf'?'attachment':'inline').'; filename="bukti-'.$payment->id.'.'.$extension.'"']);
 }
}
