<?php

namespace Tests\Unit;

use App\Services\Ipv4PoolAllocator;
use InvalidArgumentException;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;

class Ipv4PoolAllocatorTest extends TestCase
{
    public function test_it_allocates_the_first_free_address_across_pool_ranges(): void
    {
        $address = (new Ipv4PoolAllocator())->allocate(
            '10.10.0.1-10.10.0.2,10.10.0.10-10.10.0.11',
            ['10.10.0.1', '10.10.0.2'],
        );

        $this->assertSame('10.10.0.10', $address);
    }

    public function test_it_rejects_an_exhausted_pool(): void
    {
        $this->expectException(OutOfBoundsException::class);

        (new Ipv4PoolAllocator())->allocate('10.10.0.1-10.10.0.2', [
            '10.10.0.1',
            '10.10.0.2',
        ]);
    }

    public function test_it_rejects_invalid_or_oversized_pool_ranges(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Ipv4PoolAllocator())->allocate('10.0.0.0-10.255.255.255', []);
    }
}
