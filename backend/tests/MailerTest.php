<?php
namespace Ti\Tests;

class MailerTest extends \PHPUnit\Framework\TestCase
{
    public function testTransportCapturesMessage(): void
    {
        $captured = [];
        set_mail_transport(function(array $msg) use (&$captured) {
            $captured[] = $msg;
            return true;
        });

        $ok = send_mail([
            'to'      => 'a@b.de',
            'subject' => 'Hallo',
            'body'    => 'Test',
        ]);

        $this->assertTrue($ok);
        $this->assertCount(1, $captured);
        $this->assertSame('a@b.de', $captured[0]['to']);
        $this->assertSame('Hallo', $captured[0]['subject']);
    }

    public function testSubjectStripsCrlfHeaderInjection(): void
    {
        $captured = [];
        set_mail_transport(function(array $msg) use (&$captured) {
            $captured[] = $msg; return true;
        });
        send_mail([
            'to'      => 'a@b.de',
            'subject' => "Hi\r\nBcc: attacker@evil",
            'body'    => '.',
        ]);
        $this->assertSame('Hi Bcc: attacker@evil', $captured[0]['subject']);
    }

    public function testReplyToHeaderRespected(): void
    {
        $captured = [];
        set_mail_transport(function(array $msg) use (&$captured) {
            $captured[] = $msg; return true;
        });
        send_mail(['to'=>'a@b.de','subject'=>'s','body'=>'b','reply_to'=>'visitor@example.de']);
        $this->assertStringContainsString('Reply-To: visitor@example.de', $captured[0]['headers']);
    }
}
