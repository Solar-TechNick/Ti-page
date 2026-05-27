<?php
namespace Ti\Tests;

class ValidateTest extends TestCase
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

    private function seedVoucher(string $code, ?string $expiresAt = null, int $active = 1): void
    {
        db()->prepare("INSERT INTO vouchers (code, expires_at, active) VALUES (?, ?, ?)")
            ->execute([$code, $expiresAt, $active]);
    }

    private function baseValidAngebot(): array
    {
        return [
            'name'=>'M','phone'=>'1','email'=>'a@b.de',
            'components'=>['Photovoltaik'],'privacy'=>'1',
        ];
    }

    public function testAngebotEmptyVoucherIsAccepted(): void
    {
        $errors = validate_angebot($this->baseValidAngebot() + ['voucher_code' => '']);
        $this->assertArrayNotHasKey('voucher_code', $errors);
    }

    public function testAngebotMissingVoucherKeyIsAccepted(): void
    {
        $errors = validate_angebot($this->baseValidAngebot());
        $this->assertArrayNotHasKey('voucher_code', $errors);
    }

    public function testAngebotValidVoucherIsAccepted(): void
    {
        $this->seedVoucher('GOODCODE');
        $errors = validate_angebot($this->baseValidAngebot() + ['voucher_code' => 'GOODCODE']);
        $this->assertArrayNotHasKey('voucher_code', $errors);
    }

    public function testAngebotUnknownVoucherIsRejected(): void
    {
        $errors = validate_angebot($this->baseValidAngebot() + ['voucher_code' => 'NOPE']);
        $this->assertSame('Gutscheincode ungültig oder abgelaufen.', $errors['voucher_code'] ?? null);
    }

    public function testAngebotExpiredVoucherIsRejected(): void
    {
        $this->seedVoucher('OLD', date('Y-m-d H:i:s', strtotime('-1 hour')));
        $errors = validate_angebot($this->baseValidAngebot() + ['voucher_code' => 'OLD']);
        $this->assertSame('Gutscheincode ungültig oder abgelaufen.', $errors['voucher_code'] ?? null);
    }

    public function testAngebotVoucherTooLong(): void
    {
        $errors = validate_angebot($this->baseValidAngebot() + ['voucher_code' => str_repeat('a', 51)]);
        $this->assertSame('Gutscheincode darf höchstens 50 Zeichen lang sein.', $errors['voucher_code'] ?? null);
    }

    public function testAddressStreetLengthLimit(): void
    {
        $errors = validate_angebot([
            'name'=>'M','phone'=>'1','email'=>'a@b.de',
            'components'=>['Photovoltaik'],'privacy'=>'1',
            'address_street'=>str_repeat('a', 201),
        ]);
        $this->assertNotEmpty($errors['address_street'] ?? null);
    }

    public function testAddressPostalLengthLimit(): void
    {
        $errors = validate_angebot([
            'name'=>'M','phone'=>'1','email'=>'a@b.de',
            'components'=>['Photovoltaik'],'privacy'=>'1',
            'address_postal'=>str_repeat('1', 21),
        ]);
        $this->assertNotEmpty($errors['address_postal'] ?? null);
    }

    public function testAddressCityLengthLimit(): void
    {
        $errors = validate_angebot([
            'name'=>'M','phone'=>'1','email'=>'a@b.de',
            'components'=>['Photovoltaik'],'privacy'=>'1',
            'address_city'=>str_repeat('a', 101),
        ]);
        $this->assertNotEmpty($errors['address_city'] ?? null);
    }

    public function testAddressFieldsAtLimitAreAccepted(): void
    {
        $errors = validate_angebot([
            'name'=>'M','phone'=>'1','email'=>'a@b.de',
            'components'=>['Photovoltaik'],'privacy'=>'1',
            'address_street'=>str_repeat('a', 200),
            'address_postal'=>str_repeat('1', 20),
            'address_city'=>str_repeat('a', 100),
        ]);
        $this->assertSame([], $errors);
    }
}
