<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
 public function up(): void {
  Schema::create('public_content_locks',function(Blueprint $t){$t->engine='InnoDB';$t->unsignedTinyInteger('id')->primary();});DB::table('public_content_locks')->insert(['id'=>1]);
  Schema::create('public_contents',function(Blueprint $t){
   $t->engine='InnoDB';$t->id();$t->string('section',20)->index();$t->string('singleton_key',20)->nullable()->unique();$t->uuid('request_key')->unique();$t->string('name',160);$t->text('description')->nullable();$t->string('image_path')->nullable();$t->boolean('is_visible')->default(false);$t->unsignedInteger('version')->default(1);
   $t->string('job_title',120)->nullable();$t->string('area_level',2)->nullable();$t->foreignId('rt_id')->nullable()->constrained('rts')->restrictOnDelete();$t->string('block',100)->nullable();$t->string('house_number',30)->nullable();$t->string('opens_at',5)->nullable();$t->string('closes_at',5)->nullable();$t->foreignId('created_by')->constrained('users')->restrictOnDelete();$t->foreignId('updated_by')->constrained('users')->restrictOnDelete();$t->timestamps();$t->index(['section','is_visible','id']);
  });
 }
 public function down(): void {Schema::dropIfExists('public_contents');Schema::dropIfExists('public_content_locks');}
};
