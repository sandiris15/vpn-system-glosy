<?php

namespace Tests\Unit;

use App\Services\RouterOsHostResolver;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RouterOsHostResolverTest extends TestCase
{
    public function test_it_accepts_public_ip_literals_without_dns_resolution(): void
    {
        $resolved = (new RouterOsHostResolver())->resolvePublicHost('8.8.8.8');

        $this->assertSame(['host' => '8.8.8.8', 'resolved_ip' => null], $resolved);
    }

    public function test_it_rejects_private_and_reserved_ip_literals(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new RouterOsHostResolver())->resolvePublicHost('127.0.0.1');
    }
}
