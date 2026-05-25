<?php
namespace Ti\Tests;

class HttpTest extends \PHPUnit\Framework\TestCase
{
    public function testJsonResponseSerialisesArray(): void
    {
        $body = json_response_body(['ok' => true, 'id' => 42]);
        $this->assertSame('{"ok":true,"id":42}', $body);
    }

    public function testValidationErrorShape(): void
    {
        $body = json_response_body(validation_error(['email' => 'Ungültig']));
        $this->assertSame('{"ok":false,"error":"validation","fields":{"email":"Ungültig"}}', $body);
    }

    public function testRateLimitError(): void
    {
        $body = json_response_body(rate_limit_error());
        $this->assertSame('{"ok":false,"error":"rate_limit"}', $body);
    }

    public function testCorsAllowsListedOrigin(): void
    {
        $headers = cors_headers_for('https://technik-prignitz.de', ['https://technik-prignitz.de']);
        $this->assertSame('https://technik-prignitz.de', $headers['Access-Control-Allow-Origin'] ?? null);
    }

    public function testCorsRejectsUnlistedOrigin(): void
    {
        $headers = cors_headers_for('https://evil.example', ['https://technik-prignitz.de']);
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $headers);
    }
}
