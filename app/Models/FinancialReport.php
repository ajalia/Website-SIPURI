<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FinancialReport extends Model {
 public $timestamps=false;
 protected $guarded=['id'];
 protected function casts(): array {return ['year'=>'integer','month'=>'integer','version'=>'integer','summary'=>'array','public_rows'=>'array','published_at'=>'datetime'];}
 public function publisher(){return $this->belongsTo(User::class,'published_by');}
}
