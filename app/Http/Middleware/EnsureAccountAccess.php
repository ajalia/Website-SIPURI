<?php
namespace App\Http\Middleware;
use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class EnsureAccountAccess {
 public function handle(Request $request, Closure $next) {
    $user=$request->user();
    if(!$user->is_active){Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();return redirect()->route('login')->withErrors(['username'=>'Akun tidak aktif. Hubungi Admin.']);}
    if($user->must_change_password && !$request->routeIs('password.initial*','logout')) return redirect()->route('password.initial');
    if($user->role === Role::Resident && !$user->isApproved() && !$request->routeIs('registration.*','password.initial*','account.settings','account.password','logout')) return redirect()->route('registration.status');
    return $next($request);
 }
}
