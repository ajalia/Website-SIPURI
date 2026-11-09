<?php
namespace App\Services;
use App\Enums\Role;
use App\Models\{PublicContent,User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Storage,Log};
use Illuminate\Validation\ValidationException;
class PublicContentService {
 public function locked(User $actor,callable $callback): mixed {
  return DB::transaction(function()use($actor,$callback){DB::table('public_content_locks')->where('id',1)->lockForUpdate()->firstOrFail();$admin=User::whereKey($actor->id)->firstOrFail();abort_unless($admin->role===Role::Admin&&$admin->is_active&&!$admin->must_change_password,403);return $callback($admin);},3);
 }
 // Only module-owned, unreferenced images can be removed, under the same writer mutex.
 public function prune(User $actor,?string $path): void {
  if(!PublicContent::owned($path))return;
  try{DB::transaction(function()use($path){DB::table('public_content_locks')->where('id',1)->lockForUpdate()->firstOrFail();if(!PublicContent::where('image_path',$path)->exists())Storage::disk('public_content')->delete($path);},3);}
  catch(\Throwable $e){Log::warning('Public content image cleanup deferred.', ['exception'=>get_class($e)]);}
 }
 public function save(User $actor,string $section,?PublicContent $target,array $data,?UploadedFile $file,string $nonce,?int $expected): PublicContent {
  $new=null;$old=null;
  try{
   if($file){$new=$file->store('images','public_content');if(!$new)throw new \RuntimeException('Image storage failed');}
   $saved=$this->locked($actor,function($admin)use($section,$target,$data,$new,$nonce,$expected,&$old){
    abort_unless(isset(PublicContent::SECTIONS[$section]),404);
    if(!$target&&$existing=PublicContent::where('request_key',$nonce)->lockForUpdate()->first()){abort_unless($existing->section===$section&&(int)$existing->created_by===$admin->id,403);return $existing;}
    if($target){$record=PublicContent::whereKey($target->id)->lockForUpdate()->firstOrFail();abort_unless($record->section===$section,404);if($record->version!==$expected)throw ValidationException::withMessages(['content'=>'Konten sudah berubah sejak formulir dibuka. Muat ulang sebelum menyimpan.']);}
    else {if(PublicContent::singleton($section)&&PublicContent::where('singleton_key',$section)->exists())throw ValidationException::withMessages(['content'=>'Bagian ini sudah disimpan Admin lain. Buka ulang formulir edit.']);$record=new PublicContent(['section'=>$section,'singleton_key'=>PublicContent::singleton($section)?$section:null,'request_key'=>$nonce,'created_by'=>$admin->id]);}
    if(!$new&&in_array($section,['banner','profil','fasilitas'],true)&&!$record->imageAvailable())throw ValidationException::withMessages(['photo'=>'Foto belum tersedia. Pilih foto yang valid sebelum menyimpan.']);
    $old=$record->image_path;$record->fill($data);if($new)$record->image_path=$new;$record->updated_by=$admin->id;$record->version=$target?$record->version+1:1;$record->save();return $record;
   });
  }catch(\Throwable $e){if($new)$this->prune($actor,$new);throw $e;}
  if($old&&$old!==$saved->image_path)$this->prune($actor,$old);
  if($new&&$new!==$saved->image_path)$this->prune($actor,$new);
  return $saved;
 }
 public function visibility(User $actor,PublicContent $target,bool $visible,int $expected): void {
  $this->locked($actor,function($admin)use($target,$visible,$expected){$record=PublicContent::whereKey($target->id)->lockForUpdate()->firstOrFail();abort_if(PublicContent::singleton($record->section),404);if($record->version!==$expected)throw ValidationException::withMessages(['content'=>'Konten berubah sejak konfirmasi dibuka. Muat ulang daftar.']);$record->is_visible=$visible;$record->version++;$record->updated_by=$admin->id;$record->save();});
 }
}
