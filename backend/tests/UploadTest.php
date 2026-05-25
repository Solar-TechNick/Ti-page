<?php
namespace Ti\Tests;

class UploadTest extends \PHPUnit\Framework\TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/ti-upload-test-' . uniqid();
        mkdir($this->tmpDir, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmpDir);
    }

    private function rrmdir(string $d): void
    {
        if (!is_dir($d)) return;
        foreach (scandir($d) as $f) {
            if ($f === '.' || $f === '..') continue;
            $p = "{$d}/{$f}";
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($d);
    }

    private function fakeUpload(string $name, string $content, string $type = 'image/jpeg'): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp, $content);
        return [
            'name'     => $name,
            'type'     => $type,
            'tmp_name' => $tmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => strlen($content),
        ];
    }

    public function testNormaliseFilesEntryEmpty(): void
    {
        $result = normalise_files_input([]);
        $this->assertSame([], $result);
    }

    public function testNormaliseFilesEntrySingle(): void
    {
        $files = ['files' => [
            'name'     => ['a.jpg'],
            'type'     => ['image/jpeg'],
            'tmp_name' => ['/tmp/x'],
            'error'    => [0],
            'size'     => [123],
        ]];
        $result = normalise_files_input($files['files']);
        $this->assertCount(1, $result);
        $this->assertSame('a.jpg', $result[0]['name']);
    }

    public function testValidateRejectsTooManyFiles(): void
    {
        $files = [];
        for ($i = 0; $i < 11; $i++) $files[] = $this->fakeUpload("a{$i}.jpg", 'x');
        $err = validate_uploads($files, [
            'max_file_count' => 10,
            'max_file_bytes' => 1024,
            'max_total_bytes' => 10000,
            'allowed_mimes' => ['image/jpeg'],
        ]);
        $this->assertSame('validation', $err['kind']);
    }

    public function testValidateRejectsTooLargeFile(): void
    {
        $big = str_repeat('x', 2000);
        $files = [$this->fakeUpload('a.jpg', $big)];
        $err = validate_uploads($files, [
            'max_file_count' => 10,
            'max_file_bytes' => 1024,
            'max_total_bytes' => 100000,
            'allowed_mimes' => ['image/jpeg','application/pdf'],
        ]);
        $this->assertSame('too_large', $err['kind']);
    }

    public function testValidateRejectsTotalSize(): void
    {
        $half = str_repeat('x', 600);
        // valid JPEG header + filler so MIME detection passes
        $jpeg = "\xFF\xD8\xFF\xE0\x00\x10JFIF" . str_repeat("\0", 600);
        $files = [$this->fakeUpload('a.jpg', $jpeg, 'image/jpeg'),
                  $this->fakeUpload('b.jpg', $jpeg, 'image/jpeg')];
        $err = validate_uploads($files, [
            'max_file_count' => 10,
            'max_file_bytes' => 10000,
            'max_total_bytes' => 1000,
            'allowed_mimes' => ['image/jpeg'],
        ]);
        $this->assertSame('too_large', $err['kind']);
    }

    public function testValidateAcceptsValidFile(): void
    {
        $jpeg = "\xFF\xD8\xFF\xE0\x00\x10JFIF" . str_repeat("\0", 100);
        $files = [$this->fakeUpload('photo.jpg', $jpeg, 'image/jpeg')];
        $err = validate_uploads($files, [
            'max_file_count' => 10,
            'max_file_bytes' => 10000,
            'max_total_bytes' => 100000,
            'allowed_mimes' => ['image/jpeg'],
        ]);
        $this->assertNull($err);
    }

    public function testStoreUploadsCreatesDirAndReturnsMetadata(): void
    {
        $jpeg = "\xFF\xD8\xFF\xE0\x00\x10JFIF" . str_repeat("\0", 100);
        $files = [$this->fakeUpload('mein bild.jpg', $jpeg, 'image/jpeg')];
        $meta = store_uploads($files, $this->tmpDir, 42, [
            'image/jpeg' => 'jpg',
        ]);
        $this->assertCount(1, $meta);
        $this->assertSame('mein bild.jpg', $meta[0]['original_name']);
        $this->assertSame('image/jpeg', $meta[0]['mime_type']);
        $this->assertFileExists($this->tmpDir . '/42/' . $meta[0]['stored_name']);
        $this->assertStringEndsWith('.jpg', $meta[0]['stored_name']);
    }
}
