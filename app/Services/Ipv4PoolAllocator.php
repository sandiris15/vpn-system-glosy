<?php

namespace App\Services;

use InvalidArgumentException;
use OutOfBoundsException;

class Ipv4PoolAllocator
{
    private const MAX_ADDRESSES_TO_SCAN = 65536;

    /**
     * @param  array<int, string|null>  $reservedAddresses
     */
    public function allocate(string $ranges, array $reservedAddresses): string
    {
        $reserved = [];

        foreach ($reservedAddresses as $address) {
            if (is_string($address) && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $reserved[$this->toInteger($address)] = true;
            }
        }

        $scanned = 0;
        $poolRanges = array_filter(array_map('trim', explode(',', $ranges)));

        if ($poolRanges === []) {
            throw new InvalidArgumentException('The configured RouterOS IP pool has no ranges.');
        }

        foreach ($poolRanges as $poolRange) {
            $parts = explode('-', $poolRange);

            if (count($parts) > 2) {
                throw new InvalidArgumentException('The configured RouterOS IP pool contains an invalid range.');
            }

            $start = $this->toInteger(trim($parts[0]));
            $end = $this->toInteger(trim($parts[1] ?? $parts[0]));

            if ($end < $start) {
                throw new InvalidArgumentException('The configured RouterOS IP pool range is reversed.');
            }

            $rangeSize = $end - $start + 1;

            if ($scanned + $rangeSize > self::MAX_ADDRESSES_TO_SCAN) {
                throw new InvalidArgumentException('The RouterOS IP pool is too large to allocate safely.');
            }

            for ($address = $start; $address <= $end; $address++) {
                if (! isset($reserved[$address])) {
                    return $this->toString($address);
                }
            }

            $scanned += $rangeSize;
        }

        throw new OutOfBoundsException('No free IPv4 addresses remain in the configured RouterOS IP pool.');
    }

    /**
     * @param  array<int, string|null>  $reservedAddresses
     */
    public function isAvailable(string $ranges, string $address, array $reservedAddresses): bool
    {
        $candidate = $this->toInteger($address);
        $reserved = [];

        foreach ($reservedAddresses as $reservedAddress) {
            if (is_string($reservedAddress) && filter_var($reservedAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $reserved[$this->toInteger($reservedAddress)] = true;
            }
        }

        $scanned = 0;
        $inPool = false;

        foreach (array_filter(array_map('trim', explode(',', $ranges))) as $poolRange) {
            $parts = explode('-', $poolRange);

            if (count($parts) > 2) {
                throw new InvalidArgumentException('The configured RouterOS IP pool contains an invalid range.');
            }

            $start = $this->toInteger(trim($parts[0]));
            $end = $this->toInteger(trim($parts[1] ?? $parts[0]));

            if ($end < $start) {
                throw new InvalidArgumentException('The configured RouterOS IP pool range is reversed.');
            }

            $scanned += $end - $start + 1;

            if ($scanned > self::MAX_ADDRESSES_TO_SCAN) {
                throw new InvalidArgumentException('The RouterOS IP pool is too large to allocate safely.');
            }

            $inPool = $inPool || ($candidate >= $start && $candidate <= $end);
        }

        return $inPool && ! isset($reserved[$candidate]);
    }

    private function toInteger(string $address): int
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new InvalidArgumentException('The configured RouterOS IP pool contains an invalid IPv4 address.');
        }

        return unpack('Naddress', inet_pton($address))['address'];
    }

    private function toString(int $address): string
    {
        return inet_ntop(pack('N', $address));
    }
}
