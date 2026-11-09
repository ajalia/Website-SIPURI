<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DuesPeriod extends Model {
 protected $guarded=['id'];
 protected function casts(): array {return ['amount'=>'integer','year'=>'integer','month'=>'integer','due_date'=>'date','issued_at'=>'datetime'];}
 public function invoices(){return $this->hasMany(Invoice::class);}
 public function getLabelAttribute(): string {return \App\Support\Dues::months()[$this->month].' '.$this->year;}
}
