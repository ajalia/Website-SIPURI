<?php
namespace App\Http\Controllers;
use App\Http\Requests\ResidentRegistrationRequest;
use App\Models\Rt;
use App\Services\AddressGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class RegistrationController extends Controller {
 public function status(Request $request){if($request->user()->isApproved())return redirect()->route('dashboard');return view('auth.status');}
 public function edit(Request $request){abort_unless($request->user()->registration_status==='rejected',403);return view('auth.revise',['rts'=>Rt::where('is_active',true)->orderBy('number')->get()]);}
 public function update(ResidentRegistrationRequest $request){
    AddressGuard::transaction(function()use($request){
        $user=\App\Models\User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
        if($user->registration_status!=='rejected')throw ValidationException::withMessages(['registration'=>'Pendaftaran ini tidak sedang menunggu perbaikan.']);
        AddressGuard::assertAvailable($request->validated('block'),$request->validated('house_number'),$user->id);
        $user->fill($request->safe()->only(['name','rt_id','block','house_number']));$user->registration_status='pending';$user->rejection_reason=null;$user->save();
    });
    $request->user()->refresh();
    return redirect()->route('registration.status')->with('success','Perbaikan berhasil diajukan. Menunggu pemeriksaan RT.');
 }
}
