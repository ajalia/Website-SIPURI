<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class HouseResidency extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['started_at'=>'datetime', 'moved_at'=>'date']; }
    public function house() { return $this->belongsTo(House::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by'); }
}
