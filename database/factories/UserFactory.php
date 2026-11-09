<?php
namespace Database\Factories;
use App\Models\User;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
class UserFactory extends Factory {
 protected $model=User::class;
 public function definition(): array {return ['name'=>fake()->name(),'username'=>fake()->unique()->userName(),'password'=>Hash::make('password123'),'role'=>Role::Resident,'registration_status'=>'pending','is_active'=>true,'must_change_password'=>false,'remember_token'=>Str::random(10)];}
}
