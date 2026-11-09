@props(['status'])
<span class="finance-badge finance-{{ $status }}">{{ ['paid'=>'Lunas','unpaid'=>'Belum Lunas','pending'=>'Menunggu Verifikasi','rejected'=>'Bukti Ditolak','accepted'=>'Diterima'][$status] ?? $status }}</span>
