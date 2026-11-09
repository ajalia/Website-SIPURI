<?php
use Illuminate\Support\Facades\Artisan;
use App\Models\{Rt,User};
use App\Enums\Role;
use Illuminate\Support\Str;
Artisan::command('sipuri:demo',function(){
 if(!app()->environment(['local','testing'])){$this->error('Akun demo hanya boleh dibuat di lingkungan lokal.');return 1;}
 foreach(range(1,5)as$n)Rt::firstOrCreate(['number'=>$n],['is_active'=>true]);
 $created=[];
 foreach([['admin.demo','Admin Demo',Role::Admin,null],['rt01.demo','Pengurus RT 01 Demo',Role::Rt,Rt::where('number',1)->value('id')],['rt02.demo','Pengurus RT 02 Demo',Role::Rt,Rt::where('number',2)->value('id')],['bendahara.demo','Bendahara Demo',Role::Treasurer,null]]as[$username,$name,$role,$rt]){
  if(User::where('username',$username)->exists())continue;
  $password=\App\Support\PasswordPolicy::temporary();$u=new User(['name'=>$name,'username'=>$username,'password'=>$password,'rt_id'=>$rt]);$u->role=$role;$u->registration_status='approved';$u->must_change_password=true;$u->save();$created[]="$username | $password";
 }
 if($created){$this->warn('Kredensial demo ditampilkan sekali dan tidak disimpan ke file.');foreach($created as $credential)$this->line($credential);}
 else $this->info('Akun demo sudah ada; kata sandi tidak diubah.');
 $this->warn('RT 01-05 adalah asumsi prototipe, bukan data wilayah resmi.');
})->purpose('Menyiapkan data demo lokal tanpa menimpa akun yang sudah ada');

Artisan::command('sipuri:recover-admin',function(){
 if(!$this->input->isInteractive()||!defined('STDIN')||!stream_isatty(STDIN)){$this->error('Pemulihan Admin harus dijalankan secara lokal dan interaktif.');return 1;}
 $username=$this->ask('Username Admin');
 $admin=User::where('username',mb_strtolower(trim((string)$username)))->where('role','admin')->first();
 if(!$admin){$this->error('Akun Admin tidak ditemukan.');return 1;}
 if(!$this->confirm('Anda sudah memverifikasi identitas pemilik dan berwenang memulihkan akun Admin ini?',false))return 1;
 $temporary=app(\App\Services\CredentialService::class)->recoverAdminLocally($admin);
 $this->warn('Kata sandi sementara berikut ditampilkan satu kali. Sampaikan secara pribadi; jangan simpan ke file atau log.');
 $this->line($temporary);unset($temporary);
 $this->info('Seluruh sesi dicabut. Akun wajib mengganti kata sandi setelah login. Status aktif tetap dipertahankan.');
 return 0;
})->purpose('Memulihkan akun Admin melalui terminal lokal interaktif, tanpa endpoint publik');
