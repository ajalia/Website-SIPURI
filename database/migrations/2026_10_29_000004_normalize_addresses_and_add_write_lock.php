<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use App\Support\AddressDataAudit;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('address_write_locks',function(Blueprint $t){ $t->unsignedTinyInteger('id')->primary(); });
        DB::table('address_write_locks')->insert(['id'=>1]);
        DB::transaction(function(){
            $plan=AddressDataAudit::inspect();
            // Ambiguous addresses are left untouched; never merge houses or accounts.
            foreach($plan['updates'] as $row) DB::table($row['table'])->where('id',$row['id'])->update(['block'=>$row['block'],'house_number'=>$row['house_number']]);
        });
    }
    public function down(): void { Schema::dropIfExists('address_write_locks'); }
};
