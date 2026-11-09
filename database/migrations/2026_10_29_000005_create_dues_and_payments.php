<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('payment_accounts',function(Blueprint $t){$t->unsignedTinyInteger('id')->primary();$t->string('bank_name',100);$t->string('account_number',50);$t->string('account_holder',150);$t->foreignId('updated_by')->constrained('users')->restrictOnDelete();$t->timestamps();});
  Schema::create('dues_periods',function(Blueprint $t){$t->id();$t->unsignedSmallInteger('year');$t->unsignedTinyInteger('month');$t->unsignedBigInteger('amount');$t->date('due_date');$t->unsignedInteger('target_count');$t->foreignId('issued_by')->constrained('users')->restrictOnDelete();$t->timestamp('issued_at');$t->timestamps();$t->unique(['year','month']);});
  Schema::create('invoices',function(Blueprint $t){
   $t->id();$t->foreignId('dues_period_id')->constrained('dues_periods')->restrictOnDelete();$t->foreignId('house_id')->constrained('houses')->restrictOnDelete();$t->foreignId('house_residency_id')->constrained('house_residencies')->restrictOnDelete();$t->foreignId('user_id')->constrained('users')->restrictOnDelete();
   $t->string('resident_name',150);$t->string('block',100);$t->string('house_number',30);$t->unsignedSmallInteger('rt_number');$t->unsignedBigInteger('amount');$t->date('due_date');$t->string('status',12)->default('unpaid')->index();$t->timestamp('settled_at')->nullable();$t->timestamps();
   $t->unique(['dues_period_id','house_id']);$t->unique(['dues_period_id','block','house_number'],'invoices_period_address_unique');$t->index(['user_id','status']);
  });
  Schema::create('payment_submissions',function(Blueprint $t){
   $t->id();$t->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();$t->foreignId('user_id')->constrained('users')->restrictOnDelete();$t->date('paid_on');$t->unsignedBigInteger('amount');$t->string('proof_path');$t->string('mime',50);$t->unsignedInteger('file_size');$t->string('bank_name',100);$t->string('account_number',50);$t->string('account_holder',150);$t->string('status',12)->default('pending')->index();
   $t->foreignId('pending_invoice_id')->nullable()->unique()->constrained('invoices')->restrictOnDelete();$t->foreignId('accepted_invoice_id')->nullable()->unique()->constrained('invoices')->restrictOnDelete();
   $t->text('rejection_reason')->nullable();$t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();$t->timestamp('reviewed_at')->nullable();$t->timestamps();
  });
  Schema::create('dues_income_entries',function(Blueprint $t){$t->id();$t->foreignId('invoice_id')->unique()->constrained('invoices')->restrictOnDelete();$t->foreignId('payment_submission_id')->unique()->constrained('payment_submissions')->restrictOnDelete();$t->unsignedBigInteger('amount');$t->date('transaction_date');$t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();$t->timestamp('recorded_at');$t->timestamps();});
 }
 public function down(): void {foreach(['dues_income_entries','payment_submissions','invoices','dues_periods','payment_accounts'] as $table)Schema::dropIfExists($table);}
};
