<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PaymentSubmission extends Model {
 protected $guarded=['id'];
 protected function casts(): array {return ['amount'=>'integer','paid_on'=>'date','reviewed_at'=>'datetime'];}
 public function invoice(){return $this->belongsTo(Invoice::class);}
 public function reviewer(){return $this->belongsTo(User::class,'reviewed_by');}
}
