<?php

namespace Tests\Unit;

use App\Models\Server;
use App\Services\RouterOsScriptGenerator;
use PHPUnit\Framework\TestCase;

class RouterOsScriptGeneratorTest extends TestCase
{
    public function test_it_escapes_routeros_values_and_routes_speedtest_address_lists(): void
    {
        $server = new Server(['host' => 'router.example.test', 'public_ip' => '203.0.113.10']);
        $script = (new RouterOsScriptGenerator())->generate($server, [
            'username' => 'branch-01',
            'password' => 'quote" dollar$ slash\\',
        ]);

        $this->assertStringContainsString('password="quote\\" dollar\\$ slash\\\\"', $script);
        $this->assertStringContainsString('dst-address-list="speedtest"', $script);
        $this->assertStringContainsString('address="10.0.0.0/8" list="IP-Lokal"', $script);
        $this->assertStringContainsString('address="www.speedtest.net" list="speedtest"', $script);
        $this->assertStringContainsString('connect-to="router.example.test"', $script);
        $this->assertStringNotContainsString('connect-to="203.0.113.10"', $script);
    }

    public function test_it_uses_the_complete_username_in_the_sstp_client(): void
    {
        $script = (new RouterOsScriptGenerator())->generate(
            new Server(['host' => 'router.example.test']),
            ['username' => 'branch.full-user-01', 'password' => 'strong-password'],
        );

        $this->assertStringContainsString('user="branch.full-user-01"', $script);
    }

    public function test_it_can_generate_a_vpn_only_script_without_speedtest_routing(): void
    {
        $script = (new RouterOsScriptGenerator())->generate(
            new Server(['host' => 'router.example.test']),
            ['username' => 'branch-01', 'password' => 'strong-password'],
            includeSpeedtest: false,
        );

        $this->assertStringContainsString('/interface sstp-client', $script);
        $this->assertStringContainsString('password="strong-password"', $script);
        $this->assertStringNotContainsString('/routing table', $script);
        $this->assertStringNotContainsString('/ip firewall address-list', $script);
        $this->assertStringNotContainsString('/ip firewall mangle', $script);
        $this->assertStringNotContainsString('/ip route', $script);
        $this->assertStringNotContainsString('/ip firewall nat', $script);
    }

    public function test_it_generates_one_sstp_interface_with_three_endpoint_failover(): void
    {
        $script = (new RouterOsScriptGenerator())->generate(
            new Server(['host' => 'primary.example.test', 'public_ip' => '203.0.113.10']),
            ['username' => 'branch-01', 'password' => 'strong-password'],
            ['primary.example.test', 'backup.example.test', 'secondary.example.test'],
        );

        $this->assertSame(1, substr_count($script, 'add name="sstp-speedtest-'));
        $this->assertSame(3, substr_count($script, 'type=tcp-conn port=443'));
        $this->assertStringContainsString('connect-to="primary.example.test"', $script);
        $this->assertStringContainsString('add host="backup.example.test" type=tcp-conn port=443', $script);
        $this->assertStringContainsString('add host="secondary.example.test" type=tcp-conn port=443', $script);
        $this->assertStringContainsString(':set nextEndpoint "backup.example.test"', $script);
        $this->assertStringContainsString(':set nextEndpoint "secondary.example.test"', $script);
        $this->assertStringContainsString('verify-server-certificate=no', $script);
        $this->assertStringContainsString('sstpPrimaryRetry >= 10', $script);
        $this->assertStringContainsString('interval=1m', $script);
    }

    public function test_it_rejects_control_characters_in_script_values(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        (new RouterOsScriptGenerator())->generate(
            new Server(['host' => 'router.example.test']),
            ['username' => 'branch-01', 'password' => "line\nbreak"],
        );
    }
}
