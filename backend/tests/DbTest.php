<?php
namespace Ti\Tests;

class DbTest extends TestCase
{
    public function testReturnsSamePdoInstance(): void
    {
        $a = db();
        $b = db();
        $this->assertSame($a, $b);
    }

    public function testCanQueryUsersTable(): void
    {
        $count = db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assertSame(0, (int)$count);
    }
}
