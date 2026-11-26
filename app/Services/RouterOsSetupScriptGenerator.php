<?php

namespace App\Services;

use InvalidArgumentException;

class RouterOsSetupScriptGenerator
{
    /**
     * @param  array<string, string>  $input
     */
    public function generate(array $input): string
    {
        $wanInterface = $input['wan_interface'];
        $lanMode = $input['lan_mode'];

        if (! in_array($lanMode, ['ethernet', 'vlan'], true)) {
            throw new InvalidArgumentException('Choose an Ethernet or VLAN LAN interface.');
        }

        $lanInterface = $lanMode === 'ethernet' ? $input['lan_interface'] : $input['vlan_name'];
        $vlanParent = $input['vlan_parent'] ?? '';
        $vlanId = $input['vlan_id'] ?? '';
        $identity = $input['identity'];
        $adminUsername = $input['admin_username'];
        $adminPassword = $input['admin_password'];
        $pppoeService = $input['pppoe_service_name'];
        $hotspotName = $input['hotspot_server_name'];
        $dnsServers = match ($input['dns_servers']) {
            'cloudflare' => '1.1.1.1',
            'google' => '8.8.8.8',
            'both' => '1.1.1.1,8.8.8.8',
            default => throw new InvalidArgumentException('Choose a supported DNS option.'),
        };

        foreach ([$wanInterface, $lanInterface, $adminUsername, $pppoeService, $hotspotName] as $name) {
            if (preg_match('/\A[A-Za-z0-9._-]{1,64}\z/', $name) !== 1) {
                throw new InvalidArgumentException('Interface and service names contain unsupported characters.');
            }
        }

        if ($wanInterface === $lanInterface || ($lanMode === 'vlan' && $wanInterface === $vlanParent)) {
            throw new InvalidArgumentException('The WAN interface and LAN interface must be different.');
        }

        if ($lanMode === 'vlan'
            && (preg_match('/\A[A-Za-z0-9._-]{1,64}\z/', $vlanParent) !== 1
                || ! ctype_digit((string) $vlanId)
                || (int) $vlanId < 1
                || (int) $vlanId > 4094)) {
            throw new InvalidArgumentException('Enter a valid VLAN parent interface, name, and ID.');
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $identity.$adminPassword) === 1
            || mb_strlen($identity) > 64
            || mb_strlen($adminPassword) < 12
            || mb_strlen($adminPassword) > 128) {
            throw new InvalidArgumentException('Identity or administrator password is invalid.');
        }

        [$localAddress, $networkAddress, $prefix, $networkStart, $networkEnd] = $this->network($input['lan_address']);
        $pppoeRange = $this->poolRange(
            $input['pppoe_pool_start'],
            $input['pppoe_pool_end'],
            $networkStart,
            $networkEnd,
            $localAddress,
        );
        $hotspotRange = $this->poolRange(
            $input['hotspot_pool_start'],
            $input['hotspot_pool_end'],
            $networkStart,
            $networkEnd,
            $localAddress,
        );

        [$pppoeStart, $pppoeEnd] = array_map(fn (string $ip): int => $this->ipToInteger($ip), [
            $input['pppoe_pool_start'],
            $input['pppoe_pool_end'],
        ]);
        [$hotspotStart, $hotspotEnd] = array_map(fn (string $ip): int => $this->ipToInteger($ip), [
            $input['hotspot_pool_start'],
            $input['hotspot_pool_end'],
        ]);

        if ($pppoeStart <= $hotspotEnd && $hotspotStart <= $pppoeEnd) {
            throw new InvalidArgumentException('PPPoE and Hotspot address ranges must not overlap.');
        }

        $suffix = substr(hash('sha256', strtolower($wanInterface.'|'.$lanInterface)), 0, 8);
        $pppoePool = 'vpnm-pppoe-pool-'.$suffix;
        $pppoeProfile = 'vpnm-pppoe-profile-'.$suffix;
        $hotspotPool = 'vpnm-hotspot-pool-'.$suffix;
        $hotspotProfile = 'vpnm-hotspot-profile-'.$suffix;
        $hotspotDhcp = 'vpnm-hotspot-dhcp-'.$suffix;
        $hotspotServer = 'vpnm-hotspot-'.$suffix;
        $wanComment = 'VPN Management Setup '.$suffix.' WAN DHCP';
        $lanComment = 'VPN Management Setup '.$suffix.' LAN address';
        $pppoePoolComment = 'VPN Management Setup '.$suffix.' PPPoE pool';
        $pppoeProfileComment = 'VPN Management Setup '.$suffix.' PPPoE profile';
        $pppoeServerComment = 'VPN Management Setup '.$suffix.' PPPoE server';
        $hotspotPoolComment = 'VPN Management Setup '.$suffix.' Hotspot pool';
        $hotspotProfileComment = 'VPN Management Setup '.$suffix.' Hotspot profile';
        $hotspotDhcpComment = 'VPN Management Setup '.$suffix.' Hotspot DHCP';
        $hotspotNetworkComment = 'VPN Management Setup '.$suffix.' Hotspot network';
        $hotspotComment = 'VPN Management Setup '.$suffix.' Hotspot server';
        $natComment = 'VPN Management Setup '.$suffix.' WAN masquerade';
        $vlanComment = 'VPN Management Setup '.$suffix.' VLAN';

        $lines = [
            '# MikroTik RouterOS v7 - generated WAN, LAN, PPPoE, and Hotspot setup',
            '# Review all names, pools, and existing configuration before running this script.',
            '# Create a new full-access administrator before disabling FTP, SSH, and Telnet.',
            $this->assertNoMatch('/user', 'name='.$this->quote($adminUsername), 'Administrator username already exists; choose another name.'),
            $this->assertNoMatch('/ip dhcp-client', 'interface='.$wanInterface.' and comment!='.$this->quote($wanComment), 'A different DHCP client already uses this WAN interface.'),
            $this->assertNoMatch('/interface pppoe-server server', 'interface='.$lanInterface.' and comment!='.$this->quote($pppoeServerComment), 'A PPPoE server already uses this LAN interface.'),
            $this->assertNoMatch('/ip dhcp-server', 'interface='.$lanInterface.' and comment!='.$this->quote($hotspotDhcpComment), 'A DHCP server already uses this LAN interface.'),
            $this->assertNoMatch('/ip hotspot', 'interface='.$lanInterface.' and comment!='.$this->quote($hotspotComment), 'A Hotspot server already uses this LAN interface.'),
            $this->assertNoMatch('/ip hotspot', 'name='.$hotspotName.' and comment!='.$this->quote($hotspotComment), 'Hotspot server name already exists outside this generated setup.'),
            $this->assertNoMatch(
                '/ip dhcp-server network',
                'address='.$networkAddress.'/'.$prefix.' and comment!='.$this->quote($hotspotNetworkComment),
                'A different DHCP network already uses this LAN subnet.',
            ),
            $this->assertNoMatch(
                '/ip address',
                'address='.$input['lan_address'].' and interface!='.$lanInterface,
                'The LAN address is already assigned to another interface.',
            ),
            $this->assertHasMatch('/interface', 'name='.$wanInterface, 'WAN interface does not exist.'),
        ];

        if ($lanMode === 'ethernet') {
            $lines[] = $this->assertHasMatch('/interface', 'name='.$lanInterface, 'LAN interface does not exist.');
        } else {
            $lines[] = $this->assertHasMatch('/interface', 'name='.$vlanParent, 'VLAN parent interface does not exist.');
            $lines[] = $this->assertNoMatch(
                '/interface vlan',
                'name='.$input['vlan_name'].' and comment!='.$this->quote($vlanComment),
                'VLAN name already exists outside this generated setup.',
            );
        }

        array_push($lines,
            '/user add name='.$this->quote($adminUsername).' group=full password='.$this->quote($adminPassword),
            '/system identity set name='.$this->quote($identity),
        );
        $lines[] = $this->upsert('/ip dhcp-client', $wanComment, 'interface='.$wanInterface.' add-default-route=yes disabled=no');

        if ($lanMode === 'vlan') {
            $lines[] = $this->upsert(
                '/interface vlan',
                $vlanComment,
                'name='.$input['vlan_name'].' interface='.$vlanParent.' vlan-id='.$vlanId.' disabled=no',
            );
        }

        $lines[] = $this->ensureAddress($lanInterface, $input['lan_address'], $lanComment);
        $lines[] = $this->upsert(
            '/ip firewall nat',
            $natComment,
            'chain=srcnat action=masquerade out-interface='.$wanInterface.' disabled=no',
        );
        $lines[] = $this->upsert('/ip pool', $pppoePoolComment, 'name='.$pppoePool.' ranges='.$pppoeRange);
        $lines[] = $this->upsert('/ppp profile', $pppoeProfileComment, 'name='.$pppoeProfile
            .' local-address='.$localAddress.' remote-address='.$pppoePool);
        $lines[] = $this->upsert('/interface pppoe-server server', $pppoeServerComment,
            'interface='.$lanInterface.' service-name='.$this->quote($pppoeService)
                .' default-profile='.$pppoeProfile.' one-session-per-host=yes disabled=no');
        $lines[] = $this->upsert('/ip pool', $hotspotPoolComment, 'name='.$hotspotPool.' ranges='.$hotspotRange);
        $lines[] = $this->upsert('/ip dhcp-server', $hotspotDhcpComment,
            'name='.$hotspotDhcp.' interface='.$lanInterface.' address-pool='.$hotspotPool.' disabled=no');
        $lines[] = $this->upsert('/ip dhcp-server network', $hotspotNetworkComment,
            'address='.$networkAddress.'/'.$prefix.' gateway='.$localAddress.' dns-server='.$localAddress);
        $lines[] = $this->upsert('/ip hotspot profile', $hotspotProfileComment,
            'name='.$hotspotProfile.' hotspot-address='.$localAddress);
        $lines[] = $this->upsert('/ip hotspot', $hotspotComment,
            'name='.$hotspotServer.' interface='.$lanInterface.' address-pool='.$hotspotPool
                .' profile='.$hotspotProfile.' disabled=no');
        $lines[] = '/ip dns set servers='.$dnsServers.' allow-remote-requests=yes';
        $lines[] = '/ip service set [/ip service find where name=ftp] disabled=yes';
        $lines[] = '/ip service set [/ip service find where name=ssh] disabled=yes';
        $lines[] = '/ip service set [/ip service find where name=telnet] disabled=yes';

        return implode("\n", $lines)."\n";
    }

    private function assertNoMatch(string $menu, string $where, string $message): string
    {
        $find = '['.$menu.' find where '.$where.']';

        return ':if ([:len '.$find.'] > 0) do={ :error '.$this->quote($message).' }';
    }

    private function assertHasMatch(string $menu, string $where, string $message): string
    {
        $find = '['.$menu.' find where '.$where.']';

        return ':if ([:len '.$find.'] = 0) do={ :error '.$this->quote($message).' }';
    }

    private function upsert(string $menu, string $comment, string $attributes): string
    {
        $quotedComment = $this->quote($comment);
        $find = '['.$menu.' find where comment='.$quotedComment.']';

        return ':if ([:len '.$find.'] = 0) do={ '.$menu.' add '.$attributes.' comment='.$quotedComment
            .' } else={ '.$menu.' set '.$find.' '.$attributes.' comment='.$quotedComment.' }';
    }

    private function ensureAddress(string $interface, string $address, string $comment): string
    {
        $quotedComment = $this->quote($comment);
        $find = '[/ip address find where address='.$address.' and interface='.$interface.']';

        return ':if ([:len '.$find.'] = 0) do={ /ip address add address='.$address.' interface='.$interface
            .' comment='.$quotedComment.' } else={ /ip address set '.$find.' disabled=no comment='.$quotedComment.' }';
    }

    /**
     * @return array{string, string, int, int, int}
     */
    private function network(string $cidr): array
    {
        if (preg_match('/\A([^\/]+)\/(\d{1,2})\z/', $cidr, $matches) !== 1
            || filter_var($matches[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new InvalidArgumentException('Enter a valid IPv4 address with a prefix, such as 192.168.10.1/24.');
        }

        $prefix = (int) $matches[2];

        if ($prefix < 1 || $prefix > 30) {
            throw new InvalidArgumentException('LAN prefix must be between /1 and /30 to provide usable host addresses.');
        }

        $ip = $this->ipToInteger($matches[1]);
        $mask = (0xFFFFFFFF << (32 - $prefix)) & 0xFFFFFFFF;
        $networkStart = $ip & $mask;
        $networkEnd = $networkStart | (~$mask & 0xFFFFFFFF);

        if ($ip === $networkStart || $ip === $networkEnd) {
            throw new InvalidArgumentException('The LAN address must be a usable host address, not the network or broadcast address.');
        }

        return [$matches[1], long2ip($networkStart), $prefix, $networkStart, $networkEnd];
    }

    private function poolRange(string $start, string $end, int $networkStart, int $networkEnd, string $gateway): string
    {
        $startInteger = $this->ipToInteger($start);
        $endInteger = $this->ipToInteger($end);
        $gatewayInteger = $this->ipToInteger($gateway);

        if ($startInteger > $endInteger
            || $startInteger <= $networkStart
            || $endInteger >= $networkEnd
            || $startInteger < $networkStart
            || $endInteger > $networkEnd
            || ($gatewayInteger >= $startInteger && $gatewayInteger <= $endInteger)) {
            throw new InvalidArgumentException('Each IP pool must be an ordered range of usable LAN addresses and must exclude the router address.');
        }

        return $start.'-'.$end;
    }

    private function ipToInteger(string $ip): int
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new InvalidArgumentException('Enter valid IPv4 addresses for the LAN and IP pools.');
        }

        return unpack('Naddress', inet_pton($ip))['address'];
    }

    private function quote(string $value): string
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new InvalidArgumentException('RouterOS script values cannot contain control characters.');
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }
}
