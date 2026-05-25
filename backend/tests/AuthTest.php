<?php
namespace Ti\Tests;

class AuthTest extends TestCase
{
    public function testCreateAndVerifyUser(): void
    {
        create_user('admin', 'correct horse battery staple');
        $this->assertTrue(verify_login('admin', 'correct horse battery staple'));
        $this->assertFalse(verify_login('admin', 'wrong'));
    }

    public function testLockoutAfterFailures(): void
    {
        create_user('admin', 'right');
        for ($i = 0; $i < 5; $i++) verify_login('admin', 'wrong');
        $this->assertTrue(is_account_locked('admin'));
        // Still locked even with right password
        $this->assertFalse(verify_login('admin', 'right'));
    }

    public function testLockoutExpires(): void
    {
        create_user('admin', 'right');
        for ($i = 0; $i < 5; $i++) verify_login('admin', 'wrong');
        // Manually expire the lock
        db()->exec("UPDATE users SET locked_until = '2000-01-01 00:00:00' WHERE username='admin'");
        $this->assertTrue(verify_login('admin', 'right'));
    }
}
