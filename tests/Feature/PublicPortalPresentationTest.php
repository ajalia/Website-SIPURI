<?php
namespace Tests\Feature;

use App\Enums\Role;
use App\Models\{Rt, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPortalPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_shows_only_signed_in_resident_and_escapes_rt_notes(): void
    {
        $rt = Rt::create(['number'=>2, 'is_active'=>true]);
        $resident = User::factory()->create(['name'=>'Warga Pemilik Sesi', 'rt_id'=>$rt->id, 'block'=>'B3', 'house_number'=>'14', 'registration_status'=>'rejected', 'rejection_reason'=>'<script>alert("test")</script> Periksa alamat.']);
        $other = User::factory()->create(['name'=>'Warga Lain Rahasia', 'rt_id'=>$rt->id]);
        $this->actingAs($resident)->get('/pendaftaran/status')->assertOk()
            ->assertSee($resident->name)->assertSee($resident->username)->assertSee('RT 02')
            ->assertDontSee($other->name)->assertDontSee($other->username)
            ->assertSee($resident->rejection_reason)->assertDontSee('<script>alert(', false)
            ->assertSee(route('registration.edit'), false);
        $this->get('/pendaftaran/perbaiki')->assertOk()->assertSee($resident->rejection_reason)
            ->assertSee('value="'.$resident->username.'"', false)->assertSee('readonly', false);
    }

    public function test_resubmitted_data_returns_to_pending_view_without_changing_identity(): void
    {
        $rt = Rt::create(['number'=>1, 'is_active'=>true]);
        $resident = User::factory()->create(['rt_id'=>$rt->id, 'registration_status'=>'rejected', 'rejection_reason'=>'Nomor rumah perlu diperbaiki.']);
        $username = $resident->username;
        $this->actingAs($resident)->put('/pendaftaran/perbaiki', [
            'name'=>'Nama Setelah Perbaikan', 'rt_id'=>$rt->id, 'block'=>'B4', 'house_number'=>'22',
            'username'=>'username-diabaikan', 'role'=>'admin',
        ])->assertRedirect(route('registration.status'));
        $this->get('/pendaftaran/status')->assertOk()->assertSee('Nama Setelah Perbaikan')
            ->assertSee('Blok B4 No. 22')->assertSee('Menunggu Verifikasi RT')
            ->assertSee('Perbaikan berhasil diajukan.')
            ->assertDontSee('Perbaiki Pendaftaran')->assertDontSee('Nomor rumah perlu diperbaiki.');
        $this->assertSame($username, $resident->fresh()->username);
        $this->assertSame(Role::Resident, $resident->fresh()->role);
    }

    public function test_invalid_registration_preserves_values_and_does_not_claim_success(): void
    {
        $rt = Rt::create(['number'=>1, 'is_active'=>true]);
        $this->from('/daftar')->post('/daftar', [
            'name'=>'Nama Belum Terkirim', 'username'=>'nama.uji', 'rt_id'=>$rt->id,
            'block'=>'B3', 'house_number'=>'14', 'password'=>'terlalu-pendek', 'password_confirmation'=>'berbeda',
        ])->assertSessionHasErrors('password')->assertRedirect('/daftar');
        $this->get('/daftar')->assertOk()->assertSee('Nama Belum Terkirim')
            ->assertSee('Konfirmasi kata sandi tidak cocok.')
            ->assertDontSee('Pendaftaran Berhasil Dikirim!')
            ->assertDontSee('value="terlalu-pendek"', false);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_shared_header_changes_with_session_and_public_styles_stay_out_of_dashboard(): void
    {
        $this->get('/')->assertOk()->assertSee(route('login'), false)
            ->assertSee('Kuliner di Puri Indah')->assertDontSee('Kos Lingkungan')
            ->assertDontSee('cdn.tailwindcss.com')->assertDontSee('fonts.googleapis.com');
        $rt = Rt::create(['number'=>1, 'is_active'=>true]);
        $user = User::factory()->create(['rt_id'=>$rt->id, 'role'=>Role::Rt, 'registration_status'=>'approved']);
        $this->actingAs($user)->get('/')->assertOk()->assertSee('Buka Portal Anda');
        $this->get('/rt')->assertOk()->assertDontSee('assets/stitch.css')->assertDontSee('assets/login.css');
        $user->must_change_password = true;
        $user->save();
        $this->get('/kata-sandi-awal')->assertOk()->assertSee(route('logout'), false)
            ->assertSee('name="_token"', false)->assertSee('name="current_password"', false)
            ->assertDontSee('Kata Sandi Berhasil Diperbarui');
    }
}
