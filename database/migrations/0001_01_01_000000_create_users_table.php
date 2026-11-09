<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
    Schema::create('rts', function(Blueprint $t){$t->id();$t->unsignedSmallInteger('number')->unique();$t->boolean('is_active')->default(true);$t->timestamps();});
    Schema::create('users', function(Blueprint $t){
        $t->id();$t->string('name',150);$t->string('username',50)->unique();$t->string('password');
        $t->string('role',20)->default('warga');$t->foreignId('rt_id')->nullable()->constrained('rts')->restrictOnDelete();
        $t->string('block',100)->nullable();$t->string('house_number',30)->nullable();
        $t->boolean('is_active')->default(true);$t->boolean('must_change_password')->default(false);
        $t->string('registration_status',20)->default('pending')->index();$t->text('rejection_reason')->nullable();
        $t->timestamp('approved_at')->nullable();$t->rememberToken();$t->timestamps();
        $t->index(['rt_id','registration_status']);
    });
    Schema::create('houses', function(Blueprint $t){
        $t->id();$t->foreignId('rt_id')->constrained('rts')->restrictOnDelete();$t->string('block',100);$t->string('house_number',30);
        $t->foreignId('current_user_id')->nullable()->unique()->constrained('users')->restrictOnDelete();$t->timestamps();
        $t->unique(['rt_id','block','house_number'],'houses_address_unique');
    });
    Schema::create('registration_reviews', function(Blueprint $t){
        $t->id();$t->foreignId('user_id')->constrained()->restrictOnDelete();$t->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
        $t->string('decision',20);$t->text('reason')->nullable();$t->json('submitted_data');$t->timestamps();
    });
    Schema::create('sessions',function(Blueprint $t){$t->string('id')->primary();$t->foreignId('user_id')->nullable()->index();$t->string('ip_address',45)->nullable();$t->text('user_agent')->nullable();$t->longText('payload');$t->integer('last_activity')->index();});
 }
 public function down(): void {Schema::dropIfExists('sessions');Schema::dropIfExists('registration_reviews');Schema::dropIfExists('houses');Schema::dropIfExists('users');Schema::dropIfExists('rts');}
};
