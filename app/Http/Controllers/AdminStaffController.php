<?php
namespace App\Http\Controllers;
use App\Enums\Role;
use App\Models\{User,Rt};
use App\Services\StaffAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AdminStaffController extends Controller {
 private function filters(Request $request): array {
  $f=Validator::make($request->query(),['q'=>'nullable|string|max:100','role'=>'nullable|in:all,rt,bendahara','status'=>'nullable|in:all,active,inactive','page'=>'nullable|integer|min:1'])->validate();
  return ['q'=>$f['q']??'','role'=>$f['role']??'all','status'=>$f['status']??'all'];
 }
 private function challenge(Request $request,string $action,?User $target,StaffAccountService $service): string {
  $actions=$request->session()->get('staff_actions',[]);
  foreach($actions as $key=>$entry)if($entry['expires']<now()->timestamp)unset($actions[$key]);
  $request->session()->put('staff_actions',$actions);
  $nonce=(string)Str::uuid();$request->session()->put('staff_actions.'.$nonce,['actor'=>$request->user()->id,'action'=>$action,'target'=>$target?->id,'expected'=>$target?$service->fingerprint($target):null,'expires'=>now()->addMinutes(20)->timestamp]);return $nonce;
 }
 private function verify(Request $request,string $action,?User $target=null): array {
  $request->validate(['request_key'=>'required|uuid']);$challenge=$request->session()->get('staff_actions.'.$request->request_key);
  if(!$challenge||$challenge['expires']<now()->timestamp||$challenge['actor']!==$request->user()->id||$challenge['action']!==$action||$challenge['target']!==$target?->id)throw ValidationException::withMessages(['request_key'=>'Formulir kedaluwarsa atau tidak sesuai akun/tindakan. Buka ulang formulir.']);return $challenge;
 }
 private function data(Request $request,StaffAccountService $service,bool $fresh=false): array {
  $filters=$fresh?['q'=>'','role'=>'all','status'=>'all']:$this->filters($request);$base=User::whereIn('role',['rt','bendahara']);
  $totals=(clone $base)->selectRaw('COUNT(*) AS total, COALESCE(SUM(CASE WHEN is_active=1 THEN 1 ELSE 0 END),0) AS active, COALESCE(SUM(CASE WHEN is_active=0 THEN 1 ELSE 0 END),0) AS inactive')->first();
  $summary=['total'=>(int)$totals->total,'active'=>(int)$totals->active,'inactive'=>(int)$totals->inactive];
  $query=$base->when($filters['role']!=='all',fn($q)=>$q->where('role',$filters['role']))->when($filters['status']!=='all',fn($q)=>$q->where('is_active',$filters['status']==='active'))->when($filters['q']!=='',fn($q)=>$q->where(fn($s)=>$s->where('name','like','%'.$filters['q'].'%')->orWhere('username','like','%'.$filters['q'].'%')));
  $accounts=$query->with('rt')->orderByDesc('id')->paginate(10,['*'],'page',$fresh?1:null)->appends($filters);$rts=Rt::where('is_active',true)->orderBy('number')->get();$conflicts=$service->conflicts();
  $old=$request->old('request_key');$requestKey=!$fresh&&$old&&$request->session()->get('staff_actions.'.$old.'.action')==='create'?$old:$this->challenge($request,'create',null,$service);
  return compact('filters','summary','accounts','rts','conflicts','requestKey');
 }
 public function index(Request $request,StaffAccountService $service){return view('admin.staff',$this->data($request,$service));}
 public function manage(Request $request,User $target,StaffAccountService $service){
  $service->assertStaff($target);$data=$this->data($request,$service);$target->load('rt');$mode='manage';$actionKey=$this->challenge($request,'rename',$target,$service);
  return view('admin.staff',$data+compact('target','mode','actionKey'));
 }
 public function confirmStatus(Request $request,User $target,StaffAccountService $service){
  $service->assertStaff($target);$request->validate(['operation'=>'required|in:activate,deactivate']);$activate=$request->operation==='activate';
  abort_if($target->is_active===$activate,409,'Status akun sudah berubah. Muat ulang daftar akun.');
  $data=$this->data($request,$service);$target->load('rt');$mode='status';$actionKey=$this->challenge($request,$request->operation,$target,$service);
  return view('admin.staff',$data+compact('target','mode','actionKey','activate'));
 }
 public function store(Request $request,StaffAccountService $service){
  if(is_string($request->username))$request->merge(['username'=>mb_strtolower(trim($request->username))]);if(is_string($request->name))$request->merge(['name'=>trim($request->name)]);
  $data=$request->validate(['name'=>'required|string|max:150','username'=>['required','string','min:3','max:50','regex:/\A[a-z0-9][a-z0-9._]*\z/'],'role'=>'required|in:rt,bendahara','rt_id'=>'nullable|required_if:role,rt|prohibited_if:role,bendahara|integer','is_active'=>'prohibited','password'=>'prohibited','must_change_password'=>'prohibited','registration_status'=>'prohibited']);
  $this->verify($request,'create');$result=$service->create($request->user(),$data,$request->request_key);
  if($result['replayed'])return redirect()->route('admin.accounts')->with('success','Permintaan sebelumnya sudah diproses. Sandi sementara tidak ditampilkan ulang; gunakan Reset Kata Sandi bila diperlukan.');
  $temporary=$result['temporary'];$created=$result['user'];
  $request->session()->forget('_old_input');
  // A direct POST response updates the list and shows plaintext ONCE, never in session/flash.
  return response()->view('admin.staff',$this->data($request,$service,true)+compact('temporary','created'))->withHeaders(['Cache-Control'=>'private, no-store, max-age=0','Pragma'=>'no-cache','Referrer-Policy'=>'no-referrer','X-Frame-Options'=>'DENY']);
 }
 public function rename(Request $request,User $target,StaffAccountService $service){
  $service->assertStaff($target);if(is_string($request->name))$request->merge(['name'=>trim($request->name)]);
  Validator::make($request->request->all(),['name'=>'required|string|max:150','username'=>'prohibited','role'=>'prohibited','rt_id'=>'prohibited','is_active'=>'prohibited','password'=>'prohibited','must_change_password'=>'prohibited'])->validate();
  $challenge=$this->verify($request,'rename',$target);$changed=$service->rename($request->user(),$target,$request->name,$request->request_key,$challenge['expected']);
  return redirect()->route('admin.accounts',$this->filters($request))->with('success',$changed?'Nama pengurus diperbarui. Username, peran, wilayah, dan riwayat tetap.':'Permintaan ini sudah diproses sebelumnya. Tidak ada perubahan baru; periksa data terkini pada daftar.');
 }
 private function setStatus(Request $request,User $target,StaffAccountService $service,bool $active){
  $service->assertStaff($target);Validator::make($request->request->all(),['confirmed'=>'accepted','is_active'=>'prohibited','role'=>'prohibited','rt_id'=>'prohibited','username'=>'prohibited'])->validate();$challenge=$this->verify($request,$active?'activate':'deactivate',$target);
  $changed=$service->status($request->user(),$target,$active,$request->request_key,$challenge['expected']);
  return redirect()->route('admin.accounts',$this->filters($request))->with('success',!$changed?'Permintaan ini sudah diproses sebelumnya. Tidak ada perubahan baru; periksa status terkini pada daftar.':($active?'Akun pengurus diaktifkan. Pemilik dapat masuk kembali; kewajiban ganti sandi tetap.':'Akun pengurus dinonaktifkan dan seluruh sesinya dicabut. Riwayat tetap tersimpan.'));
 }
 public function activate(Request $request,User $target,StaffAccountService $service){return $this->setStatus($request,$target,$service,true);}
 public function deactivate(Request $request,User $target,StaffAccountService $service){return $this->setStatus($request,$target,$service,false);}
}
