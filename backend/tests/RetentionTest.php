<?php
namespace Ti\Tests;

class RetentionTest extends TestCase
{
    public function testHandledOlderThan12MonthsDeleted(): void
    {
        db()->exec("INSERT INTO contact_requests (name, contact, message, status, handled_at, created_at)
                    VALUES ('old', 'a@b.de', '.', 'handled', '2024-01-01', '2024-01-01')");
        db()->exec("INSERT INTO contact_requests (name, contact, message, status, handled_at)
                    VALUES ('recent', 'a@b.de', '.', 'handled', NOW())");

        retention_apply();

        $rows = db()->query("SELECT name FROM contact_requests")->fetchAll();
        $names = array_column($rows, 'name');
        $this->assertContains('recent', $names);
        $this->assertNotContains('old', $names);
    }

    public function testIpAnonymizedAfter30Days(): void
    {
        $packed = pack_ip('192.0.2.123');
        $stmt = db()->prepare("INSERT INTO contact_requests (name, contact, message, ip_address, created_at)
                               VALUES ('u', 'a@b.de', '.', ?, DATE_SUB(NOW(), INTERVAL 31 DAY))");
        $stmt->execute([$packed]);

        retention_apply();

        $ip = db()->query("SELECT ip_address FROM contact_requests WHERE name='u'")->fetchColumn();
        $this->assertSame(inet_pton('192.0.2.0'), $ip);
    }

    public function testAttachmentsDeletedWithAngebot(): void
    {
        $dir = sys_get_temp_dir() . '/ti-ret-' . uniqid();
        mkdir("{$dir}/42", 0700, true);
        $p = "{$dir}/42/abc.jpg";
        file_put_contents($p, 'x');
        $GLOBALS['__ti_retention_upload_dir'] = $dir;

        db()->exec("INSERT INTO angebot_requests (id, name, phone, email, components, status, handled_at, created_at)
                    VALUES (42, 'A', '1', 'a@b.de', 'PV', 'handled', '2024-01-01', '2024-01-01')");
        db()->exec("INSERT INTO angebot_attachments (angebot_id, stored_name, original_name, mime_type, size_bytes)
                    VALUES (42, 'abc.jpg', 'photo.jpg', 'image/jpeg', 1)");

        retention_apply();

        $this->assertFileDoesNotExist($p);
        $this->assertSame(0, (int)db()->query("SELECT COUNT(*) FROM angebot_requests WHERE id=42")->fetchColumn());
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__ti_retention_upload_dir']);
    }
}
