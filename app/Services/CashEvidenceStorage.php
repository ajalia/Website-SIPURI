<?php
namespace App\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\ValidationException;
class CashEvidenceStorage {
 public function validate(Request $request): void {
  $request->validate(['proof'=>'required|file|mimes:jpg,jpeg,png,pdf|mimetypes:image/jpeg,image/png,application/pdf|max:5120']);
  $file=$request->file('proof');$mime=$file->getMimeType();
  if(($mime==='application/pdf'&&file_get_contents($file->getRealPath(),false,null,0,5)!=='%PDF-')||($mime!=='application/pdf'&&@getimagesize($file->getRealPath())===false))throw ValidationException::withMessages(['proof'=>'Isi bukti harus gambar JPG/PNG atau PDF yang valid, maksimal 5 MB.']);
 }
 public function attach(Request $request,string $kind,int $id,?string &$stored): void {
  if($stored===null)$stored=$request->file('proof')->store('proofs','cash_proofs');
  DB::table('cash_proofs')->insert(['source_kind'=>$kind,'source_id'=>$id,'path'=>$stored,'mime'=>$request->file('proof')->getMimeType(),'file_size'=>$request->file('proof')->getSize(),'uploaded_by'=>$request->user()->id,'created_at'=>now()]);
 }
 public function cleanup(?string $stored): void {if($stored)Storage::disk('cash_proofs')->delete($stored);}
}
