<?php
namespace Ti\Tests;

require_once __DIR__ . '/../public/api/angebot.php';

class AngebotEndpointTest extends TestCase
{
    private array $mails;
    private string $uploadDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mails = [];
        $caps = &$this->mails;
        set_mail_transport(function(array $m) use (&$caps) { $caps[] = $m; return true; });

        $this->uploadDir = sys_get_temp_dir() . '/ti-up-' . uniqid();
        mkdir($this->uploadDir, 0700, true);
        $GLOBALS['__ti_override_upload_dir'] = $this->uploadDir;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__ti_override_upload_dir']);
    }

    public function testHappyPathStoresAndCsvComponents(): void
    {
        $result = angebot_handle([
            'name'=>'Anna','phone'=>'1','email'=>'a@b.de',
            'components'=>['Photovoltaik','Stromspeicher'],
            'building'=>'Einfamilienhaus','location'=>'19348',
            'address_street'=>'Hauptstr. 1','address_postal'=>'19348','address_city'=>'Sükow',
            'roof'=>'Satteldach','usage'=>'3-4 Personen',
            'consumption'=>'4500','timeline'=>'In 1-3 Monaten',
            'details'=>'PV bitte','photos_followup'=>'1','privacy'=>'1',
        ], [], pack_ip('192.0.2.50'), 'UA');

        $this->assertSame(200, $result['status']);
        $row = db()->query('SELECT * FROM angebot_requests')->fetch();
        $this->assertSame('Photovoltaik, Stromspeicher', $row['components']);
        $this->assertSame('Anna', $row['name']);
        $this->assertCount(2, $this->mails);
    }

    public function testValidationErrors(): void
    {
        $result = angebot_handle([], [], pack_ip('192.0.2.51'), 'UA');
        $this->assertSame(400, $result['status']);
        $this->assertSame('validation', $result['body']['error']);
    }

    public function testHoneypotSilentSuccess(): void
    {
        $result = angebot_handle([
            'name'=>'x','phone'=>'1','email'=>'a@b.de',
            'components'=>['x'],'privacy'=>'1','website'=>'spam'
        ], [], pack_ip('192.0.2.52'), 'UA');
        $this->assertSame(200, $result['status']);
        $this->assertSame(0, (int)db()->query('SELECT COUNT(*) FROM angebot_requests')->fetchColumn());
    }

    public function testFileUploadStored(): void
    {
        $jpeg = "\xFF\xD8\xFF\xE0\x00\x10JFIF" . str_repeat("\0", 200);
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp, $jpeg);

        $files = [
            'files' => [
                'name'     => ['photo.jpg'],
                'type'     => ['image/jpeg'],
                'tmp_name' => [$tmp],
                'error'    => [UPLOAD_ERR_OK],
                'size'     => [filesize($tmp)],
            ],
        ];
        $result = angebot_handle([
            'name'=>'Anna','phone'=>'1','email'=>'a@b.de',
            'components'=>['Photovoltaik'],'privacy'=>'1',
            'address_street'=>'Hauptstr. 1','address_postal'=>'19348','address_city'=>'Sükow',
        ], $files, pack_ip('192.0.2.53'), 'UA');

        $this->assertSame(200, $result['status']);
        $attachments = db()->query('SELECT * FROM angebot_attachments')->fetchAll();
        $this->assertCount(1, $attachments);
        $this->assertSame('photo.jpg', $attachments[0]['original_name']);
        $this->assertFileExists($this->uploadDir . '/' . $attachments[0]['angebot_id'] . '/' . $attachments[0]['stored_name']);
    }

    public function testRejectTooLargeFile(): void
    {
        $big = str_repeat('x', 11 * 1024 * 1024);
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp, $big);
        $files = ['files' => [
            'name'=>['big.bin'],'type'=>['application/octet-stream'],
            'tmp_name'=>[$tmp],'error'=>[UPLOAD_ERR_OK],'size'=>[filesize($tmp)],
        ]];

        $result = angebot_handle([
            'name'=>'A','phone'=>'1','email'=>'a@b.de',
            'components'=>['x'],'privacy'=>'1',
            'address_street'=>'Hauptstr. 1','address_postal'=>'19348','address_city'=>'Sükow',
        ], $files, pack_ip('192.0.2.54'), 'UA');
        $this->assertSame(413, $result['status']);
    }

    public function testValidVoucherIsStoredAndEmailed(): void
    {
        db()->prepare("INSERT INTO vouchers (code) VALUES (?)")->execute(['MESSE2026']);

        $result = angebot_handle([
            'name'=>'V','phone'=>'1','email'=>'v@example.de',
            'components'=>['Photovoltaik'],'privacy'=>'1',
            'address_street'=>'Hauptstr. 1','address_postal'=>'19348','address_city'=>'Sükow',
            'voucher_code'=>'MESSE2026',
        ], [], pack_ip('192.0.2.60'), 'UA');

        $this->assertSame(200, $result['status']);
        $row = db()->query('SELECT voucher_code FROM angebot_requests')->fetch();
        $this->assertSame('MESSE2026', $row['voucher_code']);

        // Operator email is the first mail captured; visitor reply is the second.
        $this->assertStringContainsString('Gutscheincode: MESSE2026', $this->mails[0]['body']);
    }

    public function testInvalidVoucherRejectsSubmission(): void
    {
        $result = angebot_handle([
            'name'=>'V','phone'=>'1','email'=>'v@example.de',
            'components'=>['Photovoltaik'],'privacy'=>'1',
            'voucher_code'=>'BOGUS',
        ], [], pack_ip('192.0.2.61'), 'UA');

        $this->assertSame(400, $result['status']);
        $this->assertSame('validation', $result['body']['error']);
        $this->assertArrayHasKey('voucher_code', $result['body']['fields']);
        $this->assertSame(0, (int)db()->query('SELECT COUNT(*) FROM angebot_requests')->fetchColumn());
        $this->assertCount(0, $this->mails);
    }

    public function testMissingVoucherFieldStoresNull(): void
    {
        $result = angebot_handle([
            'name'=>'V','phone'=>'1','email'=>'v@example.de',
            'components'=>['Photovoltaik'],'privacy'=>'1',
            'address_street'=>'Hauptstr. 1','address_postal'=>'19348','address_city'=>'Sükow',
        ], [], pack_ip('192.0.2.62'), 'UA');

        $this->assertSame(200, $result['status']);
        $row = db()->query('SELECT voucher_code FROM angebot_requests')->fetch();
        $this->assertNull($row['voucher_code']);
    }

    public function testTrimmedVoucherIsStoredAsTyped(): void
    {
        db()->prepare("INSERT INTO vouchers (code) VALUES (?)")->execute(['TRIMME']);
        $result = angebot_handle([
            'name'=>'V','phone'=>'1','email'=>'v@example.de',
            'components'=>['Photovoltaik'],'privacy'=>'1',
            'address_street'=>'Hauptstr. 1','address_postal'=>'19348','address_city'=>'Sükow',
            'voucher_code'=>'  TRIMME  ',
        ], [], pack_ip('192.0.2.63'), 'UA');

        $this->assertSame(200, $result['status']);
        $row = db()->query('SELECT voucher_code FROM angebot_requests')->fetch();
        $this->assertSame('TRIMME', $row['voucher_code']);
    }

    public function testAddressFieldsArePersisted(): void
    {
        $result = angebot_handle([
            'name'=>'Anna','phone'=>'1','email'=>'a@b.de',
            'components'=>['Photovoltaik'],'privacy'=>'1',
            'address_street'=>'Quitzower Damm 15',
            'address_postal'=>'19348',
            'address_city'=>'Sükow',
        ], [], pack_ip('192.0.2.70'), 'UA');

        $this->assertSame(200, $result['status']);
        $row = db()->query('SELECT * FROM angebot_requests')->fetch();
        $this->assertSame('Quitzower Damm 15', $row['address_street']);
        $this->assertSame('19348',             $row['address_postal']);
        $this->assertSame('Sükow',             $row['address_city']);
    }

    public function testOperatorEmailContainsAddressBlock(): void
    {
        angebot_handle([
            'name'=>'Anna','phone'=>'1','email'=>'a@b.de',
            'components'=>['Photovoltaik'],'privacy'=>'1',
            'address_street'=>'Quitzower Damm 15',
            'address_postal'=>'19348',
            'address_city'=>'Sükow',
        ], [], pack_ip('192.0.2.71'), 'UA');

        $this->assertStringContainsString('Adresse:', $this->mails[0]['body']);
        $this->assertStringContainsString('Quitzower Damm 15', $this->mails[0]['body']);
        $this->assertStringContainsString('19348 Sükow',       $this->mails[0]['body']);
        $this->assertStringNotContainsString('Standort/PLZ',   $this->mails[0]['body']);
    }

    public function testVisitorAutoreplyContainsAddress(): void
    {
        angebot_handle([
            'name'=>'Anna','phone'=>'1','email'=>'a@b.de',
            'components'=>['Photovoltaik'],'privacy'=>'1',
            'address_street'=>'Quitzower Damm 15',
            'address_postal'=>'19348',
            'address_city'=>'Sükow',
        ], [], pack_ip('192.0.2.72'), 'UA');

        // Visitor reply is the second captured mail.
        $this->assertStringContainsString('Adresse:', $this->mails[1]['body']);
        $this->assertStringContainsString('Quitzower Damm 15, 19348 Sükow', $this->mails[1]['body']);
    }

    public function testMultiSelectBuildingIsStoredAsCommaSeparated(): void
    {
        $result = angebot_handle([
            'name'=>'Multi','phone'=>'1','email'=>'m@b.de',
            'components'=>['Photovoltaik'],'privacy'=>'1',
            'address_street'=>'Hauptstr. 1','address_postal'=>'19348','address_city'=>'Sükow',
            'building'=>['Einfamilienhaus', 'Carport / Garage', 'Freiland'],
        ], [], pack_ip('192.0.2.80'), 'UA');

        $this->assertSame(200, $result['status']);
        $row = db()->query('SELECT building FROM angebot_requests')->fetch();
        $this->assertSame('Einfamilienhaus, Carport / Garage, Freiland', $row['building']);
        $this->assertStringContainsString(
            'Objekt:       Einfamilienhaus, Carport / Garage, Freiland',
            $this->mails[0]['body']
        );
    }

    public function testMissingAddressFieldsAreRejected(): void
    {
        $result = angebot_handle([
            'name'=>'Anna','phone'=>'1','email'=>'a@b.de',
            'components'=>['Photovoltaik'],'privacy'=>'1',
        ], [], pack_ip('192.0.2.73'), 'UA');

        $this->assertSame(400, $result['status']);
        $this->assertSame('validation', $result['body']['error']);
        $this->assertArrayHasKey('address_street', $result['body']['fields']);
        $this->assertArrayHasKey('address_postal', $result['body']['fields']);
        $this->assertArrayHasKey('address_city',   $result['body']['fields']);
        $this->assertSame(0, (int)db()->query('SELECT COUNT(*) FROM angebot_requests')->fetchColumn());
        $this->assertCount(0, $this->mails);
    }
}
