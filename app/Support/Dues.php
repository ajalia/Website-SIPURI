<?php
namespace App\Support;
final class Dues {
 public static function months(): array {return [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];}
 public static function money(int|string $value): string {
  $integer=(string)(int)$value;
  return 'Rp'.(str_starts_with($integer,'-')?'-':'').preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', ltrim($integer,'-'));
 }
 public static function submission(?string $status): string {return ['pending'=>'Menunggu Verifikasi','rejected'=>'Ditolak','accepted'=>'Diterima'][$status]??'Belum ada pengajuan';}
}
