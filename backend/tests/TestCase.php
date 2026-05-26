<?php
namespace Ti\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use PDO;

abstract class TestCase extends PHPUnitTestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = db();
        $this->truncateAll();
    }

    private function truncateAll(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['angebot_attachments','angebot_requests','contact_requests','rate_limit','users','vouchers'] as $t) {
            $this->pdo->exec("TRUNCATE TABLE {$t}");
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
