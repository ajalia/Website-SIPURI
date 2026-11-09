@extends($layout)
@section('title','Sandi Sementara Dibuat')
@section('page-title','Pemulihan Akun')
@section('content')
<section class="account-card surface"><h1>Sandi Sementara Dibuat</h1><p>Untuk akun <strong>{{ $target->username }}</strong>. Sampaikan secara pribadi kepada pemilik yang telah diverifikasi.</p><div class="alert alert-warning">Ditampilkan satu kali pada respons ini. Jangan simpan dalam foto, file, pesan grup, atau catatan publik. Halaman tidak menyediakan fitur menampilkan ulang.</div><label class="form-label" for="temporary-password">Kata Sandi Sementara</label><input class="form-control account-temporary" type="text" id="temporary-password" data-ephemeral-secret readonly value="{{ $temporary }}" autocomplete="off" spellcheck="false"><p class="form-text">Seluruh sesi lama telah dicabut. Akun wajib mengganti kata sandi setelah masuk; status aktif dan pendaftaran tetap.</p><a class="btn btn-primary mt-3" href="{{ route(auth()->user()->role===\App\Enums\Role::Admin?($target->role===\App\Enums\Role::Resident?'admin.resident-recovery':'admin.accounts'):'rt.registrations.index') }}">Selesai</a></section>
@endsection
