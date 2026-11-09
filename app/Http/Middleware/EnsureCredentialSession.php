<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
class EnsureCredentialSession {
 public function handle(Request $request,Closure $next){
  $user=$request->user();$key='credentials.'.$user->id;
  $version=(int)User::whereKey($user->id)->value('credentials_version');
  if((int)$request->session()->get($key,0)!==$version){
   Auth::guard('web')->logoutCurrentDevice();$request->session()->invalidate();$request->session()->regenerateToken();
   return redirect()->route('login')->withErrors(['username'=>'Sesi berakhir setelah perubahan keamanan akun. Silakan masuk kembali.']);
  }
  $request->session()->put($key,$version);return $next($request);
 }
}
