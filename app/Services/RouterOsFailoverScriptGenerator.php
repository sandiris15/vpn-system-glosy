<?php

namespace App\Services;

use InvalidArgumentException;

class RouterOsFailoverScriptGenerator
{
    public function generate(string $primaryGateway, string $backupGateway): string
    {
        foreach ([$primaryGateway, $backupGateway] as $gateway) {
            if (filter_var($gateway, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                throw new InvalidArgumentException('Failover gateways must be valid IPv4 addresses.');
            }
        }

        if ($primaryGateway === $backupGateway) {
            throw new InvalidArgumentException('Failover gateways must be different.');
        }

        $uplinks = [
            ['name' => 'ISP1', 'gateway' => $primaryGateway, 'distance' => 1, 'probes' => ['8.8.8.8', '8.8.4.4']],
            ['name' => 'ISP2', 'gateway' => $backupGateway, 'distance' => 2, 'probes' => ['1.1.1.1', '1.0.0.1']],
        ];
        $routes = [];

        foreach ($uplinks as $uplink) {
            foreach ($uplink['probes'] as $index => $probe) {
                $number = $index + 1;
                $probeComment = 'VPN Management '.$uplink['name'].' probe '.$number;
                $routes[] = [
                    'comment' => $probeComment,
                    'attributes' => 'dst-address='.$probe.'/32 scope=10 gateway='.$this->quote($uplink['gateway']),
                ];
                $routes[] = [
                    'comment' => 'VPN Management '.$uplink['name'].' default '.$number,
                    'attributes' => 'dst-address=0.0.0.0/0 gateway='.$probe
                        .' distance='.$uplink['distance'].' target-scope=11 check-gateway=ping',
                ];
            }
        }

        $lines = [
            '# MikroTik RouterOS v7 - dual-WAN recursive failover',
            '# ISP 1 is preferred; ISP 2 takes over when both ISP 1 probes are unreachable.',
            '# ISP 1 becomes preferred again automatically when either ISP 1 probe is reachable.',
            '# Two independent probes per ISP reduce failover caused by one probe host being unavailable.',
            '# Re-running this script updates only routes with these exact comments.',
            '',
            '/ip route',
        ];

        foreach ($routes as $route) {
            $lines[] = $this->upsertRoute($route['comment'], $route['attributes']);
        }

        return implode("\n", $lines)."\n";
    }

    private function upsertRoute(string $comment, string $attributes): string
    {
        $quotedComment = $this->quote($comment);

        return ':if ([:len [/ip route find where comment='.$quotedComment.']] = 0) do={ /ip route add '
            .$attributes.' comment='.$quotedComment.' } else={ /ip route set [find where comment='.$quotedComment.'] '
            .$attributes.' comment='.$quotedComment.' }';
    }

    private function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }
}
