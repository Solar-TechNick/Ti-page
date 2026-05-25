<?php
namespace Ti\Tests;

class ValidateTest extends \PHPUnit\Framework\TestCase
{
    public function testRequiredMissing(): void
    {
        $errors = validate_contact([]);
        $this->assertSame('Bitte geben Sie Ihren Namen an.', $errors['name'] ?? null);
        $this->assertSame('Bitte geben Sie einen Kontakt an.', $errors['contact'] ?? null);
        $this->assertSame('Bitte schreiben Sie uns eine Nachricht.', $errors['message'] ?? null);
    }

    public function testValidContact(): void
    {
        $errors = validate_contact([
            'name'    => 'Max',
            'contact' => 'max@example.de',
            'message' => 'Hallo',
        ]);
        $this->assertSame([], $errors);
    }

    public function testFieldLengthLimit(): void
    {
        $errors = validate_contact([
            'name'    => str_repeat('a', 201),
            'contact' => 'a@b.de',
            'message' => 'x',
        ]);
        $this->assertNotEmpty($errors['name'] ?? null);
    }

    public function testAngebotRequiresComponents(): void
    {
        $errors = validate_angebot(['name'=>'M','phone'=>'1','email'=>'a@b.de','privacy'=>'1']);
        $this->assertNotEmpty($errors['components'] ?? null);
    }

    public function testAngebotRequiresPrivacy(): void
    {
        $errors = validate_angebot([
            'name'=>'M','phone'=>'1','email'=>'a@b.de',
            'components'=>['Photovoltaik'],
        ]);
        $this->assertNotEmpty($errors['privacy'] ?? null);
    }

    public function testAngebotEmailFormat(): void
    {
        $errors = validate_angebot([
            'name'=>'M','phone'=>'1','email'=>'not-email',
            'components'=>['Photovoltaik'],'privacy'=>'1',
        ]);
        $this->assertNotEmpty($errors['email'] ?? null);
    }

    public function testHoneypotDetection(): void
    {
        $this->assertTrue(is_honeypot_triggered(['website' => 'something']));
        $this->assertFalse(is_honeypot_triggered(['website' => '']));
        $this->assertFalse(is_honeypot_triggered([]));
    }
}
