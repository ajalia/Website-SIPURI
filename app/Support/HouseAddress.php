<?php
namespace App\Support;

final class HouseAddress
{
    public const CONFLICT = 'Rumah ini sudah terdaftar atau sedang dalam proses verifikasi. Periksa kembali alamat Anda atau hubungi pengurus RT.';
    public const BLOCK_HELP = 'Masukkan satu huruf diikuti angka, misalnya A2 atau F12.';

    public static function block(mixed $value): mixed
    {
        if (!is_string($value)) return $value;
        $value = strtoupper(preg_replace('/^(?:blok\s+)+/iu', '', trim($value)));
        if (preg_match('/\A([A-Z])([0-9]+)\z/', $value, $parts)) {
            $value = $parts[1].(ltrim($parts[2], '0') ?: '0');
        }
        return $value;
    }

    public static function number(mixed $value): mixed
    {
        if (is_int($value)) $value = (string)$value;
        if (!is_string($value)) return $value;
        $value = preg_replace('/^(?:(?:nomor|no\.?)\s*)+/iu', '', trim($value));
        if (preg_match('/\A[0-9]+\z/', $value)) $value = ltrim($value, '0') ?: '0';
        return $value;
    }

    public static function blockRules(): array { return ['required', 'string', 'max:100', 'regex:/\A[A-Z][1-9][0-9]*\z/']; }
    public static function numberRules(): array { return ['required', 'string', 'max:30', 'regex:/\A[1-9][0-9]*\z/']; }
    public static function valid(mixed $block, mixed $number): bool
    {
        return is_string($block) && is_string($number) && strlen($block)<=100 && strlen($number)<=30
            && preg_match('/\A[A-Z][1-9][0-9]*\z/', $block) && preg_match('/\A[1-9][0-9]*\z/', $number);
    }
    public static function display(?string $block, ?string $number): string
    {
        return 'Blok '.self::block($block ?? '').' No. '.self::number($number ?? '');
    }
}
