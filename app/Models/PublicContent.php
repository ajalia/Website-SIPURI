<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
class PublicContent extends Model {
 public const SECTIONS=['banner'=>['Banner Beranda','Banner','Judul, deskripsi singkat, dan foto utama.'],'profil'=>['Profil Kawasan','Profil','Informasi dan foto pengenalan kawasan.'],'fasilitas'=>['Fasilitas Lingkungan','Fasilitas','Daftar fasilitas beserta foto dan keterangannya.'],'pengurus'=>['Pengurus Lingkungan','Pengurus','Profil pengurus yang ditampilkan kepada publik.'],'kuliner'=>['Kuliner','Kuliner','Tempat makan, lokasi, keterangan, dan jam operasional.']];
 protected $guarded=['id'];
 protected function casts(): array {return ['is_visible'=>'boolean','version'=>'integer','rt_id'=>'integer'];}
 public static function singleton(string $section): bool {return in_array($section,['banner','profil'],true);}
 public static function owned(?string $path): bool {return is_string($path)&&preg_match('~\Aimages/[a-zA-Z0-9]{40}\.(?:jpg|jpeg|png|webp)\z~D',$path)===1;}
 public function imageAvailable(): bool {return self::owned($this->image_path)&&Storage::disk('public_content')->exists($this->image_path);}
 public function imageUrl(): ?string {return $this->imageAvailable()?route('public-content.image',['filename'=>basename($this->image_path)]):null;}
 public function rt(){return $this->belongsTo(Rt::class);}
 public function region(): string {return $this->area_level==='RT'?($this->rt?->label()??'RT belum ditentukan').' · RW 07':'RW 07';}
 public function hours(): string {return $this->opens_at.'–'.$this->closes_at.($this->closes_at<$this->opens_at?' (tutup hari berikutnya)':($this->closes_at===$this->opens_at?' (jam buka dan tutup sama)':''));}
}
