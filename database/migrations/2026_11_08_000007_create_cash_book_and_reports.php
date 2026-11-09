<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
 public function up(): void {
  Schema::create('cash_books',function(Blueprint $t){$t->engine='InnoDB';$t->unsignedTinyInteger('id')->primary();$t->date('starts_on')->nullable();$t->bigInteger('opening_balance')->nullable();$t->foreignId('configured_by')->nullable()->constrained('users')->restrictOnDelete();$t->timestamp('configured_at')->nullable();});
  DB::table('cash_books')->insert(['id'=>1]);
  Schema::create('cash_transactions',function(Blueprint $t){$t->engine='InnoDB';$t->id();$t->uuid('request_key')->unique();$t->string('kind',12);$t->date('transaction_date')->index();$t->string('description',1000);$t->unsignedBigInteger('amount');$t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();$t->timestamp('created_at');});
  Schema::create('cash_proofs',function(Blueprint $t){$t->engine='InnoDB';$t->id();$t->string('source_kind',12);$t->unsignedBigInteger('source_id');$t->string('path');$t->string('mime',50);$t->unsignedInteger('file_size');$t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();$t->timestamp('created_at');$t->index(['source_kind','source_id']);});
  Schema::create('financial_reports',function(Blueprint $t){$t->engine='InnoDB';$t->id();$t->unsignedSmallInteger('year');$t->unsignedTinyInteger('month');$t->unsignedInteger('version');$t->string('source_fingerprint',64);$t->string('request_hash',64)->unique();$t->json('summary');$t->json('public_rows');$t->foreignId('published_by')->constrained('users')->restrictOnDelete();$t->timestamp('published_at');$t->unique(['year','month','version']);});
 }
 public function down(): void {foreach(['financial_reports','cash_proofs','cash_transactions','cash_books'] as $table)Schema::dropIfExists($table);}
};
