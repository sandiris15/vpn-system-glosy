<?php

declare(strict_types=1);

use SimpleVpn\Config;
use SimpleVpn\RouterOsApi;
use SimpleVpn\ScriptExporter;
use SimpleVpn\VpnProvisioner;

require dirname(__DIR__).'/vendor/autoload.php';

final class FakeRouterOs implements RouterOsApi
{
    /**
     * @var array<string, list<array<string, mixed>>>
     */
    public array $collections = [
        'ppp/secret' => [],
        'ppp/profile' => [['name' => 'sstp-profile']],
        'ip/pool' => [['name' => 'sstp-pool', 'ranges' => '10.10.0.1-10.10.0.5']],
        'ppp/active' => [],
        'ip/address' => [],
        'ip/firewall/nat' => [],
    ];

    /**
     * @var list<array{resource: string, attributes: array<string, mixed>}>
     */
    public array $creates = [];

    /**
     * @var list<array{resource: string, id: string}>
     */
    public array $deletes = [];

    public ?string $failOnCreateResource = null;

    private int $nextId = 1;

    public function collection(string $resource): array
    {
        return $this->collections[$resource] ?? [];
    }

    public function create(string $resource, array $attributes): array
    {
        if ($resource === $this->failOnCreateResource) {
            throw new RuntimeException('Simulated RouterOS rejection.');
        }

        $id = '*'.$this->nextId++;
        $record = ['.id' => $id] + $attributes;
        $this->collections[$resource][] = $record;
        $this->creates[] = ['resource' => $resource, 'attributes' => $attributes];

        return $record;
    }

    public function delete(string $resource, string $id): void
    {
        $this->deletes[] = ['resource' => $resource, 'id' => $id];
        $this->collections[$resource] = array_values(array_filter(
            $this->collections[$resource] ?? [],
            fn (array $record): bool => ($record['.id'] ?? null) !== $id,
        ));
    }
}

function assertThat(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function expectFailure(callable $callback, string $class, string $messagePart): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        assertThat($exception instanceof $class, 'Unexpected exception: '.$exception::class);
        assertThat(str_contains($exception->getMessage(), $messagePart), 'Unexpected error message: '.$exception->getMessage());

        return;
    }

    throw new RuntimeException('Expected '.$class.' was not thrown.');
}

function withProvisioner(FakeRouterOs $router, callable $callback): mixed
{
    $directory = sys_get_temp_dir().'/simple-vpn-test-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700, true);

    $config = new Config(
        'Simple VPN NAT',
        'operator',
        'not-used-in-service-tests',
        false,
        'router.example.test',
        8433,
        'api-user',
        'api-password',
        null,
        'sstp-profile',
        'sstp-pool',
        '10.10.0.1',
        '163.61.245.2',
        45000,
        45002,
    );

    try {
        return $callback(new VpnProvisioner($router, $config, $directory.'/provision.lock'));
    } finally {
        $lockPath = $directory.'/provision.lock';
        if (is_file($lockPath)) {
            unlink($lockPath);
        }
        rmdir($directory);
    }
}

$tests = [
    'generates an SSTP and Speedtest script with the requested routing data' => function (): void {
        $script = (new ScriptExporter())->sstpSpeedtestClient(
            'branch-01',
            'secret-$password',
            '163.61.245.2',
            '10.20.188.1',
        );

        assertThat(str_contains($script, 'connect-to="163.61.245.2"'), 'Script should connect to the configured public IP.');
        assertThat(str_contains($script, 'user="branch-01" password="secret-\\$password"'), 'Script should escape credential variable characters.');
        assertThat(str_contains($script, 'verify-server-certificate=no'), 'Script should follow the selected certificate setting.');
        assertThat(str_contains($script, 'address="10.0.0.0/8" list="IP-Lokal"'), 'Script should include the local address list.');
        assertThat(str_contains($script, 'address="www.nperf.com" list="speedtest"'), 'Script should include the Speedtest address list.');
        assertThat(str_contains($script, 'gateway="10.20.188.1" routing-table="Speedtest"'), 'Script should route through the configured PPP gateway.');
        assertThat(str_contains($script, 'find where list="speedtest" and address="www.nperf.com"'), 'Script should avoid duplicate address-list entries.');
    },
    'rejects an invalid static local address' => function (): void {
        $config = new Config(
            'Simple VPN NAT',
            'operator',
            'not-used-in-service-tests',
            false,
            'router.example.test',
            8433,
            'api-user',
            'api-password',
            null,
            'sstp-profile',
            'sstp-pool',
            'not-an-ip',
            '163.61.245.2',
            45000,
            45002,
        );

        assertThat(
            in_array('ROUTEROS_LOCAL_ADDRESS must be a valid IPv4 address.', $config->errors(), true),
            'Invalid local address should be rejected.',
        );
    },
    'allocates unused address and port and exports a NAT script' => function (): void {
        $router = new FakeRouterOs();
        $router->collections['ppp/secret'][] = ['name' => 'existing', 'remote-address' => '10.10.0.2'];
        $router->collections['ppp/active'][] = ['address' => '10.10.0.3'];
        $router->collections['ip/firewall/nat'][] = [
            '.id' => '*nat0',
            'chain' => 'dstnat',
            'action' => 'dst-nat',
            'protocol' => 'tcp',
            'dst-address' => '163.61.245.0/24',
            'dst-port' => '45000',
            'disabled' => 'false',
        ];

        withProvisioner($router, function (VpnProvisioner $provisioner) use ($router): void {
            $result = $provisioner->create([
                'username' => 'customer-01',
                'password' => 'strong-password',
                'protocol' => 'tcp',
                'public_port' => '45002',
                'internal_port' => '8291',
            ]);

            assertThat($result['remote_address'] === '10.10.0.4', 'Expected the first free pool address.');
            assertThat($result['nat_rule']['dst-port'] === '45001', 'Expected the first free public port.');
            assertThat(str_contains((string) $result['script'], 'protocol="tcp"'), 'NAT script should include protocol.');
            assertThat(str_contains((string) $result['script'], 'comment="customer-01-winbox"'), 'NAT script should include the generated comment.');
            assertThat($result['nat_rule']['to-ports'] === '8291', 'Winbox should target port 8291.');
            assertThat($result['nat_rule']['comment'] === 'customer-01-winbox', 'Winbox comment should include username and service.');
            assertThat(count($router->creates) === 2, 'Expected one secret and one NAT rule.');
            $createdSecret = array_values(array_filter(
                $router->collections['ppp/secret'],
                fn (array $secret): bool => ($secret['name'] ?? null) === 'customer-01',
            ))[0] ?? [];
            assertThat(($createdSecret['local-address'] ?? null) === '10.10.0.1', 'Secret should use the configured static local address.');
            assertThat(($createdSecret['remote-address'] ?? null) === '10.10.0.4', 'Secret should save its allocated remote address.');
            assertThat(! array_key_exists('comment', $createdSecret), 'PPP Secret should not include a comment.');
        });
    },
    'generates built-in service comments and accepts manual port comments' => function (): void {
        foreach (['8728' => 'mikrotik-api', '80' => 'webfig'] as $port => $suffix) {
            $router = new FakeRouterOs();
            $port = (string) $port;

            withProvisioner($router, function (VpnProvisioner $provisioner) use ($router, $port, $suffix): void {
                $provisioner->create([
                    'username' => 'branch',
                    'password' => 'strong-password',
                    'protocol' => 'tcp',
                    'internal_port' => $port,
                ]);

                $nat = $router->collections['ip/firewall/nat'][0] ?? [];
                assertThat(($nat['comment'] ?? null) === 'branch-'.$suffix, 'Built-in service should generate its service comment.');
                assertThat(($nat['to-ports'] ?? null) === $port, 'Built-in service should use its selected internal port.');
            });
        }

        $router = new FakeRouterOs();

        withProvisioner($router, function (VpnProvisioner $provisioner) use ($router): void {
            expectFailure(
                fn () => $provisioner->create([
                    'username' => 'manual-branch',
                    'password' => 'strong-password',
                    'protocol' => 'tcp',
                    'internal_port' => 'custom',
                    'custom_internal_port' => '8080',
                ]),
                InvalidArgumentException::class,
                'Comment manual',
            );
            assertThat($router->creates === [], 'Manual port without a comment must not create RouterOS resources.');

            $provisioner->create([
                'username' => 'manual-branch',
                'password' => 'strong-password',
                'protocol' => 'tcp',
                'public_port' => '45002',
                'internal_port' => 'custom',
                'custom_internal_port' => '8080',
                'comment' => 'manual-branch-admin',
            ]);

            $nat = $router->collections['ip/firewall/nat'][0] ?? [];
            assertThat(($nat['dst-port'] ?? null) === '45000', 'Public port should be automatically allocated even if a request supplies one.');
            assertThat(($nat['to-ports'] ?? null) === '8080', 'Manual service should use its custom internal port.');
            assertThat(($nat['comment'] ?? null) === 'manual-branch-admin', 'Manual service should use its provided comment.');
        });
    },
    'rejects duplicate usernames without creating configuration' => function (): void {
        $router = new FakeRouterOs();
        $router->collections['ppp/secret'][] = ['name' => 'Customer-01', 'remote-address' => '10.10.0.2'];

        withProvisioner($router, function (VpnProvisioner $provisioner) use ($router): void {
            expectFailure(
                fn () => $provisioner->create([
                    'username' => 'customer-01',
                    'password' => 'strong-password',
                    'protocol' => '',
                ]),
                InvalidArgumentException::class,
                'sudah ada',
            );
            assertThat($router->creates === [], 'Duplicate username must not create RouterOS resources.');
        });
    },
    'rejects when the public port range is full and rolls back a secret if NAT creation fails' => function (): void {
        $router = new FakeRouterOs();
        $router->collections['ip/firewall/nat'][] = [
            '.id' => '*nat0',
            'chain' => 'dstnat',
            'action' => 'dst-nat',
            'protocol' => 'tcp',
            'dst-address' => '163.61.245.2',
            'dst-port' => '45000-45002',
            'disabled' => 'false',
        ];

        withProvisioner($router, function (VpnProvisioner $provisioner) use ($router): void {
            expectFailure(
                fn () => $provisioner->create([
                    'username' => 'customer-02',
                    'password' => 'strong-password',
                    'protocol' => 'tcp',
                    'internal_port' => '8291',
                ]),
                RuntimeException::class,
                'Tidak ada port publik kosong',
            );
            assertThat($router->creates === [], 'An exhausted public port range must not create a secret.');
        });

        $router = new FakeRouterOs();
        $router->failOnCreateResource = 'ip/firewall/nat';

        withProvisioner($router, function (VpnProvisioner $provisioner) use ($router): void {
            expectFailure(
                fn () => $provisioner->create([
                    'username' => 'customer-03',
                    'password' => 'strong-password',
                    'protocol' => 'tcp',
                    'public_port' => '45000',
                    'internal_port' => '80',
                ]),
                RuntimeException::class,
                'Provisioning gagal',
            );
            assertThat(count($router->deletes) === 1, 'The newly created secret should be rolled back.');
            assertThat($router->collections['ppp/secret'] === [], 'Rollback should remove the new secret.');
        });
    },
];

foreach ($tests as $name => $test) {
    $test();
    print("PASS {$name}\n");
}

print(count($tests)." tests passed.\n");
