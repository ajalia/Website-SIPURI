@props(['status'])
<span class="rt-badge rt-badge-{{ $status }}">{{ ['pending'=>'Menunggu verifikasi','approved'=>'Disetujui','rejected'=>'Ditolak','current'=>'Masih Tinggal','moved'=>'Sudah Pindah'][$status] ?? $status }}</span>
