<?php
namespace App\Http\Controllers;
use App\Enums\Role;
use App\Models\User;
use App\Services\CredentialService;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,DB};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountSecurityController extends Controller {
 public function settings(Request $request){
  $user=$request->user();$layout=match($user->role){Role::Admin=>'layouts.portal',Role::Rt=>'layouts.rt',default=>$user->isApproved()?'layouts.finance':'layouts.auth'};
  return response()->view('account.settings',compact('user','layout'))->header('Cache-Control','private, no-store')->header('X-Frame-Options','DENY');
 }
 public function change(Request $request,CredentialService $service){
  $data=$request->validate(['current_password'=>'required|string|max:128','password'=>PasswordPolicy::rules()],PasswordPolicy::messages());
  $user=$service->change($request->user(),$data['current_password'],$data['password']);
  $this->retainCurrentSession($request,$user);
  return redirect()->route('account.settings')->with('success','Kata sandi berhasil diperbarui. Sesi perangkat lain telah dicabut.');
 }
 public function retainCurrentSession(Request $request,User $user): void {
  Auth::guard('web')->setUser($user);$request->session()->put('credentials.'.$user->id,$user->credentials_version);$request->session()->regenerate(true);$request->session()->regenerateToken();
 }
 public function accounts(Request $request){
  $data=$request->validate(['q'=>'nullable|string|max:100','role'=>'nullable|in:all,warga']);$q=$data['q']??'';$role=$data['role']??'all';
  $accounts=User::where('role','warga')->when($role!=='all',fn($s)=>$s->where('role',$role))->when($q!=='',fn($s)=>$s->where(fn($s)=>$s->where('name','like','%'.$q.'%')->orWhere('username','like','%'.$q.'%')))->with('rt')->orderBy('name')->paginate(15)->withQueryString();
  return view('account.accounts',compact('accounts','q','role'));
 }
 public function confirm(Request $request,User $target,CredentialService $service){
  abort_unless($service->mayReset($request->user(),$target),403);
  $token=Str::random(40);$request->session()->put('reset_challenge.'.$target->id,['token'=>$token,'expires'=>now()->addMinutes(10)->timestamp]);
  $layout=$request->user()->role===Role::Admin?'layouts.portal':'layouts.rt';
  return response()->view('account.reset-confirm',compact('target','token','layout'))->header('Cache-Control','private, no-store')->header('X-Frame-Options','DENY');
 }
 public function reset(Request $request,User $target,CredentialService $service){
  abort_unless($service->mayReset($request->user(),$target),403);
  $request->validate(['confirmed'=>'required|accepted','token'=>'required|string|size:40']);$challenge=$request->session()->get('reset_challenge.'.$target->id);
  if(!$challenge||$challenge['expires']<now()->timestamp||!hash_equals($challenge['token'],$request->token))throw ValidationException::withMessages(['confirmed'=>'Konfirmasi kedaluwarsa atau sudah digunakan. Buka ulang halaman pemulihan.']);
  $temporary=$service->reset($request->user(),$target,$request->token);
  $request->session()->forget('reset_challenge.'.$target->id);
  $layout=$request->user()->role===Role::Admin?'layouts.portal':'layouts.rt';
  // Direct response only: no flash, session, URL, file, or audit entry contains plaintext.
  return response()->view('account.reset-result',compact('target','temporary','layout'))->withHeaders(['Cache-Control'=>'private, no-store, max-age=0','Pragma'=>'no-cache','Referrer-Policy'=>'no-referrer','X-Frame-Options'=>'DENY']);
 }
}
