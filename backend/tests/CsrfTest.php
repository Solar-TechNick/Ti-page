<?php
namespace Ti\Tests;

class CsrfTest extends \PHPUnit\Framework\TestCase
{
    public function testGenerateAndCheck(): void
    {
        $store = [];
        $token = csrf_issue($store);
        $this->assertTrue(csrf_verify($token, $store));
    }

    public function testWrongTokenFails(): void
    {
        $store = [];
        csrf_issue($store);
        $this->assertFalse(csrf_verify('wrong', $store));
    }

    public function testEmptyStoreFails(): void
    {
        $this->assertFalse(csrf_verify('whatever', []));
    }
}
