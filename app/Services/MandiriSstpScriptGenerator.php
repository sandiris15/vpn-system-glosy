<?php

namespace App\Services;

class MandiriSstpScriptGenerator
{
    /**
     * @param  array{host: string, port: int, username: string, password: string}  $connection
     * @param  array{vpn_username: string, vpn_password: string, sstp_port: int, add_default_route: bool}  $account
     */
    public function generate(array $connection, array $account): string
    {
        $interface = 'sstp-mandiri-'.substr(hash('sha256', $account['vpn_username']), 0, 8);
        $quote = fn (string $value): string => (new RouterOsScriptGenerator())->quoteString($value);
        $lines = [
            '# RouterOS v7 SSTP client',
            '/interface sstp-client',
            'add name='.$quote($interface)
                .' connect-to='.$quote(trim($connection['host'], '[]'))
                .' connect-port='.$account['sstp_port']
                .' user='.$quote($account['vpn_username'])
                .' password='.$quote($account['vpn_password'])
                .' profile=default-encryption verify-server-certificate=yes disabled=no',
        ];

        if ($account['add_default_route']) {
            $lines[] = '';
            $lines[] = '/ip route';
            $lines[] = 'add dst-address=0.0.0.0/0 gateway='.$quote($interface).' distance=2';
        }

        return implode("\n", $lines)."\n";
    }
}
