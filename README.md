# SIPURI — tahap awal implementasi

Laravel 12, Laravel Blade, Bootstrap 5.3.8, CSS, dan JavaScript seperlunya. Tidak memakai React, Tailwind, atau starter kit SPA untuk halaman aplikasi. PHP lokal yang ditemukan adalah 8.2.12; Laravel 12 mendukung PHP 8.2. Referensi: https://laravel.com/docs/12.x/releases

## Yang sudah berfungsi

- Login memakai username/kata sandi, logout POST, sesi diregenerasi, hash kata sandi, token CSRF.
- Pembatasan percobaan login: lima kegagalan per username/IP mengunci percobaan selama 15 menit.
- Empat peran: Admin, Pengurus RT, Bendahara, Warga. Akses diperiksa server, bukan hanya menu.
- Akun pengurus dengan kata sandi awal harus mengganti kata sandi sebelum masuk dashboard.
- Registrasi warga, status menunggu, alasan penolakan, formulir perbaikan, dan pengajuan ulang.
- RT hanya bisa melihat dan memutuskan pendaftaran di wilayahnya.
- Persetujuan mencatat rumah dari data pendaftaran yang sudah diperiksa; RT tidak perlu memasukkan data warga terlebih dahulu.
- Pencegahan rumah yang sama ditautkan ke dua akun warga aktif; keputusan disimpan dalam satu transaksi database dengan riwayat pemeriksaan.
- Daftar rumah yang disetujui dan beranda warga dengan data rumahnya.
- Komponen header/sidebar bersama, navigasi drawer mobile, dialog konfirmasi Bootstrap, serta font Inter dan aset Bootstrap lokal.

## Belum selesai

Pengelolaan akun pengurus melalui Admin, reset/lupa sandi, ubah data/pindah rumah, tagihan bulanan serentak, pembayaran dan bukti, kas, publikasi laporan, serta pengelolaan konten publik belum diimplementasikan. Dashboard Admin/Bendahara baru kerangka akun, belum modul bisnis lengkap. Beranda publik masih pengantar sederhana.

Kode awal mengikuti gambar dan keputusan dalam chat. Ekspor ZIP Stitch belum tersedia sehingga ini belum konversi lengkap atau penyesuaian persis seluruh desain Stitch. Jangan menganggap proyek ini sudah memenuhi seluruh SRS atau sudah 50% tanpa penilaian rubrik.

## Menjalankan demo lokal yang sudah disiapkan

Buka PowerShell pada folder proyek ini:

```powershell
.\scripts\start.ps1
```

Buka http://127.0.0.1:8000/masuk .

Data awal lokal berisi Admin, RT 01, RT 02, dan Bendahara. Username dan kata sandi acak berada di `storage/app/private/demo-accounts.txt` (folder privat yang tidak disajikan web dan diabaikan Git). RT/Bendahara diminta mengganti sandi awal pada login pertama. Jangan memasukkan kredensial ini ke repositori atau laporan publik.

Daftar RT 01–05 adalah data prototipe sementara, bukan jumlah wilayah yang telah dikonfirmasi. Tidak ada data penduduk nyata atau rumah yang diisi otomatis.

Untuk membuat ulang data contoh pada database lokal lain (tidak menimpa akun yang sudah ada):

```powershell
php artisan sipuri:demo
```

Alur uji manual: daftar akun warga dengan RT 01 → keluar → masuk sebagai RT 01 → ganti sandi awal → Verifikasi Warga → Periksa Pendaftaran → Setujui atau Tolak dengan alasan → keluar → login warga → cek status atau dashboard. Untuk menguji penolakan, perbaiki alamat dari akun warga lalu kirim ulang.

## Database

MySQL tetap target sesuai rencana SIPURI. Layanan MySQL XAMPP belum berjalan ketika fondasi dibuat. Untuk demo dan pengujian awal, `.env` lokal memakai SQLite `database/database.sqlite` agar alur dapat langsung dicoba tanpa mengubah layanan XAMPP lain. Pengujian otomatis memakai SQLite terpisah di memori, bukan data demo.

Untuk berpindah ke MySQL:
1. Jalankan MySQL melalui XAMPP.
2. Buat database kosong khusus `sipuri` (jangan memakai database proyek lain).
3. Ubah DB_CONNECTION menjadi mysql pada `.env`; isi host, port, nama database, username dan password sesuai komputer.
4. Jalankan `php artisan config:clear`, lalu `php artisan migrate`.
5. Jalankan `php artisan sipuri:demo` hanya untuk data pengujian lokal jika diperlukan. Akun yang dibuat pada SQLite tidak otomatis disalin ke MySQL.

Contoh konfigurasi ada di `.env.mysql.example`. Migrasi menggunakan Schema Builder; pengujian integrasi MySQL masih perlu dilakukan setelah layanan tersedia. Tidak ada perintah migrate:fresh atau penghapusan data otomatis.

## Instalasi ulang dari kode

Gunakan PHP 8.2+ dan Composer. Jalankan `composer install`, salin `.env.example` ke `.env`, isi database sendiri, `php artisan key:generate`, kemudian `php artisan migrate`. Bootstrap/Inter sudah disimpan di public/assets sehingga npm/Vite tidak wajib untuk menjalankan versi ini.

Jangan salin `.env`, database demo, atau berkas kredensial ke hosting. APP_DEBUG harus false di produksi, gunakan HTTPS dan kredensial tersendiri. Daftar RT dan akun awal perlu diisi dari data resmi.

## Pengujian

```powershell
.\scripts\test.ps1
```

Di lingkungan biasa juga dapat memakai `php artisan test`. Skrip test.ps1 menyiapkan manifest cache serta konfigurasi pengujian lokal untuk lingkungan Windows yang melaporkan is_readable/is_writable secara berbeda. Skrip tersebut tidak mengubah vendor Laravel atau menonaktifkan pemeriksaan akses aplikasi. PHPUnit tetap menjalankan seluruh tes dengan database in-memory.

## Pekerjaan berikutnya

1. Periksa dan sesuaikan frontend dari ZIP ekspor Stitch.
2. Pengelolaan akun pengurus oleh Admin dan kelengkapan data warga.
3. Tagihan serentak per bulan/tahun dengan nominal pilihan dan jatuh tempo; unik per rumah/periode.
4. Bukti pembayaran → keputusan Bendahara → pelunasan + pemasukan kas tepat satu kali.
5. Transaksi kas dengan bukti wajib; laporan terbit Admin yang aman untuk warga.
6. Konten publik dan pengujian akhir MySQL/mobile.

Keputusan pengenalan rumah: alamat dinormalisasi menjadi RT, blok huruf besar, dan nomor. Pindah rumah/pergantian penanggung jawab belum tersedia; jangan mengubah data langsung untuk mengalihkan tagihan. Pembatasan duplikat perlu tetap ditinjau dengan format alamat resmi mitra.
