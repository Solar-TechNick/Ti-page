<?php
namespace Ti\Tests;

require_once __DIR__ . '/../public/api/contact.php';

class ContactEndpointTest extends TestCase
{
    private array $mails;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mails = [];
        $caps = &$this->mails;
        set_mail_transport(function(array $m) use (&$caps) { $caps[] = $m; return true; });
    }

    public function testHappyPathStoresAndMails(): void
    {
        $result = contact_handle([
            'name'    => 'Max Mustermann',
            'contact' => 'max@example.de',
            'topic'   => 'PV',
            'message' => 'Bitte melden.',
            'website' => '',
        ], pack_ip('192.0.2.1'), 'TestUA/1.0');

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['body']['ok']);
        $this->assertIsInt($result['body']['id']);

        $row = db()->query('SELECT * FROM contact_requests')->fetch();
        $this->assertSame('Max Mustermann', $row['name']);
        $this->assertSame('PV', $row['topic']);

        $this->assertCount(2, $this->mails); // operator + visitor autoreply
    }

    public function testMissingFieldsReturn400(): void
    {
        $result = contact_handle([], pack_ip('192.0.2.1'), '');
        $this->assertSame(400, $result['status']);
        $this->assertSame('validation', $result['body']['error']);
        $this->assertArrayHasKey('name', $result['body']['fields']);
    }

    public function testHoneypotReturnsFakeSuccessAndDoesNotStore(): void
    {
        $result = contact_handle([
            'name'=>'x','contact'=>'x@y.de','message'=>'.',
            'website' => 'spam',
        ], pack_ip('192.0.2.2'), '');
        $this->assertSame(200, $result['status']);
        $count = (int)db()->query('SELECT COUNT(*) FROM contact_requests')->fetchColumn();
        $this->assertSame(0, $count);
        $this->assertCount(0, $this->mails);
    }

    public function testRateLimitReturns429(): void
    {
        $packed = pack_ip('192.0.2.99');
        for ($i = 0; $i < 5; $i++) {
            contact_handle(['name'=>"u{$i}",'contact'=>'x@y.de','message'=>'.'], $packed, '');
        }
        $result = contact_handle(['name'=>'u6','contact'=>'x@y.de','message'=>'.'], $packed, '');
        $this->assertSame(429, $result['status']);
        $this->assertSame('rate_limit', $result['body']['error']);
    }
}
