<?php
namespace App\Http\Controllers;
use App\Models\FinancialReport;
use App\Services\{CashBookService,CashEvidenceStorage};
use App\Support\Dues;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CashReportController extends Controller {
 private function period(Request $request): array {
  $f=$request->validate(['year'=>'nullable|integer|between:2000,2100','month'=>'nullable|integer|between:1,12']);
  return ['year'=>(int)($f['year']??now('Asia/Jakarta')->year),'month'=>(int)($f['month']??now('Asia/Jakarta')->month)];
 }
 private function data(Request $request,CashBookService $service): array {
  $filters=$this->period($request);
  return $service->locked(function($book)use($filters,$service){$statement=$service->statement($filters['year'],$filters['month'],$book);$label=Dues::months()[$filters['month']].' '.$filters['year'];return compact('filters','book','statement','label');});
 }
 private function token(Request $request,string $bucket): string {
  $old=$request->old('request_key');
  if($old&&$request->session()->has($bucket.'.'.$old))return $old;
  $token=(string)Str::uuid();$request->session()->put($bucket.'.'.$token,now()->addMinutes(30)->timestamp);return $token;
 }
 public function cash(Request $request,CashBookService $service){
  $data=$this->data($request,$service);$rows=array_reverse($data['statement']['period']);$page=max(1,(int)$request->query('page',1));
  $data['rows']=new LengthAwarePaginator(array_slice($rows,($page-1)*10,10),count($rows),10,$page,['path'=>url()->current(),'query'=>$data['filters']]);
  $data['requestKey']=$this->token($request,'cash_request');
  $data['latest']=FinancialReport::where($data['filters'])->orderByDesc('version')->first();
  return view('cash.book',$data);
 }
 public function configure(Request $request,CashBookService $service){
  $data=$request->validate(['starts_on'=>'required|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:'.now('Asia/Jakarta')->toDateString(),'opening_balance'=>'required|integer|between:-1000000000000,1000000000000','opening_confirmed'=>'accepted']);
  $service->configure($data,$request->user()->id);
  return redirect()->route('treasurer.reports',$this->period($request))->with('success','Saldo pembukaan disimpan dan dikunci. Posisi saldo adalah sebelum transaksi pada tanggal mulai pembukuan.');
 }
 public function store(Request $request,CashBookService $service,CashEvidenceStorage $files){
  $data=$request->validate(['request_key'=>'required|uuid','kind'=>'required|in:income,expense','transaction_date'=>'required|date_format:Y-m-d|before_or_equal:'.now('Asia/Jakarta')->toDateString(),'description'=>'required|string|max:1000','amount'=>'required|integer|between:1,1000000000000']);
  if(trim($data['description'])==='')throw ValidationException::withMessages(['description'=>'Keterangan wajib diisi.']);
  $files->validate($request);$stored=null;
  try{$transactionId=$service->locked(function($book)use($request,$data,$service,$files,&$stored){
   $existing=DB::table('cash_transactions')->where('request_key',$data['request_key'])->first();
   if($existing){
    abort_unless((int)$existing->recorded_by===$request->user()->id,403);
    if($existing->kind!==$data['kind']||$existing->transaction_date!==$data['transaction_date']||$existing->description!==trim($data['description'])||(int)$existing->amount!==(int)$data['amount'])throw ValidationException::withMessages(['request_key'=>'Formulir ini sudah digunakan untuk transaksi lain. Muat ulang sebelum menambah transaksi baru.']);
    return (int)$existing->id;
   }
   if(($request->session()->get('cash_request.'.$data['request_key'])??0)<now()->timestamp)throw ValidationException::withMessages(['request_key'=>'Formulir kedaluwarsa. Muat ulang halaman kas.']);
   if(!$book->starts_on)throw ValidationException::withMessages(['transaction_date'=>'Saldo awal belum diatur.']);
   if($data['transaction_date']<$book->starts_on)throw ValidationException::withMessages(['transaction_date'=>'Tanggal transaksi tidak boleh sebelum '.$book->starts_on.'.']);
   $id=DB::table('cash_transactions')->insertGetId(['request_key'=>$data['request_key'],'kind'=>$data['kind'],'transaction_date'=>$data['transaction_date'],'description'=>trim($data['description']),'amount'=>(int)$data['amount'],'recorded_by'=>$request->user()->id,'created_at'=>now()]);
   $files->attach($request,'manual',$id,$stored);
   return (int)$id;
  });}catch(\Throwable $e){$files->cleanup($stored);throw $e;}
  $request->session()->forget('cash_request.'.$data['request_key']);
  $period=['year'=>(int)substr($data['transaction_date'],0,4),'month'=>(int)substr($data['transaction_date'],5,2)];
  $page=$service->locked(function($book)use($service,$period,$transactionId){
   $rows=array_reverse($service->statement($period['year'],$period['month'],$book)['period']);
   foreach($rows as $index=>$row)if($row['kind']==='manual'&&(int)$row['id']===$transactionId)return intdiv($index,10)+1;
   return 1;
  });
  return redirect()->route('treasurer.reports',$period+['page'=>$page])->with('success','Transaksi kas tersimpan. Periode tanggal transaksi dan ringkasannya telah diperbarui.');
 }
 public function repair(Request $request,CashBookService $service,CashEvidenceStorage $files){
  $request->validate(['source'=>['required','regex:/\A(dues|manual):[1-9][0-9]*\z/']]);[$kind,$id]=explode(':',$request->source);$files->validate($request);$stored=null;
  try{$service->locked(function()use($request,$service,$files,$kind,$id,&$stored){
   abort_unless($service->sourceExists($kind,(int)$id),404);
   if($service->evidence($kind,(int)$id))throw ValidationException::withMessages(['source'=>'Bukti sudah tersedia. Berkas lama tidak ditimpa.']);
   $files->attach($request,$kind,(int)$id,$stored);
  });}catch(\Throwable $e){$files->cleanup($stored);throw $e;}
  return redirect()->route('treasurer.reports',$this->period($request))->with('success','Bukti pelengkap disimpan tanpa mengubah nominal atau pembayaran asli.');
 }
 public function proof(string $kind,int $id,CashBookService $service){
  abort_unless(in_array($kind,['dues','manual'],true)&&$service->sourceExists($kind,$id),404);$proof=$service->evidence($kind,$id);abort_unless($proof,404);
  $ext=match($proof['mime']){'image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf',default=>abort(404)};
  return response()->file(Storage::disk($proof['disk'])->path($proof['path']),['Content-Type'=>$proof['mime'],'Content-Disposition'=>($ext==='pdf'?'attachment':'inline').'; filename="bukti-kas-'.$kind.'-'.$id.'.'.$ext.'"','X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store','Content-Security-Policy'=>"default-src 'none'; sandbox"]);
 }
 public function admin(Request $request,CashBookService $service){
  $request->validate(['publication_status'=>'nullable|in:all,published,unpublished,stale','version'=>'nullable|integer|min:1']);$data=$this->data($request,$service);
  $data['history']=FinancialReport::where($data['filters'])->orderByDesc('version')->with('publisher')->get();$data['latest']=$data['history']->first();
  $data['stale']=$data['latest']&&!hash_equals($data['latest']->source_fingerprint,$data['statement']['fingerprint']);
  $data['publicationStatus']=$request->input('publication_status')??'all';
  $data['matches']=match($data['publicationStatus']){'published'=>(bool)$data['latest'],'unpublished'=>!$data['latest'],'stale'=>(bool)$data['stale'],default=>true};
  $data['historic']=$request->filled('version')?$data['history']->firstWhere('version',(int)$request->version):null;
  if($request->filled('version'))abort_unless($data['historic'],404);
  $data['preview']=null;return view('cash.admin',$data);
 }
 public function preview(Request $request,CashBookService $service){
  $request->validate(['privacy_reviewed'=>'accepted','descriptions'=>'nullable|array','descriptions.*'=>'required|string|max:500']);$data=$this->data($request,$service);
  if(!$data['statement']['ready'])throw ValidationException::withMessages(['publication'=>'Kas belum siap diterbitkan. Periksa saldo pembukaan, transaksi, dan bukti.']);
  $rows=$service->publicRows($data['statement'],$request->input('descriptions',[]));$token=(string)Str::uuid();
  $preview=$data['filters']+['fingerprint'=>$data['statement']['fingerprint'],'rows'=>$rows,'actor'=>$request->user()->id,'expires'=>now()->addMinutes(20)->timestamp];
  $request->session()->put('cash_preview.'.$token,$preview);
  return view('cash.preview',$data+compact('preview','token'));
 }
 public function publish(Request $request,CashBookService $service){
  try{$request->validate(['token'=>'required|uuid','confirmed'=>'accepted']);}catch(ValidationException $e){$e->redirectTo(route('admin.reports',$this->period($request)));throw $e;}
  $preview=$request->session()->get('cash_preview.'.$request->token);
  if(!$preview||$preview['expires']<now()->timestamp||$preview['actor']!==$request->user()->id)throw ValidationException::withMessages(['publication'=>'Pratinjau kedaluwarsa atau sudah digunakan. Tinjau ulang laporan.'])->redirectTo(route('admin.reports',$this->period($request)));
  try{$report=$service->publish($preview,$request->token,$request->user()->id);}catch(ValidationException $e){$e->redirectTo(route('admin.reports',['year'=>$preview['year'],'month'=>$preview['month']]));throw $e;}
  $request->session()->forget('cash_preview.'.$request->token);
  return redirect()->route('admin.reports',['year'=>$report->year,'month'=>$report->month])->with('success','Laporan versi '.$report->version.' diterbitkan untuk Warga.');
 }
 public function resident(Request $request){
  $filters=$this->period($request);$report=FinancialReport::where($filters)->orderByDesc('version')->first();$label=Dues::months()[$filters['month']].' '.$filters['year'];
  return view('cash.resident',compact('report','filters','label'));
 }
 public function printCash(Request $request,CashBookService $service){
  $data=$this->data($request,$service);$data['rows']=$data['statement']['period'];$data['summary']=$data['statement']['summary'];$data['documentTitle']='Laporan Internal Kas Bendahara';$data['report']=null;$data['internal']=true;
  return response()->view('cash.print',$data)->header('Cache-Control','private, no-store');
 }
 public function printResident(Request $request){
  $filters=$this->period($request);$report=FinancialReport::where($filters)->orderByDesc('version')->first();abort_unless($report,404);
  $label=Dues::months()[$filters['month']].' '.$filters['year'];$rows=$report->public_rows;$summary=$report->summary;$documentTitle='Laporan Keuangan Warga — Terbit';$internal=false;
  return response()->view('cash.print',compact('filters','report','label','rows','summary','documentTitle','internal'))->header('Cache-Control','private, no-store');
 }
}
