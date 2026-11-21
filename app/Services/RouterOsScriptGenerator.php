<?php

namespace App\Services;

use App\Models\Server;
use InvalidArgumentException;
use UnexpectedValueException;

class RouterOsScriptGenerator
{
    private const LOCAL_NETWORKS = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
    ];

    /**
     * @var list<array{address: string, comment?: string}>
     */
    private const SPEEDTEST_ADDRESSES = [
        ['address' => '72.52.64.0/18'],
        ['address' => 'xmyip.com'],
        ['address' => 'www.yougetsignal.com'],
        ['address' => 'expressvpn.com'],
        ['address' => 'www.expressvpn.com'],
        ['address' => 'whatismyip.net'],
        ['address' => 'speedtestcustom.com', 'comment' => 'whatismyip.net'],
        ['address' => 'income.speedtestcustom.com', 'comment' => 'speedtestcustom.com'],
        ['address' => 'iplocation.net', 'comment' => 'income.speedtestcustom.com'],
        ['address' => 'www.iplocation.net', 'comment' => 'iplocation.net'],
        ['address' => 'www.astrill.com', 'comment' => 'www.iplocation.net'],
        ['address' => 'www.privateinternetaccess.com', 'comment' => 'www.astrill.com'],
        ['address' => 'mxtoolbox.com', 'comment' => 'www.privateinternetaccess.com'],
        ['address' => 'ifconfig.co', 'comment' => 'mxtoolbox.com'],
        ['address' => 'whatismyip.org', 'comment' => 'ifconfig.co'],
        ['address' => 'www.goldenfrog.com', 'comment' => 'whatismyip.org'],
        ['address' => 'www.mxtoolbox.com', 'comment' => 'www.goldenfrog.com'],
        ['address' => 'www.ultratools.com', 'comment' => 'www.mxtoolbox.com'],
        ['address' => 'www.ip-adress.eu', 'comment' => 'www.ultratools.com'],
        ['address' => 'iplogger.org'],
        ['address' => 'www.vermiip.es'],
        ['address' => 'www.purevpn.com'],
        ['address' => 'www.whatismybrowser.com'],
        ['address' => 'zenmate.com'],
        ['address' => 'www.ipchicken.com'],
        ['address' => 'bittrex.com'],
        ['address' => 'whatismyip.li'],
        ['address' => 'www.ipburger.com'],
        ['address' => 'cbn.net.id'],
        ['address' => 'whatismyip4.com'],
        ['address' => 'www.inmotionhosting.com'],
        ['address' => 'nordvpn.com'],
        ['address' => 'wolframalpha.com'],
        ['address' => 'cactusvpn.com'],
        ['address' => 'www.cactusvpn.com'],
        ['address' => 'm.wolframalpha.com'],
        ['address' => 'ipcow.com'],
        ['address' => 'whatismycountry.com'],
        ['address' => 'passwordsgenerator.net'],
        ['address' => 'att-services.net'],
        ['address' => 'wtfismyip.com'],
        ['address' => 'whatismyip.network'],
        ['address' => 'ipinfo.info'],
        ['address' => 'encodable.com'],
        ['address' => 'www.overplay.net'],
        ['address' => 'myipaddress.com'],
        ['address' => 'www.myipaddress.com'],
        ['address' => 'whoer.net'],
        ['address' => 'whatismyip.com'],
        ['address' => 'www.speedtest.net'],
        ['address' => 'c.speedtest.net'],
        ['address' => 'www.ipleak.net'],
        ['address' => 'ipleak.net'],
        ['address' => 'whatismyipaddress.com'],
        ['address' => 'whatismyip.host'],
        ['address' => 'bearsmyip.com'],
        ['address' => 'check-host.net'],
        ['address' => 'hide.me'],
        ['address' => 'ipv6test.hide.me'],
        ['address' => 'ipleak.com'],
        ['address' => 'www.perfect-privacy.com'],
        ['address' => 'perfect-privacy.com'],
        ['address' => 'www.whatsmyip.org'],
        ['address' => 'whatsmyip.org'],
        ['address' => 'ipsaya.com'],
        ['address' => 'cekipsaya.com'],
        ['address' => 'intip.net'],
        ['address' => 'speedtest.net'],
        ['address' => 'myip.com'],
        ['address' => 'bgp.he.net'],
        ['address' => 'speedtest.cbn.net.id'],
        ['address' => 'www.nperf.com'],
    ];

    /**
     * @param  array{username: string, password: string}  $account
     */
    /**
     * @param  array<int, string>|null  $endpoints
     */
    public function generate(Server $server, array $account, ?array $endpoints = null, bool $includeSpeedtest = true): string
    {
        $endpoints ??= [$server->host];

        if ($endpoints === [] || collect($endpoints)->contains(
            fn (mixed $endpoint): bool => ! is_string($endpoint)
                || trim($endpoint) === ''
                || preg_match('/[\x00-\x20\x7F]/', $endpoint) === 1,
        )) {
            throw new InvalidArgumentException('Configure a public RouterOS host or IP before generating the SSTP script.');
        }

        $endpoints = array_values(array_unique(array_map(
            fn (string $endpoint): string => trim($endpoint, '[]'),
            $endpoints,
        )));

        foreach ($endpoints as $endpoint) {
            if (filter_var($endpoint, FILTER_VALIDATE_IP) === false
                && filter_var($endpoint, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
                throw new InvalidArgumentException('Every cluster endpoint must be a valid hostname or IP address.');
            }
        }
        $nameSuffix = substr(hash('sha256', $account['username']), 0, 8);
        $interface = 'sstp-speedtest-'.$nameSuffix;
        $routingTable = 'speedtest-'.$nameSuffix;
        $comment = 'SSTP Console Speedtest '.$nameSuffix;
        $primaryEndpoint = $endpoints[0];

        $lines = [
            '# RouterOS v7 - SSTP VPN client'.($includeSpeedtest ? ' with Speedtest routing' : ''),
            '# verify-server-certificate=no disables SSTP server certificate validation.',
        ];

        if ($includeSpeedtest) {
            array_push(
                $lines,
                '# This only routes destinations in the speedtest address list through this VPN.',
                '/routing table',
                ':if ([:len [/routing table find where name='.$this->quoteString($routingTable).']] = 0) do={ /routing table add fib name='.$this->quoteString($routingTable).' }',
            );
        } else {
            $lines[] = '# No Speedtest routing rules are included.';
        }

        array_push($lines,
            '',
            '/interface sstp-client',
            ':if ([:len [/interface sstp-client find where name='.$this->quoteString($interface).']] = 0) do={ /interface sstp-client add name='.$this->quoteString($interface)
                .' connect-to='.$this->quoteString($primaryEndpoint)
                .' connect-port=443 user='.$this->quoteString($account['username'])
                .' password='.$this->quoteString($account['password'])
                .' authentication=mschap2 verify-server-certificate=no disabled=no'
                .' comment='.$this->quoteString($comment).' }',
        );

        if (count($endpoints) > 1) {
            array_push($lines, ...$this->failoverLines($endpoints, $interface, $comment, $nameSuffix));
        }

        if (! $includeSpeedtest) {
            return implode("\n", $lines);
        }

        foreach (self::LOCAL_NETWORKS as $address) {
            $lines[] = $this->addressListAdd('IP-Lokal', $address);
        }

        foreach (self::SPEEDTEST_ADDRESSES as $entry) {
            $lines[] = $this->addressListAdd('speedtest', $entry['address'], $entry['comment'] ?? null);
        }

        array_push($lines,
            '',
            '/ip firewall mangle',
            ':if ([:len [/ip firewall mangle find where comment='.$this->quoteString($comment).']] = 0) do={ /ip firewall mangle add action=mark-routing chain=prerouting comment='.$this->quoteString($comment)
                .' dst-address-list='.$this->quoteString('speedtest')
                .' new-routing-mark='.$this->quoteString($routingTable)
                .' passthrough=yes }',
            '',
            '/ip route',
            ':if ([:len [/ip route find where comment='.$this->quoteString($comment).']] = 0) do={ /ip route add dst-address='.$this->quoteString('0.0.0.0/0')
                .' gateway='.$this->quoteString($interface)
                .' routing-table='.$this->quoteString($routingTable)
                .' comment='.$this->quoteString($comment).' }',
            '',
            '/ip firewall nat',
            ':if ([:len [/ip firewall nat find where comment='.$this->quoteString($comment).']] = 0) do={ /ip firewall nat add chain=srcnat out-interface='.$this->quoteString($interface)
                .' action=masquerade comment='.$this->quoteString($comment).' }',
            '',
        );

        return implode("\n", $lines);
    }

    private function addressListAdd(string $list, string $address, ?string $comment = null): string
    {
        $condition = 'list='.$this->quoteString($list).' and address='.$this->quoteString($address);
        $add = '/ip firewall address-list add address='.$this->quoteString($address).' list='.$this->quoteString($list);

        if ($comment !== null) {
            $add .= ' comment='.$this->quoteString($comment);
        }

        return ':if ([:len [/ip firewall address-list find where '.$condition.']] = 0) do={ '.$add.' }';
    }

    /**
     * @param  array<int, string>  $endpoints
     * @return array<int, string>
     */
    private function failoverLines(array $endpoints, string $interface, string $comment, string $suffix): array
    {
        $scriptName = 'sstp-failover-'.$suffix;
        $scheduleName = 'sstp-failover-'.$suffix;
        $probeComments = array_map(fn (int $index): string => $comment.' endpoint '.($index + 1), array_keys($endpoints));
        $lines = [
            '',
            '# One SSTP interface is switched between endpoints; only one node is connected at a time.',
            '# TCP/443 is checked every 30 seconds; primary is retried after 10 minutes on a backup.',
            '/tool netwatch',
        ];

        foreach ($endpoints as $index => $endpoint) {
            $lines[] = 'add host='.$this->quoteString($endpoint)
                .' type=tcp-conn port=443 interval=30s comment='.$this->quoteString($probeComments[$index]);
        }

        $lines = [
            ...$lines,
            '',
            '/system script',
            'add name='.$this->quoteString($scriptName).' policy=read,write,test source={',
            '    :global sstpEndpointIndex',
            '    :global sstpPrimaryRetry',
            '    :if ([:typeof $sstpEndpointIndex] = "nothing") do={ :set sstpEndpointIndex 0 }',
            '    :if ([:typeof $sstpPrimaryRetry] = "nothing") do={ :set sstpPrimaryRetry 0 }',
            '    :local interfaceName '.$this->quoteString($interface),
        ];

        foreach ($probeComments as $index => $probeComment) {
            $lines[] = '    :local endpointStatus'.$index.' [/tool netwatch get [find where comment='
                .$this->quoteString($probeComment).'] status]';
        }

        $lines = [
            ...$lines,
            '    :local isRunning [/interface sstp-client get [find where name=$interfaceName] running]',
            '    :if ($isRunning) do={',
            '        :if ($sstpEndpointIndex > 0) do={',
            '            :set sstpPrimaryRetry ($sstpPrimaryRetry + 1)',
            '            :if (($sstpPrimaryRetry >= 10) && ($endpointStatus0 = "up")) do={',
            '                /interface sstp-client set [find where name=$interfaceName] disabled=yes',
            '                /interface sstp-client set [find where name=$interfaceName] connect-to='.$this->quoteString($endpoints[0]),
            '                /interface sstp-client set [find where name=$interfaceName] disabled=no',
            '                :set sstpEndpointIndex 0',
            '                :set sstpPrimaryRetry 0',
            '            }',
            '        } else={ :set sstpPrimaryRetry 0 }',
            '        :return',
            '    }',
            '    :set sstpPrimaryRetry 0',
            '    :local nextIndex (($sstpEndpointIndex + 1) % '.count($endpoints).')',
            '    :for offset from=0 to='.count($endpoints).' do={',
            '        :local candidate (($sstpEndpointIndex + $offset + 1) % '.count($endpoints).')',
            '        :local candidateStatus "down"',
        ];

        foreach ($probeComments as $index => $_probeComment) {
            $lines[] = '        :if ($candidate = '.$index.') do={ :set candidateStatus $endpointStatus'.$index.' }';
        }

        $lines = [
            ...$lines,
            '        :if (($candidateStatus = "up") || ($offset = '.count($endpoints).')) do={',
            '            :set nextIndex $candidate',
            '            :break',
            '        }',
            '    }',
            '    :local nextEndpoint '.$this->quoteString($endpoints[0]),
        ];

        foreach ($endpoints as $index => $endpoint) {
            $lines[] = '    :if ($nextIndex = '.$index.') do={ :set nextEndpoint '.$this->quoteString($endpoint).' }';
        }

        return [
            ...$lines,
            '    /interface sstp-client set [find where name=$interfaceName] disabled=yes',
            '    /interface sstp-client set [find where name=$interfaceName] connect-to=$nextEndpoint',
            '    /interface sstp-client set [find where name=$interfaceName] disabled=no',
            '    :set sstpEndpointIndex $nextIndex',
            '}',
            '',
            '/system scheduler',
            'add name='.$this->quoteString($scheduleName)
                .' interval=1m on-event='.$this->quoteString('/system script run '.$scriptName)
                .' policy=read,write,test',
        ];
    }

    public function quoteString(string $value): string
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new UnexpectedValueException('RouterOS script values cannot contain control characters.');
        }

        $escaped = str_replace(
            ['\\', '"', '$'],
            ['\\\\', '\\"', '\\$'],
            $value,
        );

        return '"'.$escaped.'"';
    }
}
