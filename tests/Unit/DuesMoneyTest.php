<?php
namespace Tests\Unit;
use App\Support\Dues;
use PHPUnit\Framework\TestCase;
class DuesMoneyTest extends TestCase {
 public function test_rupiah_format_preserves_integers_without_float_rounding(): void {
  $this->assertSame('Rp125.000',Dues::money(125000));
  $this->assertSame('Rp9.007.199.254.740.993',Dues::money(9007199254740993));
  $this->assertSame('Rp-9.223.372.036.854.775.808',Dues::money(PHP_INT_MIN));
  $this->assertSame('Rp0',Dues::money(0));
 }
}
