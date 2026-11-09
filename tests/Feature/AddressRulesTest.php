<?php
namespace Tests\Feature;

use App\Enums\Role;
use App\Models\{Rt, User, House, HouseResidency};
use App\Support\{HouseAddress, AddressDataAudit};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AddressRulesTest extends TestCase
{
    use RefreshDatabase;
    private function rt(int $n=1): Rt { return Rt::firstOrCreate(['number'=>$n],['is_active'=>true]); }
    private function officer(int $n=1): User { return User::factory()->create(['role'=>Role::Rt,'rt_id'=>$this->rt($n)->id,'registration_status'=>'approved']); }
    private function user(int $n=1,array $extra=[]): User { return User::factory()->create(array_merge(['rt_id'=>$this->rt($n)->id,'block'=>'A2','house_number'=>'2'],$extra)); }
    private function payload(array $extra=[]): array { return array_merge(['name'=>'Pemohon Baru','username'=>'pemohon.baru','password'=>'Password123','password_confirmation'=>'Password123','rt_id'=>$this->rt(2)->id,'block'=>'a02','house_number'=>'002'],$extra); }
    private function approve(User $officer,User $user): HouseResidency {
        $this->actingAs($officer)->post(route('rt.registrations.decide',$user),['decision'=>'approved'])->assertSessionHasNoErrors();
        return HouseResidency::where('user_id',$user->id)->firstOrFail();
    }
    private function editPayload(array $extra=[]): array { return array_merge(['name'=>'Nama Diperbarui','block'=>'F012','house_number'=>'00012','change_reason'=>'Koreksi alamat.'],$extra); }

    public function test_registration_normalizes_without_integer_overflow_and_displays_one_prefix(): void {
        $this->post('/daftar',$this->payload(['block'=>' Blok f0012 ','house_number'=>'No. 00012']))->assertSessionHasNoErrors()->assertRedirect(route('registration.status'));
        $user=User::where('username','pemohon.baru')->firstOrFail();
        $this->assertSame('F12',$user->block);$this->assertSame('12',$user->house_number);
        $this->get('/pendaftaran/status')->assertSee('Blok F12 No. 12')->assertDontSee('Blok Blok')->assertDontSee('No. No.');
        $this->assertSame('123456789012345678901234567890',HouseAddress::number('00123456789012345678901234567890'));
    }

    public static function invalidAddresses(): array { return [
        ['AA2','2','block'], ['A0','2','block'], ['A00','2','block'], ['2A','2','block'], ['A 2','2','block'], ['A-2','2','block'],
        ['A2','0','house_number'], ['A2','000','house_number'], ['A2','-2','house_number'], ['A2','2.5','house_number'], ['A2','2e1','house_number'], ['A2','+2','house_number'], ['A2',['2'],'house_number'],
    ]; }
    #[DataProvider('invalidAddresses')]
    public function test_invalid_address_cannot_create_an_account(mixed $block,mixed $number,string $field): void {
        $this->post('/daftar',$this->payload(['block'=>$block,'house_number'=>$number]))->assertSessionHasErrors($field)->assertSessionMissing('success');
        $this->assertDatabaseCount('users',0);$this->assertGuest();
    }

    public function test_pending_address_blocks_other_rt_without_disclosing_identity_or_creating_user(): void {
        $owner=$this->user(extra:['name'=>'Nama Privat Pemilik','username'=>'username.privat']);
        $this->from('/daftar')->post('/daftar',$this->payload())->assertSessionHasErrors(['block'=>HouseAddress::CONFLICT])->assertSessionMissing('success');
        $this->get('/daftar')->assertSee(HouseAddress::CONFLICT)->assertDontSee($owner->name)->assertDontSee($owner->username);
        $this->assertDatabaseCount('users',1);$this->assertGuest();
    }

    public function test_active_resident_blocks_registration_across_rt(): void {
        $officer=$this->officer();$resident=$this->user();$this->approve($officer,$resident);
        $this->post('/keluar')->assertRedirect(route('login'));
        $this->post('/daftar',$this->payload())->assertSessionHasErrors(['block'=>HouseAddress::CONFLICT]);
        $this->assertDatabaseCount('users',2);$this->assertDatabaseCount('registration_reviews',1);
    }

    public function test_rejection_releases_address_but_resubmission_must_check_again(): void {
        $officer=$this->officer();$rejected=$this->user();
        $this->actingAs($officer)->post(route('rt.registrations.decide',$rejected),['decision'=>'rejected','reason'=>'Periksa alamat.'])->assertSessionHasNoErrors();
        $this->post('/keluar');$this->post('/daftar',$this->payload())->assertSessionHasNoErrors();
        $this->actingAs($rejected->fresh())->put('/pendaftaran/perbaiki',['name'=>$rejected->name,'rt_id'=>$rejected->rt_id,'block'=>'A02','house_number'=>'02'])->assertSessionHasErrors(['block'=>HouseAddress::CONFLICT]);
        $this->assertSame('rejected',$rejected->fresh()->registration_status);
        $this->put('/pendaftaran/perbaiki',['name'=>$rejected->name,'rt_id'=>$rejected->rt_id,'block'=>'f012','house_number'=>'0012'])->assertSessionHasNoErrors();
        $this->assertSame('F12',$rejected->fresh()->block);$this->assertSame('12',$rejected->fresh()->house_number);
        $this->assertSame('pending',$rejected->fresh()->registration_status);
    }

    public function test_moved_history_does_not_block_reuse_even_in_another_rt(): void {
        $officer=$this->officer();$user=$this->user();$old=$this->approve($officer,$user);
        $this->post(route('rt.houses.move',$old),['move_note'=>'Pindah keluar.'])->assertSessionHasNoErrors();
        $this->post('/keluar');$this->post('/daftar',$this->payload())->assertSessionHasNoErrors();
        $new=User::where('username','pemohon.baru')->firstOrFail();
        $this->approve($this->officer(2),$new);
        $this->assertDatabaseCount('house_residencies',2);$this->assertNotNull($old->fresh()->moved_at);
        $this->assertSame('approved',$user->fresh()->registration_status);
        $this->assertSame(1,House::whereNotNull('current_user_id')->count());
    }

    public function test_rt_edit_excludes_self_normalizes_and_blocks_pending_address_in_other_rt(): void {
        $officer=$this->officer();$user=$this->user();$record=$this->approve($officer,$user);
        $this->put(route('rt.houses.update',$record),$this->editPayload(['block'=>'a02','house_number'=>'02']))->assertSessionHasNoErrors();
        $this->assertSame('A2',$user->fresh()->block);$this->assertSame('2',$record->fresh()->house_number);
        $this->user(2,['block'=>'F12','house_number'=>'12']);
        $this->put(route('rt.houses.update',$record),$this->editPayload())->assertSessionHasErrors(['block'=>HouseAddress::CONFLICT],null,'edit');
        $this->assertSame('A2',$record->fresh()->block);
        $this->put(route('rt.houses.update',$record),$this->editPayload(['block'=>'AA2']))->assertSessionHasErrors(['block'],null,'edit');
    }

    public function test_rt_correction_does_not_reassign_account_to_a_different_vacant_house(): void {
        $officer=$this->officer();$first=$this->user();$old=$this->approve($officer,$first);
        $this->post(route('rt.houses.move',$old),['move_note'=>'Pindah.'])->assertSessionHasNoErrors();
        $second=$this->user(extra:['block'=>'B3','house_number'=>'14']);$current=$this->approve($officer,$second);
        $this->put(route('rt.houses.update',$current),$this->editPayload(['block'=>'A2','house_number'=>'02']))->assertSessionHasErrors(['block'],null,'edit');
        $this->assertNotSame($old->house_id,$current->fresh()->house_id);
        $this->assertNotNull($old->fresh()->moved_at);$this->assertDatabaseCount('houses',2);$this->assertDatabaseCount('house_residencies',2);
        $this->assertSame($second->id,$current->fresh()->house->current_user_id);
    }

    public function test_approval_rechecks_legacy_duplicate_applications_across_rt(): void {
        $officer=$this->officer();$user=$this->user();$other=$this->user(2,['block'=>'Blok A02','house_number'=>'No. 002']);
        $this->actingAs($officer)->post(route('rt.registrations.decide',$user),['decision'=>'approved'])->assertSessionHasErrors(['block'=>HouseAddress::CONFLICT]);
        $this->assertDatabaseCount('houses',0);$this->assertDatabaseCount('registration_reviews',0);
        $this->assertSame('pending',$user->fresh()->registration_status);
        $this->actingAs($this->officer(2))->post(route('rt.registrations.decide',$other),['decision'=>'rejected','reason'=>'Perlu perbaikan.'])->assertSessionHasNoErrors();
        $this->approve($officer,$user);$this->assertDatabaseCount('houses',1);
    }

    public function test_normalization_migration_preserves_ids_and_defers_collisions_without_merging(): void {
        $original=DB::getDefaultConnection();
        config(['database.connections.address_migration_test'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]]);
        DB::setDefaultConnection('address_migration_test');
        try {
            (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
            DB::table('rts')->insert([['id'=>1,'number'=>1],['id'=>2,'number'=>2]]);
            foreach([[10,1,'Blok b003','No. 0014'],[11,1,'Blok D04','02'],[12,2,'D4','No. 2']] as [$id,$rt,$block,$number]) {
                DB::table('users')->insert(['id'=>$id,'name'=>'Legacy','username'=>'legacy'.$id,'password'=>'unchanged-hash','role'=>'warga','rt_id'=>$rt,'block'=>$block,'house_number'=>$number,'registration_status'=>$id===10?'approved':'pending']);
            }
            DB::table('houses')->insert(['id'=>20,'rt_id'=>1,'block'=>'Blok b003','house_number'=>'No. 0014','current_user_id'=>10]);
            (require database_path('migrations/2026_10_29_000003_create_house_residencies.php'))->up();
            (require database_path('migrations/2026_10_29_000004_normalize_addresses_and_add_write_lock.php'))->up();
            $this->assertSame('B3',DB::table('users')->where('id',10)->value('block'));
            $this->assertSame('14',DB::table('houses')->where('id',20)->value('house_number'));
            $this->assertSame('B3',DB::table('house_residencies')->value('block'));
            $this->assertSame('unchanged-hash',DB::table('users')->where('id',10)->value('password'));
            $this->assertSame('Blok D04',DB::table('users')->where('id',11)->value('block'));
            $this->assertCount(1,AddressDataAudit::inspect()['issues']);
            $this->assertSame(3,DB::table('users')->count());$this->assertSame(1,DB::table('houses')->count());
        } finally { DB::setDefaultConnection($original);DB::purge('address_migration_test'); }
    }
}
