<?php
namespace App\Http\Controllers;
use App\Models\{Invoice,PaymentSubmission,PaymentAccount};
use App\Services\{DuesService,AddressGuard};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TreasurerBillingController extends Controller {
 private function filters(Request $request,bool $allowAll=false): array {
  $f=$request->validate(['year'=>'nullable|integer|between:2000,2100','month'=>'nullable|integer|between:1,12','q'=>'nullable|string|max:100','status'=>'nullable|in:all,unpaid,paid,pending,rejected,accepted']);
  return ['year'=>$allowAll&&$request->exists('year')?(isset($f['year'])?(int)$f['year']:null):(int)($f['year']??now('Asia/Jakarta')->year),'month'=>$allowAll&&$request->exists('month')?(isset($f['month'])?(int)$f['month']:null):(int)($f['month']??now('Asia/Jakarta')->month),'q'=>$f['q']??'','status'=>$f['status']??'all'];
 }
 private function invoices(array $f){return Invoice::whereHas('period',fn($q)=>$q->when($f['year']!==null,fn($p)=>$p->where('year',$f['year']))->when($f['month']!==null,fn($p)=>$p->where('month',$f['month'])))->when($f['q']!=='',fn($q)=>$q->where(fn($s)=>$s->where('resident_name','like','%'.$f['q'].'%')->orWhere('block','like','%'.$f['q'].'%')->orWhere('house_number','like','%'.$f['q'].'%')));}
 public function dashboard(Request $request,DuesService $service){
  $filters=$this->filters($request);$query=$this->invoices($filters);
  [$summary,$pending]=DB::transaction(function()use($service,$query){
   return [$service->summary($query),PaymentSubmission::where('status','pending')->whereHas('invoice',fn($q)=>$q->whereIn('id',(clone $query)->select('id')))->with('invoice.period')->latest()->limit(5)->get()];
  });
  return view('billing.treasurer-dashboard',compact('filters','summary','pending'));
 }
 public function index(Request $request){
  $filters=$this->filters($request);$query=$this->invoices($filters);
  if(!in_array($filters['status'],['all','unpaid','paid'],true))throw ValidationException::withMessages(['status'=>'Pilih status tagihan yang tersedia.']);
  $invoices=$query->when($filters['status']!=='all',fn($q)=>$q->where('status',$filters['status']))->with(['period','latestSubmission'])->latest('id')->paginate(10)->withQueryString();
  return view('billing.treasurer-invoices',compact('invoices','filters'));
 }
 public function create(){return view('billing.create-period',['preview'=>null,'token'=>null]);}
 private function periodData(Request $request): array {return $request->validate(['month'=>'required|integer|between:1,12','year'=>'required|integer|between:2000,2100','amount'=>'required|integer|min:1|max:1000000000','due_date'=>'required|date_format:Y-m-d']);}
 public function preview(Request $request,DuesService $service){
  $data=$this->periodData($request);$targets=$service->targets();
  if($targets->isEmpty())throw ValidationException::withMessages(['targets'=>'Belum ada rumah masih tinggal dengan akun disetujui RT.']);
  $token=(string)Str::uuid();$preview=['data'=>$data,'count'=>$targets->count(),'fingerprint'=>$service->fingerprint($targets),'expires'=>now()->addMinutes(20)->timestamp];
  $request->session()->put('dues_preview.'.$token,$preview);
  return view('billing.create-period',compact('preview','token'));
 }
 public function issue(Request $request,DuesService $service){
  $request->validate(['token'=>'required|uuid','confirmed'=>'accepted']);$preview=$request->session()->get('dues_preview.'.$request->token);
  if($preview)$request->merge($preview['data']);
  if(!$preview||$preview['expires']<now()->timestamp)throw ValidationException::withMessages(['preview'=>'Tinjauan kedaluwarsa atau sudah digunakan. Silakan tinjau ulang.'])->redirectTo(route('treasurer.periods.create'));
  try{$period=$service->issue($preview['data'],$request->user()->id,$preview['fingerprint']);}catch(ValidationException $e){$e->redirectTo(route('treasurer.periods.create'));throw $e;}
  $request->session()->forget('dues_preview.'.$request->token);
  return redirect()->route('treasurer.invoices.index',['month'=>$period->month,'year'=>$period->year])->with('success','Tagihan periode '.$period->label.' diterbitkan untuk '.$period->target_count.' rumah. Belum ada pemasukan kas dari penerbitan ini.');
 }
 public function submissions(Request $request){
  $filters=$this->filters($request,true);if(!$request->has('status'))$filters['status']='pending';
  if(!in_array($filters['status'],['all','pending','rejected','accepted'],true))throw ValidationException::withMessages(['status'=>'Pilih status pengajuan yang tersedia.']);
  $navigationFilters=array_map(fn($value)=>$value??'', $filters)+$request->only('page');
  $query=$this->invoices($filters);
  $pendingCount=PaymentSubmission::where('status','pending')->whereIn('invoice_id',(clone $query)->select('id'))->count();
  $payments=PaymentSubmission::whereIn('invoice_id',$query->select('id'))->when($filters['status']!=='all',fn($q)=>$q->where('status',$filters['status']))->with('invoice.period')->latest('id')->paginate(10)->appends($navigationFilters);
  $years=\App\Models\DuesPeriod::query()->distinct()->orderByDesc('year')->pluck('year');
  return view('billing.submissions',compact('payments','filters','pendingCount','years','navigationFilters'));
 }
 public function review(Request $request,PaymentSubmission $payment){$this->filters($request,true);$backFilters=array_map(fn($value)=>$value??'', $request->only(['month','year','status','q','page']));$payment->load(['invoice.period','reviewer']);return view('billing.review',compact('payment','backFilters'));}
 public function decide(Request $request,PaymentSubmission $payment,DuesService $service){
  $data=$request->validate(['decision'=>'required|in:accepted,rejected','confirmed'=>'required_if:decision,accepted|accepted_if:decision,accepted','reason'=>'nullable|required_if:decision,rejected|string|max:2000']);
  if($data['decision']==='rejected'&&trim($data['reason']??'')==='')throw ValidationException::withMessages(['reason'=>'Alasan penolakan wajib diisi.']);
  $service->decide($payment,$data['decision'],$request->user()->id,isset($data['reason'])?trim($data['reason']):null);
  return redirect()->route('treasurer.payments.review',array_merge(['payment'=>$payment->id],array_map(fn($value)=>$value??'', $request->only(['month','year','status','q','page']))))->with('success',$data['decision']==='accepted'?'Pembayaran diterima; tagihan lunas dan satu pemasukan iuran tercatat.':'Pengajuan ditolak. Alasan dapat dibaca Warga; tagihan tetap Belum Lunas.');
 }
 public function account(){return view('billing.account',['account'=>PaymentAccount::find(1)]);}
 public function saveAccount(Request $request){
  $data=$request->validate(['bank_name'=>'required|string|max:100','account_number'=>'required|string|max:50|regex:/\A[0-9]+\z/','account_holder'=>'required|string|max:150']);
  AddressGuard::transaction(fn()=>PaymentAccount::updateOrCreate(['id'=>1],$data+['updated_by'=>$request->user()->id]));
  return redirect()->route('treasurer.account')->with('success','Rekening tujuan berhasil disimpan. Pengajuan lama tetap menyimpan rekening pada saat bukti diajukan.');
 }
}
