<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('users',function(Blueprint $t){$t->unsignedInteger('credentials_version')->default(0);});
  Schema::create('credential_events',function(Blueprint $t){$t->id();$t->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();$t->foreignId('target_id')->constrained('users')->restrictOnDelete();$t->string('actor_label',150)->nullable();$t->string('kind',30);$t->string('nonce_hash',64)->unique();$t->timestamp('created_at');});
 }
 public function down(): void {Schema::dropIfExists('credential_events');Schema::table('users',fn(Blueprint $t)=>$t->dropColumn('credentials_version'));}
};
