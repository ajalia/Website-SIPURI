<?php
namespace App\Enums;
enum Role: string {
    case Admin = 'admin';
    case Rt = 'rt';
    case Treasurer = 'bendahara';
    case Resident = 'warga';
    public function label(): string { return match($this) { self::Admin => 'Admin', self::Rt => 'Pengurus RT', self::Treasurer => 'Bendahara', self::Resident => 'Warga' }; }
}
