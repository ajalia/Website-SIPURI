<?php
namespace Tests\Feature;

use App\Enums\Role;
use App\Models\{Rt, User, House, HouseResidency};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RtResidentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function rt(int $number=1): Rt { return Rt::firstOrCreate(['number'=>$number], ['is_active'=>true]); }
    private function officer(int $number=1): User { return User::factory()->create(['role'=>Role::Rt, 'rt_id'=>$this->rt($number)->id, 'registration_status'=>'approved']); }
    private function resident(int $number=1, array $extra=[]): User { return User::factory()->create(array_merge(['rt_id'=>$this->rt($number)->id, 'block'=>'B3', 'house_number'=>'14'], $extra)); }
    private function approve(User $officer, User $user): HouseResidency {
        $this->actingAs($officer)->post(route('rt.registrations.decide', $user), ['decision'=>'approved'])->assertSessionHasNoErrors();
        return HouseResidency::where('user_id', $user->id)->latest('id')->firstOrFail();
    }
    private function payload(array $extra=[]): array { return array_merge(['name'=>'Nama Diperbarui', 'block'=>'Blok C2', 'house_number'=>'No. 025', 'change_reason'=>'Koreksi sesuai konfirmasi warga.'], $extra); }

    public function test_approval_creates_one_residency_without_retyping_and_rejects_repeated_approval(): void {
        $officer=$this->officer(); $user=$this->resident();
        $record=$this->approve($officer,$user);
        $this->assertSame($user->name,$record->resident_name);
        $this->assertSame('B3',$record->block);
        $this->assertSame($user->id,$record->house->current_user_id);
        $this->post(route('rt.registrations.decide',$user),['decision'=>'approved'])->assertSessionHasErrors('decision');
        $this->assertDatabaseCount('house_residencies',1);
        $this->assertDatabaseCount('registration_reviews',1);
    }

    public function test_rt_can_edit_with_audit_while_username_region_hash_and_account_status_stay_unchanged(): void {
        $officer=$this->officer(); $user=$this->resident(); $record=$this->approve($officer,$user);
        $before=$user->fresh();
        $this->from(route('rt.houses.show',$record))->put(route('rt.houses.update',$record),$this->payload(['username'=>'hacked','rt_id'=>$this->rt(2)->id,'role'=>'admin','is_active'=>false]))
            ->assertRedirect(route('rt.houses.show',$record))->assertSessionHasNoErrors();
        $user->refresh(); $record->refresh();
        $this->assertSame('Nama Diperbarui',$user->name);
        $this->assertSame('C2',$record->block); $this->assertSame('25',$record->house_number);
        $this->assertSame('C2',$record->house->block);
        foreach(['username','rt_id','role','password','is_active','registration_status'] as $key) $this->assertEquals($before->$key,$user->$key);
        $log=DB::table('residency_changes')->first();
        $this->assertSame($officer->id,$log->actor_id);
        $this->assertSame('B3',json_decode($log->before_data,true)['block']);
        $this->assertSame('C2',json_decode($log->after_data,true)['block']);
        $this->get(route('rt.houses.show',$record))->assertOk()->assertSee('Perubahan berhasil disimpan.')->assertSee('Nama Diperbarui')->assertDontSee('data-auto-open',false);
    }

    public function test_validation_and_duplicate_address_do_not_partially_update_records_and_reopen_edit_modal(): void {
        $officer=$this->officer(); $user=$this->resident(); $record=$this->approve($officer,$user);
        $other=$this->resident(extra:['block'=>'C2','house_number'=>'25']); $this->approve($officer,$other);
        $this->from(route('rt.houses.show',$record))->put(route('rt.houses.update',$record),$this->payload())->assertSessionHasErrors(['block'],null,'edit');
        $this->get(route('rt.houses.show',$record))->assertOk()->assertSee('data-auto-open',false)->assertSee('Nama Diperbarui');
        $this->assertSame('B3',$record->fresh()->block); $this->assertSame($user->name,$user->fresh()->name);
        $this->assertDatabaseCount('residency_changes',0);
        $this->put(route('rt.houses.update',$record),$this->payload(['change_reason'=>' ']))->assertSessionHasErrors(['change_reason'],null,'edit');
        $this->put(route('rt.houses.update',$record),$this->payload(['name'=>['invalid']]))->assertSessionHasErrors(['name'],null,'edit');
    }

    public function test_other_rt_and_other_roles_cannot_read_or_modify_residency(): void {
        $owner=$this->officer(); $user=$this->resident(); $record=$this->approve($owner,$user);
        $other=$this->officer(2); $this->actingAs($other);
        $this->get(route('rt.houses.show',$record))->assertForbidden();
        $this->put(route('rt.houses.update',$record),$this->payload())->assertForbidden();
        $this->post(route('rt.houses.move',$record),['move_note'=>'Pindah'])->assertForbidden();
        $this->get('/rt/data-warga')->assertOk()->assertDontSee($user->name);
        $this->get('/rt/pendaftaran?status=all')->assertOk()->assertDontSee($user->name);
        $this->actingAs($user->fresh())->put(route('rt.houses.update',$record),$this->payload())->assertForbidden();
        $noRegion=User::factory()->create(['role'=>Role::Rt,'rt_id'=>null,'registration_status'=>'approved']);
        $this->actingAs($noRegion)->get('/rt/data-warga')->assertForbidden();
        $this->get('/rt')->assertForbidden();
        $this->assertDatabaseCount('residency_changes',0);
    }

    public function test_move_preserves_history_account_and_reviews_but_releases_active_house(): void {
        $officer=$this->officer(); $user=$this->resident(); $record=$this->approve($officer,$user);
        $account=$user->fresh()->only(['id','password','username','role','registration_status','is_active']);
        $this->from(route('rt.houses.show',$record))->post(route('rt.houses.move',$record),['move_note'=>'Pindah ke luar lingkungan.'])
            ->assertRedirect(route('rt.houses.show',$record))->assertSessionHasNoErrors();
        $record->refresh(); $this->assertNotNull($record->moved_at); $this->assertSame($officer->id,$record->recorded_by);
        $this->assertNull($record->house->current_user_id);
        $this->assertEquals($account,$user->fresh()->only(array_keys($account)));
        $this->assertDatabaseCount('users',2); $this->assertDatabaseCount('houses',1); $this->assertDatabaseCount('registration_reviews',1);
        $this->get(route('rt.houses.show',$record))->assertOk()->assertSee('Sudah Pindah')->assertSee('Pindah ke luar lingkungan.')->assertDontSee('data-bs-target="#move"',false);
        $this->post(route('rt.houses.move',$record),['move_note'=>'Duplikat'])->assertSessionHasErrors(['record'],null,'move');
        $this->put(route('rt.houses.update',$record),$this->payload())->assertSessionHasErrors(['record'],null,'edit');
        $this->actingAs($user->fresh())->get('/warga')->assertOk()->assertSee('B3');
    }

    public function test_move_requires_note_and_valid_date_and_reopens_only_move_form(): void {
        $officer=$this->officer(); $user=$this->resident(); $record=$this->approve($officer,$user);
        $this->from(route('rt.houses.show',$record))->post(route('rt.houses.move',$record),['move_note'=>' '])->assertSessionHasErrors(['move_note'],null,'move');
        $this->post(route('rt.houses.move',$record),['moved_at'=>now('Asia/Jakarta')->addDay()->toDateString(),'move_note'=>'Catatan aman'])->assertSessionHasErrors(['moved_at'],null,'move');
        $this->get(route('rt.houses.show',$record))->assertOk()->assertSee('data-auto-open',false)->assertSee('Catatan aman');
        $this->post(route('rt.houses.move',$record),['moved_at'=>'2000-01-01','move_note'=>'Catatan'])->assertSessionHasErrors(['moved_at'],null,'move');
        $this->assertNull($record->fresh()->moved_at); $this->assertSame($user->id,$record->house->current_user_id);
    }

    public function test_house_can_have_new_approved_occupant_without_overwriting_departed_history(): void {
        $officer=$this->officer(); $first=$this->resident(extra:['name'=>'Penghuni Lama']); $old=$this->approve($officer,$first);
        $this->post(route('rt.houses.move',$old),['move_note'=>'Pindah'])->assertSessionHasNoErrors();
        $second=$this->resident(extra:['name'=>'Penghuni Baru']); $new=$this->approve($officer,$second);
        $this->assertSame($old->house_id,$new->house_id); $this->assertNotNull($old->fresh()->moved_at);
        $this->assertSame('Penghuni Lama',$old->fresh()->resident_name); $this->assertDatabaseCount('houses',1); $this->assertDatabaseCount('house_residencies',2);
        $this->get('/rt/data-warga?status=moved')->assertSee('Penghuni Lama')->assertDontSee('Penghuni Baru');
        $this->get('/rt/data-warga?status=current')->assertSee('Penghuni Baru')->assertDontSee('Penghuni Lama');
        $this->get('/rt')->assertViewHas('houses',1)->assertViewHas('pending',0);
    }

    public function test_filters_pagination_and_dashboard_counts_remain_scoped_to_rt(): void {
        $officer=$this->officer();
        for($i=1;$i<=17;$i++) $this->resident(extra:['name'=>'Warga Uji '.$i, 'house_number'=>(string)$i]);
        $this->resident(2,['name'=>'Pendaftar RT Lain']);
        $this->actingAs($officer)->get('/rt')->assertViewHas('pending',17)->assertViewHas('houses',0)->assertDontSee('Pendaftar RT Lain');
        $this->get('/rt/pendaftaran?status=all&q=Warga')->assertOk()->assertViewHas('applicants',fn($p)=>$p->total()===17 && $p->count()===10)->assertSee('status=all',false)->assertSee('q=Warga',false);
        $this->get('/rt/pendaftaran?status=all&q=Warga&page=2')->assertOk()->assertViewHas('applicants',fn($p)=>$p->count()===7);
        $this->get('/rt/pendaftaran?status=unknown')->assertSessionHasErrors('status');
    }

    public function test_resident_directory_paginates_and_preserves_search_and_status_filters(): void {
        $officer=$this->officer();
        for($i=1;$i<=17;$i++) {
            $user=$this->resident(extra:['name'=>'Penghuni Uji '.$i,'house_number'=>(string)$i,'registration_status'=>'approved']);
            $house=House::create(['rt_id'=>$officer->rt_id,'block'=>'B3','house_number'=>(string)$i,'current_user_id'=>$user->id]);
            HouseResidency::create(['house_id'=>$house->id,'user_id'=>$user->id,'resident_name'=>$user->name,'block'=>'B3','house_number'=>(string)$i,'started_at'=>now()]);
        }
        $this->actingAs($officer)->get('/rt/data-warga?q=Penghuni&status=current')->assertOk()
            ->assertViewHas('records',fn($p)=>$p->total()===17 && $p->count()===15)
            ->assertSee('status=current',false)->assertSee('q=Penghuni',false);
        $this->get('/rt/data-warga?q=Penghuni&status=current&page=2')->assertOk()->assertViewHas('records',fn($p)=>$p->count()===2);
        $this->get('/rt/data-warga?status=moved')->assertOk()->assertViewHas('records',fn($p)=>$p->total()===0);
    }
    public function test_additive_migration_backfills_existing_house_without_modifying_source_rows(): void {
        $original=DB::getDefaultConnection();
        config(['database.connections.backfill_test'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]]);
        DB::setDefaultConnection('backfill_test');
        try {
            $base=require database_path('migrations/0001_01_01_000000_create_users_table.php'); $base->up();
            DB::table('rts')->insert(['id'=>12,'number'=>2,'is_active'=>true]);
            DB::table('users')->insert(['id'=>40,'name'=>'Existing Resident','username'=>'existing','password'=>'preserved-hash','role'=>'warga','rt_id'=>12,'registration_status'=>'approved','approved_at'=>'2026-01-01 00:00:00']);
            DB::table('houses')->insert(['id'=>20,'rt_id'=>12,'block'=>'B3','house_number'=>'14','current_user_id'=>40]);
            $migration=require database_path('migrations/2026_10_29_000003_create_house_residencies.php'); $migration->up();
            $this->assertSame(1,DB::table('house_residencies')->count());
            $row=DB::table('house_residencies')->first();
            $this->assertSame(20,$row->house_id); $this->assertSame(40,$row->user_id);
            $this->assertSame('Existing Resident',$row->resident_name);
            $this->assertSame('preserved-hash',DB::table('users')->value('password'));
            $this->assertSame(40,DB::table('houses')->value('current_user_id'));
        } finally { DB::setDefaultConnection($original); DB::purge('backfill_test'); }
    }
}
