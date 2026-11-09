<?php
namespace App\Http\Requests;
use App\Support\HouseAddress;
use App\Support\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResidentRegistrationRequest extends FormRequest {
 public function authorize(): bool {return true;}
 protected function prepareForValidation(): void {
    $data=['block'=>HouseAddress::block($this->input('block')), 'house_number'=>HouseAddress::number($this->input('house_number'))];
    foreach(['name','username'] as $key) if(is_string($this->input($key))) $data[$key]=trim($this->input($key));
    if(isset($data['username']))$data['username']=mb_strtolower($data['username']);
    $this->merge($data);
 }
 public function rules(): array {
    $rules=['name'=>['required','string','max:150'],'rt_id'=>['required','integer',Rule::exists('rts','id')->where('is_active',true)],'block'=>HouseAddress::blockRules(),'house_number'=>HouseAddress::numberRules()];
    if($this->routeIs('register.store')) $rules += ['username'=>['required','string','min:3','max:50','regex:/\A[a-z0-9][a-z0-9._]*\z/',Rule::unique('users','username')],'password'=>PasswordPolicy::rules()];
    return $rules;
 }
 public function attributes(): array {return ['name'=>'nama lengkap','rt_id'=>'RT','block'=>'blok','house_number'=>'nomor rumah','username'=>'username','password'=>'kata sandi'];}
 public function messages(): array {return PasswordPolicy::messages()+['required'=>':attribute wajib diisi.','max'=>':attribute terlalu panjang.','min'=>':attribute minimal :min karakter.','block.regex'=>HouseAddress::BLOCK_HELP,'house_number.regex'=>'Nomor rumah harus bilangan bulat positif.','rt_id.exists'=>'Pilih RT yang tersedia.','username.unique'=>'Username sudah digunakan.','username.regex'=>'Gunakan huruf kecil, angka, titik, atau garis bawah tanpa spasi.','password.confirmed'=>'Konfirmasi kata sandi tidak cocok.'];}
}
