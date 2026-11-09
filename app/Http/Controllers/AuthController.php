<?php
namespace App\Http\Controllers;
use App\Enums\Role;
use App\Services\AddressGuard;
use App\Support\PasswordPolicy;
use App\Services\CredentialService;
use App\Models\{User,Rt};
use App\Http\Requests\ResidentRegistrationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,Hash,RateLimiter};
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
class AuthController extends Controller {
 public function loginForm(){return view('auth.login');}
 public function login(Request $request){
    $data=$request->validate(['username'=>'required|string|max:50','password'=>'required|string|max:128'],['required'=>'Isian ini wajib diisi.']);
    $username=mb_strtolower(trim($data['username']));$key='login:'.hash('sha256',$username.'|'.$request->ip());
    if(RateLimiter::tooManyAttempts($key,5)) throw ValidationException::withMessages(['username'=>'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.']);
    if(!Auth::attempt(['username'=>$username,'password'=>$data['password'],'is_active'=>true])){RateLimiter::hit($key,900);throw ValidationException::withMessages(['username'=>'Username atau kata sandi tidak sesuai, atau akun tidak aktif.']);}
    RateLimiter::clear($key);$request->session()->regenerate();$request->session()->put('credentials.'.Auth::id(),(int)Auth::user()->credentials_version);return redirect()->route('dashboard');
 }
 public function registerForm(){return view('auth.register',['rts'=>Rt::where('is_active',true)->orderBy('number')->get()]);}
 public function register(ResidentRegistrationRequest $request){
        $user=AddressGuard::transaction(function()use($request){
        $data=$request->safe()->except('password_confirmation');
        AddressGuard::assertAvailable($data['block'],$data['house_number']);
        if(User::where('username',$data['username'])->exists())throw ValidationException::withMessages(['username'=>'Username sudah digunakan.']);
        $user=new User($data);$user->role=Role::Resident;$user->registration_status='pending';$user->must_change_password=false;$user->save();
        return $user;
    });
    Auth::login($user);$request->session()->regenerate();$request->session()->put('credentials.'.$user->id,(int)$user->credentials_version);return redirect()->route('registration.status')->with('success','Pendaftaran berhasil dikirim.');
 }
 public function logout(Request $request){Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();return redirect()->route('login');}
 public function passwordForm(Request $request){if(!$request->user()->must_change_password)return redirect()->route('dashboard');return view('auth.password');}
 public function changePassword(Request $request){
    abort_unless($request->user()->must_change_password,403);
    $data=$request->validate(['current_password'=>['required','string','max:128'],'password'=>PasswordPolicy::rules()],PasswordPolicy::messages());
    $user=app(CredentialService::class)->change($request->user(),$data['current_password'],$data['password']);
    app(AccountSecurityController::class)->retainCurrentSession($request,$user);
    return redirect()->route('dashboard')->with('success','Kata sandi berhasil diperbarui. Sesi perangkat lain telah dicabut.');
 }
}
