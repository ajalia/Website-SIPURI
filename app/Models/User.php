<?php
namespace App\Models;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
class User extends Authenticatable {
    use HasFactory, Notifiable;
    protected $fillable = ['name','username','password','rt_id','block','house_number'];
    protected $hidden = ['password','remember_token'];
    protected $attributes = ['is_active'=>true,'must_change_password'=>false,'registration_status'=>'pending','role'=>'warga'];
    protected function casts(): array { return ['credentials_version'=>'integer','rt_id'=>'integer','password'=>'hashed','role'=>Role::class,'is_active'=>'boolean','must_change_password'=>'boolean','approved_at'=>'datetime']; }
    public function rt(): BelongsTo { return $this->belongsTo(Rt::class); }
    public function house(): HasOne { return $this->hasOne(House::class, 'current_user_id'); }
    public function latestResidency() { return $this->hasOne(HouseResidency::class)->latestOfMany(); }
    public function isApproved(): bool { return $this->role !== Role::Resident || $this->registration_status === 'approved'; }
}
