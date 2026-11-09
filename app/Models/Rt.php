<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Rt extends Model {
    protected $fillable=['number','is_active'];
    protected function casts(): array { return ['is_active'=>'boolean']; }
    public function label(): string { return 'RT '.str_pad((string)$this->number,2,'0',STR_PAD_LEFT); }
}
