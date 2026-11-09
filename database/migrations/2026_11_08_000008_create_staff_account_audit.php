<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
 public function up(): void {
  Schema::create('staff_account_locks',function(Blueprint $t){$t->engine='InnoDB';$t->unsignedTinyInteger('id')->primary();});
  DB::table('staff_account_locks')->insert(['id'=>1]);
  Schema::create('staff_account_events',function(Blueprint $t){$t->engine='InnoDB';$t->id();$t->foreignId('actor_id')->constrained('users')->restrictOnDelete();$t->foreignId('target_id')->constrained('users')->restrictOnDelete();$t->string('kind',30);$t->string('request_hash',64)->unique();$t->json('before_state')->nullable();$t->json('after_state');$t->timestamp('created_at');});
 }
 public function down(): void {Schema::dropIfExists('staff_account_events');Schema::dropIfExists('staff_account_locks');}
};
