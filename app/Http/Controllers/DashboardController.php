<?php
namespace App\Http\Controllers;
use App\Enums\Role;
use App\Models\{User,House};
use Illuminate\Http\Request;
class DashboardController extends Controller {
 public function redirect(Request $request){return redirect()->route(match($request->user()->role){Role::Admin=>'admin.dashboard',Role::Rt=>'rt.dashboard',Role::Treasurer=>'treasurer.dashboard',Role::Resident=>'resident.dashboard'});}
 public function index(Request $request){
    $user=$request->user();$pending=0;$houses=0;
    if($user->role===Role::Rt){$pending=User::where('role','warga')->where('rt_id',$user->rt_id)->where('registration_status','pending')->count();$houses=House::where('rt_id',$user->rt_id)->whereNotNull('current_user_id')->count();}
    if($user->role===Role::Rt){
        $recent=User::where('role','warga')->where('rt_id',$user->rt_id)->where('registration_status','pending')->latest()->limit(3)->get();
        return view('rt.dashboard',compact('pending','houses','recent'));
    }
    if($user->role===Role::Admin)return view('admin.dashboard');
    return view('dashboard',compact('pending','houses'));
 }
}
