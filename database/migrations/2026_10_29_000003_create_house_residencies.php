<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('house_residencies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('house_id')->constrained('houses')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->string('resident_name', 150);
            $t->string('block', 100);
            $t->string('house_number', 30);
            $t->timestamp('started_at')->nullable();
            $t->date('moved_at')->nullable()->index();
            $t->text('move_note')->nullable();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->index(['house_id', 'moved_at']);
        });
        Schema::create('residency_changes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('house_residency_id')->constrained('house_residencies')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->text('reason');
            $t->json('before_data');
            $t->json('after_data');
            $t->timestamps();
        });
        // Preserve existing houses and accounts; add historical records only.
        DB::table('houses')->whereNotNull('current_user_id')->orderBy('id')->chunkById(100, function ($houses) {
            foreach ($houses as $house) {
                $user = DB::table('users')->find($house->current_user_id);
                DB::table('house_residencies')->insert([
                    'house_id'=>$house->id, 'user_id'=>$user->id, 'resident_name'=>$user->name,
                    'block'=>$house->block, 'house_number'=>$house->house_number,
                    'started_at'=>$user->approved_at ?? $house->created_at,
                    'created_at'=>$house->created_at, 'updated_at'=>$house->updated_at,
                ]);
            }
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('residency_changes');
        Schema::dropIfExists('house_residencies');
    }
};
