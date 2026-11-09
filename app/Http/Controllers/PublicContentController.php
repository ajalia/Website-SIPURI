<?php
namespace App\Http\Controllers;
use App\Enums\Role;
use App\Models\{PublicContent,Rt,User};
use App\Services\PublicContentService;
use App\Support\HouseAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Schema,Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\{Rule,ValidationException};
class PublicContentController extends Controller {
 private function section(string $section): array {abort_unless(isset(PublicContent::SECTIONS[$section]),404);return PublicContent::SECTIONS[$section];}
 public function home(){
  $contents=Schema::hasTable('public_contents')?PublicContent::where('is_visible',true)->with('rt')->orderBy('id')->get()->groupBy('section'):collect();
  $banner=$contents->get('banner',collect())->first();$profile=$contents->get('profil',collect())->first();$facilities=$contents->get('fasilitas',collect());$officers=$contents->get('pengurus',collect());$culinary=$contents->get('kuliner',collect());
  $hasFacilities=Schema::hasTable('public_contents')&&PublicContent::where('section','fasilitas')->exists();
  return view('home',compact('banner','profile','facilities','officers','culinary','hasFacilities'));
 }
 public function image(Request $request,string $filename){
  $path='images/'.$filename;abort_unless(PublicContent::owned($path),404);
  $public=PublicContent::where('image_path',$path)->where('is_visible',true)->exists();$user=$request->user();
  if($user&&!$public)$user=User::find($user->id);
  $admin=$user&&$user->role===Role::Admin&&$user->is_active&&!$user->must_change_password&&(int)$request->session()->get('credentials.'.$user->id,0)===(int)$user->credentials_version;
  abort_unless($public||$admin,404);abort_unless(PublicContent::where('image_path',$path)->exists()&&Storage::disk('public_content')->exists($path),404);
  $mime=match(strtolower(pathinfo($filename,PATHINFO_EXTENSION))){'jpg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp',default=>abort(404)};
  return response()->file(Storage::disk('public_content')->path($path),['Content-Type'=>$mime,'Content-Disposition'=>'inline; filename="'.$filename.'"','X-Content-Type-Options'=>'nosniff','Content-Security-Policy'=>"default-src 'none'; sandbox",'Cache-Control'=>$public?'public, max-age=0, must-revalidate':'private, no-store']);
 }
 public function index(){return view('content.overview');}
 private function data(string $section): array {
  $labels=$this->section($section);$singleton=PublicContent::singleton($section);$items=PublicContent::where('section',$section)->with('rt')->orderBy('id')->paginate(10);
  return compact('section','labels','singleton','items');
 }
 private function nonce(Request $request,string $section,?PublicContent $record,string $action,?bool $visible=null): string {
  $entries=$request->session()->get('content_actions',[]);foreach($entries as $key=>$entry)if($entry['expires']<now()->timestamp)unset($entries[$key]);$key=(string)Str::uuid();
  $entries[$key]=['actor'=>$request->user()->id,'section'=>$section,'target'=>$record?->id,'version'=>$record?->version,'action'=>$action,'visible'=>$visible,'expires'=>now()->addMinutes(20)->timestamp];$request->session()->put('content_actions',$entries);return $key;
 }
 public function listing(Request $request,string $section){
  $data=$this->data($section);if($data['singleton']){$record=PublicContent::where('section',$section)->first();return $this->editor($request,$section,$record);}
  return view('content.list',$data);
 }
 public function create(Request $request,string $section){$this->section($section);abort_if(PublicContent::singleton($section),404);return $this->editor($request,$section,null);}
 public function edit(Request $request,string $section,PublicContent $content){$this->section($section);abort_unless($content->section===$section,404);return $this->editor($request,$section,$content);}
 private function editor(Request $request,string $section,?PublicContent $record){
  $data=$this->data($section);$mode='edit';$requestKey=$this->nonce($request,$section,$record,'save');$rts=Rt::orderBy('number')->get();
  return view($data['singleton']?'content.overview':'content.list',$data+compact('record','mode','requestKey','rts'));
 }
 private function verify(Request $request,string $section,?PublicContent $record,string $action): array {
  $request->validate(['request_key'=>'required|uuid']);$entry=$request->session()->get('content_actions.'.$request->request_key);
  if(!$entry||$entry['actor']!==$request->user()->id||$entry['section']!==$section||$entry['target']!==$record?->id||$entry['action']!==$action||$entry['expires']<now()->timestamp)throw ValidationException::withMessages(['content'=>'Formulir kedaluwarsa atau tidak sesuai konten. Buka ulang formulir.']);return $entry;
 }
 private function validated(Request $request,string $section,?PublicContent $record): array {
  if($section==='kuliner')$request->merge(['block'=>HouseAddress::block($request->input('block')),'house_number'=>HouseAddress::number($request->input('house_number'))]);
  $plain=function($attribute,$value,$fail){if(is_string($value)&&$value!==strip_tags($value))$fail('Gunakan teks biasa, tanpa tag HTML.');};
  $rules=['name'=>['required','string','max:'.(PublicContent::singleton($section)?160:150),$plain],'photo'=>[Rule::requiredIf(in_array($section,['banner','profil','fasilitas'],true)&&!$record?->imageAvailable()),'nullable','file','mimes:jpg,jpeg,png,webp','mimetypes:image/jpeg,image/png,image/webp','max:5120']];
  if($section==='pengurus')$rules+=['job_title'=>['required','string','max:120',$plain],'area_level'=>'required|in:RW,RT','rt_id'=>['nullable','required_if:area_level,RT','prohibited_if:area_level,RW','integer',Rule::exists('rts','id')]];
  else $rules['description']=['required','string','max:'.match($section){'profil'=>5000,'fasilitas'=>1500,default=>500},$plain];
  if($section==='kuliner')$rules+=['block'=>HouseAddress::blockRules(),'house_number'=>HouseAddress::numberRules(),'opens_at'=>'required|date_format:H:i','closes_at'=>'required|date_format:H:i'];
  $rules['is_visible']=PublicContent::singleton($section)?'prohibited':'nullable|boolean';
  $validated=$request->validate($rules,['required'=>':attribute wajib diisi.','max.string'=>':attribute maksimal :max karakter.','photo.required'=>'Foto wajib dipilih karena belum ada foto tersimpan yang tersedia.','photo.max'=>'Ukuran foto maksimal 5 MB.','photo.mimes'=>'Foto harus JPG/JPEG/PNG/WebP yang valid.','photo.mimetypes'=>'Isi foto harus JPG/JPEG/PNG/WebP yang valid.','rt_id.exists'=>'Pilih RT yang tersedia di database.','rt_id.required_if'=>'Pilih RT untuk tingkat wilayah RT.','rt_id.prohibited_if'=>'Wilayah RW tidak memerlukan pilihan RT.','block.regex'=>HouseAddress::BLOCK_HELP,'house_number.regex'=>'Nomor harus bilangan bulat positif.','opens_at.date_format'=>'Jam buka harus format 24 jam HH:MM.','closes_at.date_format'=>'Jam tutup harus format 24 jam HH:MM.'],['name'=>PublicContent::singleton($section)?'Judul':'Nama','description'=>'Keterangan','job_title'=>'Jabatan','area_level'=>'Tingkat wilayah','rt_id'=>'RT','block'=>'Blok','house_number'=>'Nomor','opens_at'=>'Jam buka','closes_at'=>'Jam tutup']);
  $file=$request->file('photo');if($file){$info=@getimagesize($file->getRealPath());if(!$info||!in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true))throw ValidationException::withMessages(['photo'=>'Isi berkas bukan gambar JPG/PNG/WebP yang valid.']);}
  unset($validated['photo']);
  if($section==='pengurus')$validated['rt_id']=$validated['area_level']==='RT'?(int)$validated['rt_id']:null;
  $validated['is_visible']=PublicContent::singleton($section)?true:$request->boolean('is_visible');return $validated;
 }
 public function store(Request $request,string $section,PublicContentService $service){return $this->save($request,$section,null,$service);}
 public function update(Request $request,string $section,PublicContent $content,PublicContentService $service){return $this->save($request,$section,$content,$service);}
 private function save(Request $request,string $section,?PublicContent $record,PublicContentService $service){
  $this->section($section);if($record)abort_unless($record->section===$section,404);
  $entry=$this->verify($request,$section,$record,'save');$data=$this->validated($request,$section,$record);
  try{$saved=$service->save($request->user(),$section,$record,$data,$request->file('photo'),$request->request_key,$entry['version']);}
  catch(ValidationException $e){throw $e;}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){throw $e;}catch(\Throwable $e){report($e);throw ValidationException::withMessages(['content'=>'Konten belum berhasil disimpan. Foto lama tetap dipertahankan. Coba kembali dan pilih ulang foto.']);}
  $page=intdiv(max(0,PublicContent::where('section',$section)->where('id','<=',$saved->id)->count()-1),10)+1;
  $message=!$record&&!$saved->wasRecentlyCreated?'Permintaan ini sudah diproses sebelumnya. Tidak ada catatan atau foto baru; periksa konten yang sudah tersimpan.':PublicContent::SECTIONS[$section][1].' berhasil disimpan. Beranda diperbarui setelah dimuat ulang.';
  return redirect()->route(PublicContent::singleton($section)?'admin.content.index':'admin.content.list',PublicContent::singleton($section)?[]:['section'=>$section,'page'=>$page])->with('success',$message);
 }
 public function confirm(Request $request,string $section,PublicContent $content){
  $this->section($section);abort_unless($content->section===$section&&!PublicContent::singleton($section),404);$data=$this->data($section);$mode='visibility';$record=$content;$visible=!$record->is_visible;$requestKey=$this->nonce($request,$section,$record,'visibility',$visible);return view('content.list',$data+compact('record','visible','mode','requestKey'));
 }
 public function visibility(Request $request,string $section,PublicContent $content,PublicContentService $service){
  $this->section($section);abort_unless($content->section===$section&&!PublicContent::singleton($section),404);$request->validate(['confirmed'=>'accepted']);$entry=$this->verify($request,$section,$content,'visibility');$service->visibility($request->user(),$content,$entry['visible'],$entry['version']);
  $page=intdiv(max(0,PublicContent::where('section',$section)->where('id','<=',$content->id)->count()-1),10)+1;
  return redirect()->route('admin.content.list',['section'=>$section,'page'=>$page])->with('success',$entry['visible']?'Konten ditampilkan di beranda.':'Konten disembunyikan dari beranda. Catatan tetap disimpan.');
 }
}
