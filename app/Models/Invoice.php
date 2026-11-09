<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Invoice extends Model {
 protected $guarded=['id'];
 protected function casts(): array {return ['amount'=>'integer','due_date'=>'date','settled_at'=>'datetime'];}
 public function period(){return $this->belongsTo(DuesPeriod::class,'dues_period_id');}
 public function user(){return $this->belongsTo(User::class);}
 public function submissions(){return $this->hasMany(PaymentSubmission::class);}
 public function latestSubmission(){return $this->hasOne(PaymentSubmission::class)->latestOfMany();}
 public function pendingSubmission(){return $this->hasOne(PaymentSubmission::class)->where('status','pending');}
 public function getActionLabelAttribute(): string {return $this->status==='paid'?'Lihat Pembayaran':match($this->latestSubmission?->status){'pending'=>'Lihat Status','rejected'=>'Perbaiki Bukti',default=>'Bayar Tagihan'};}
 public function getActionUrlAttribute(): string {return route($this->status==='paid'||$this->latestSubmission?'resident.payments.status':'resident.invoices.pay',$this);}
}
