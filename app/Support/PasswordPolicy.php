<?php
namespace App\Support;
final class PasswordPolicy {
 public const HELP='Minimal 8 karakter, mengandung huruf besar, huruf kecil, dan angka.';
 public static function rules(): array {return ['required','string','min:8','max:128','regex:/\A(?=[\s\S]*[A-Z])(?=[\s\S]*[a-z])(?=[\s\S]*[0-9])[\s\S]*\z/u','confirmed'];}
 public static function temporary(): string {
  // Explicit mandatory characters; the remaining characters are cryptographically random.
  $alphabet='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
  $result=chr(random_int(65,90)).chr(random_int(97,122)).(string)random_int(0,9);
  for($i=0;$i<17;$i++)$result.=$alphabet[random_int(0,strlen($alphabet)-1)];
  return $result;
 }
 public static function messages(): array {return ['password.required'=>'Kata sandi wajib diisi.','current_password.required'=>'Kata sandi saat ini wajib diisi.','password.regex'=>self::HELP,'password.min'=>self::HELP,'password.max'=>'Kata sandi maksimal 128 karakter.','password.confirmed'=>'Konfirmasi kata sandi tidak cocok.'];}
}
