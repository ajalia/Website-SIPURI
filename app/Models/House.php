<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class House extends Model {
    protected $fillable=['rt_id','block','house_number','current_user_id'];
    public function rt() { return $this->belongsTo(Rt::class); }
    public function resident() { return $this->belongsTo(User::class,'current_user_id'); }
}
