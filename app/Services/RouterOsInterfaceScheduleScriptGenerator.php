<?php

namespace App\Services;

use DateTimeImmutable;
use InvalidArgumentException;

class RouterOsInterfaceScheduleScriptGenerator
{
    public function generate(
        string $interfaceType,
        string $interfaceName,
        string $mode,
        string $onTime,
        string $offTime,
        ?string $onDate = null,
        ?string $offDate = null,
    ): string {
        if (! in_array($interfaceType, ['ethernet', 'vlan'], true)) {
            throw new InvalidArgumentException('Choose an Ethernet or VLAN interface.');
        }

        if (preg_match('/\A[A-Za-z0-9._-]{1,64}\z/', $interfaceName) !== 1) {
            throw new InvalidArgumentException('Interface names may only contain letters, numbers, dots, underscores, and hyphens.');
        }

        if (! in_array($mode, ['daily', 'once'], true)) {
            throw new InvalidArgumentException('Choose a daily or one-time schedule.');
        }

        if (! $this->isValidTime($onTime) || ! $this->isValidTime($offTime) || $onTime === $offTime) {
            throw new InvalidArgumentException('Enter two different valid interface schedule times.');
        }

        if ($mode === 'once') {
            if (! $this->isValidDate($onDate) || ! $this->isValidDate($offDate)) {
                throw new InvalidArgumentException('One-time schedules require valid ON and OFF dates.');
            }

            $onDateTime = new DateTimeImmutable($onDate.' '.$onTime);
            $offDateTime = new DateTimeImmutable($offDate.' '.$offTime);

            if ($offDateTime <= $onDateTime) {
                throw new InvalidArgumentException('The OFF date and time must be after the ON date and time.');
            }
        } else {
            $onDate = null;
            $offDate = null;
        }

        $suffix = substr(hash('sha256', strtolower($interfaceType.':'.$interfaceName)), 0, 10);
        $onName = 'vpnm-'.$interfaceType.'-'.$suffix.'-on';
        $offName = 'vpnm-'.$interfaceType.'-'.$suffix.'-off';
        $interval = $mode === 'daily' ? '1d' : '0s';
        $onSchedule = $this->scheduleAttributes($onDate, $onTime, $interval);
        $offSchedule = $this->scheduleAttributes($offDate, $offTime, $interval);
        $onEvent = '/interface '.$interfaceType.' enable '.$interfaceName;
        $offEvent = '/interface '.$interfaceType.' disable '.$interfaceName;

        return implode("\n", [
            '# MikroTik RouterOS v7 - scheduled '.strtoupper($interfaceType).' interface control',
            '# Replaces only the two scheduler entries generated for this interface.',
            $this->removeExisting($onName),
            $this->removeExisting($offName),
            '/system scheduler add name='.$this->quote($onName).' '.$onSchedule.' on-event='.$this->quote($onEvent).' policy=read,write,test',
            '/system scheduler add name='.$this->quote($offName).' '.$offSchedule.' on-event='.$this->quote($offEvent).' policy=read,write,test',
            '',
        ]);
    }

    private function isValidTime(string $time): bool
    {
        return preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $time) === 1;
    }

    private function isValidDate(?string $date): bool
    {
        if ($date === null || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date) !== 1) {
            return false;
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private function scheduleAttributes(?string $date, string $time, string $interval): string
    {
        return ($date === null ? '' : 'start-date='.$date.' ')
            .'start-time='.$time.':00 interval='.$interval;
    }

    private function removeExisting(string $name): string
    {
        $quotedName = $this->quote($name);

        $find = '/system scheduler find where name='.$quotedName;

        return ':if ([:len ['.$find.']] > 0) do={ /system scheduler remove ['.$find.'] }';
    }

    private function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }
}
