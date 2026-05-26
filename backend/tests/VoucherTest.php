<?php
namespace Ti\Tests;

class VoucherTest extends TestCase
{
    private function insertVoucher(string $code, ?string $expiresAt, int $active = 1): void
    {
        $stmt = db()->prepare(
            "INSERT INTO vouchers (code, expires_at, active) VALUES (?, ?, ?)"
        );
        $stmt->execute([$code, $expiresAt, $active]);
    }

    public function testReturnsRowForActiveNonExpired(): void
    {
        $this->insertVoucher('MESSE2026', null);
        $row = find_active_voucher('MESSE2026');
        $this->assertIsArray($row);
        $this->assertSame('MESSE2026', $row['code']);
    }

    public function testReturnsNullForUnknownCode(): void
    {
        $this->assertNull(find_active_voucher('DOESNOTEXIST'));
    }

    public function testReturnsNullForInactiveVoucher(): void
    {
        $this->insertVoucher('OFF', null, 0);
        $this->assertNull(find_active_voucher('OFF'));
    }

    public function testReturnsNullForExpiredVoucher(): void
    {
        $past = date('Y-m-d H:i:s', strtotime('-1 day'));
        $this->insertVoucher('EXPIRED', $past);
        $this->assertNull(find_active_voucher('EXPIRED'));
    }

    public function testReturnsRowForFutureExpiry(): void
    {
        $future = date('Y-m-d H:i:s', strtotime('+1 day'));
        $this->insertVoucher('LIVE', $future);
        $this->assertIsArray(find_active_voucher('LIVE'));
    }

    public function testCaseInsensitiveLookup(): void
    {
        $this->insertVoucher('FREE10', null);
        $this->assertIsArray(find_active_voucher('free10'));
        $this->assertIsArray(find_active_voucher('Free10'));
    }

    public function testTrimsWhitespace(): void
    {
        $this->insertVoucher('TRIMME', null);
        $this->assertIsArray(find_active_voucher('  TRIMME  '));
    }

    public function testEmptyInputReturnsNull(): void
    {
        $this->assertNull(find_active_voucher(''));
        $this->assertNull(find_active_voucher('   '));
    }
}
