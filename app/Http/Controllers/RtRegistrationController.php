<?php
namespace App\Http\Controllers;
use App\Enums\Role;
use App\Services\AddressGuard;
use App\Support\HouseAddress;
use App\Models\{User,House,HouseResidency};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
class RtRegistrationController extends Controller {
 private function authorizeApplicant(Request $request,User $applicant): void {abort_unless($applicant->role===Role::Resident && $request->user()->rt_id!==null && $applicant->rt_id===$request->user()->rt_id,403);}
 public function index(Request $request){
    $filters=$request->validate(['q'=>'nullable|string|max:100','status'=>'nullable|in:pending,rejected,approved,all']);
    $status=$filters['status']??'pending';$q=$filters['q']??'';
    $applicants=User::where('role','warga')->where('rt_id',$request->user()->rt_id)->when($status!=='all',fn($query)=>$query->where('registration_status',$status))
        ->when($q,fn($query)=>$query->where(fn($sub)=>$sub->where('name','like','%'.$q.'%')->orWhere('block','like','%'.$q.'%')->orWhere('house_number','like','%'.$q.'%')))
        ->latest()->paginate(10)->withQueryString();
    $pending=User::where('role','warga')->where('rt_id',$request->user()->rt_id)->where('registration_status','pending')->count();
    return view('rt.registrations',compact('applicants','status','q','pending'));
 }
 public function show(Request $request,User $applicant){$this->authorizeApplicant($request,$applicant);return view('rt.review',compact('applicant'));}
 public function decide(Request $request,User $applicant){
    $this->authorizeApplicant($request,$applicant);
    $data=$request->validate(['decision'=>'required|in:approved,rejected','reason'=>'nullable|required_if:decision,rejected|string|max:2000'],['reason.required_if'=>'Tuliskan alasan agar warga dapat memperbaiki pendaftaran.']);
    if($data['decision']==='rejected' && trim($data['reason']??'')==='')throw ValidationException::withMessages(['reason'=>'Alasan penolakan wajib diisi.']);
    try {AddressGuard::transaction(function()use($request,$applicant,$data){
        $resident=User::whereKey($applicant->id)->lockForUpdate()->firstOrFail();
        $this->authorizeApplicant($request,$resident);
        if($resident->registration_status!=='pending')throw ValidationException::withMessages(['decision'=>'Pendaftaran sudah diperiksa. Muat ulang halaman untuk melihat status terbaru.']);
        if($data['decision']==='approved'){
            $resident->block=HouseAddress::block($resident->block);$resident->house_number=HouseAddress::number($resident->house_number);
            AddressGuard::assertAvailable($resident->block,$resident->house_number,$resident->id);
            $house=House::where('rt_id',$resident->rt_id)->where('block',$resident->block)->where('house_number',$resident->house_number)->lockForUpdate()->first();
            if($house && $house->current_user_id && $house->current_user_id!==$resident->id)throw ValidationException::withMessages(['block'=>HouseAddress::CONFLICT]);
            if(!$house)$house=new House(['rt_id'=>$resident->rt_id,'block'=>$resident->block,'house_number'=>$resident->house_number]);
            $house->current_user_id=$resident->id;$house->save();
            HouseResidency::create(['house_id'=>$house->id,'user_id'=>$resident->id,'resident_name'=>$resident->name,'block'=>$house->block,'house_number'=>$house->house_number,'started_at'=>now()]);$resident->approved_at=now();$resident->rejection_reason=null;
        } else {$resident->rejection_reason=trim($data['reason']);}
        $resident->registration_status=$data['decision'];$resident->save();
        DB::table('registration_reviews')->insert(['user_id'=>$resident->id,'reviewer_id'=>$request->user()->id,'decision'=>$data['decision'],'reason'=>$resident->rejection_reason,'submitted_data'=>json_encode($resident->only(['name','username','rt_id','block','house_number']),JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);
    });}catch(QueryException $e){if(in_array((string)$e->getCode(),['23000','23505'],true))throw ValidationException::withMessages(['block'=>HouseAddress::CONFLICT]);throw $e;}
    return redirect()->route('rt.registrations.show',$applicant)->with('success',$data['decision']==='approved'?'Akun warga berhasil disetujui.':'Pendaftaran ditolak. Alasan dapat dilihat oleh pendaftar.');
 }
}
