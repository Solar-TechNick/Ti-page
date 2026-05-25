<?php
namespace Ti\Tests;

class IpTest extends \PHPUnit\Framework\TestCase
{
    public function testPacksIpv4(): void
    {
        $this->assertSame(inet_pton('192.0.2.1'), pack_ip('192.0.2.1'));
    }

    public function testPacksIpv6(): void
    {
        $this->assertSame(inet_pton('2001:db8::1'), pack_ip('2001:db8::1'));
    }

    public function testInvalidIpReturnsNull(): void
    {
        $this->assertNull(pack_ip('not-an-ip'));
    }

    public function testNullReturnsNull(): void
    {
        $this->assertNull(pack_ip(null));
    }

    public function testAnonymisesIpv4ToSlash24(): void
    {
        $packed = pack_ip('192.0.2.123');
        $anon = anonymize_ip($packed);
        $this->assertSame(inet_pton('192.0.2.0'), $anon);
    }

    public function testAnonymisesIpv6ToSlash48(): void
    {
        $packed = pack_ip('2001:db8:abcd:1234::1');
        $anon = anonymize_ip($packed);
        $this->assertSame(inet_pton('2001:db8:abcd::'), $anon);
    }
}
