<?php
namespace Ti\Tests;

class RateLimitTest extends TestCase
{
    public function testFirstHitIsAllowed(): void
    {
        $packed = pack_ip('192.0.2.10');
        $this->assertFalse(is_rate_limited($packed, 5, 'PT1H'));
    }

    public function testHitsAccumulateUntilLimit(): void
    {
        $packed = pack_ip('192.0.2.11');
        for ($i = 1; $i <= 5; $i++) {
            $this->assertFalse(is_rate_limited($packed, 5, 'PT1H'), "hit {$i} should pass");
        }
        $this->assertTrue(is_rate_limited($packed, 5, 'PT1H'), 'hit 6 should be blocked');
    }

    public function testDifferentIpsAreIndependent(): void
    {
        $a = pack_ip('192.0.2.20');
        $b = pack_ip('192.0.2.21');
        for ($i = 0; $i < 5; $i++) is_rate_limited($a, 5, 'PT1H');
        $this->assertFalse(is_rate_limited($b, 5, 'PT1H'));
    }

    public function testCleanupRemovesOldWindows(): void
    {
        $packed = pack_ip('192.0.2.30');
        db()->prepare("INSERT INTO rate_limit (ip_address, window_start, request_count) VALUES (?, ?, 1)")
            ->execute([$packed, '2020-01-01 00:00:00']);
        cleanup_rate_limit('PT2H');
        $remaining = db()->query("SELECT COUNT(*) FROM rate_limit")->fetchColumn();
        $this->assertSame(0, (int)$remaining);
    }
}
