<?php

namespace SimpleVpn;

final readonly class Config
{
    public function __construct(
        public string $appName,
        public string $adminUsername,
        public string $adminPasswordHash,
        public bool $secureCookie,
        public string $routerHost,
        public int $routerPort,
        public string $routerUsername,
        public string $routerPassword,
        public ?string $routerCaBundle,
        public string $pppProfile,
        public string $ipPool,
        public string $localAddress,
        public string $publicIp,
        public int $portRangeStart,
        public int $portRangeEnd,
    ) {}

    public static function fromEnvironment(): self
    {
        return new self(
            self::env('APP_NAME', 'Simple VPN NAT'),
            self::env('APP_ADMIN_USERNAME'),
            self::env('APP_ADMIN_PASSWORD_HASH'),
            filter_var(self::env('SESSION_SECURE_COOKIE', 'false'), FILTER_VALIDATE_BOOLEAN),
            self::env('ROUTEROS_HOST'),
            (int) self::env('ROUTEROS_PORT', '8433'),
            self::env('ROUTEROS_USERNAME'),
            self::env('ROUTEROS_PASSWORD'),
            self::env('ROUTEROS_CA_BUNDLE') ?: null,
            self::env('ROUTEROS_PPP_PROFILE'),
            self::env('ROUTEROS_IP_POOL'),
            self::env('ROUTEROS_LOCAL_ADDRESS'),
            self::env('ROUTEROS_PUBLIC_IP'),
            (int) self::env('ROUTEROS_PORT_RANGE_START', '45000'),
            (int) self::env('ROUTEROS_PORT_RANGE_END', '45999'),
        );
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        $errors = [];

        foreach ([
            'APP_ADMIN_USERNAME' => $this->adminUsername,
            'APP_ADMIN_PASSWORD_HASH' => $this->adminPasswordHash,
            'ROUTEROS_HOST' => $this->routerHost,
            'ROUTEROS_USERNAME' => $this->routerUsername,
            'ROUTEROS_PASSWORD' => $this->routerPassword,
            'ROUTEROS_PPP_PROFILE' => $this->pppProfile,
            'ROUTEROS_IP_POOL' => $this->ipPool,
            'ROUTEROS_LOCAL_ADDRESS' => $this->localAddress,
        ] as $key => $value) {
            if (trim($value) === '') {
                $errors[] = "{$key} must be set in this app's .env file.";
            }
        }

        $host = trim($this->routerHost, '[]');
        if ($host !== '' && filter_var($host, FILTER_VALIDATE_IP) === false
            && filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            $errors[] = 'ROUTEROS_HOST must be a hostname or IP address.';
        }

        if ($this->localAddress !== ''
            && filter_var($this->localAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            $errors[] = 'ROUTEROS_LOCAL_ADDRESS must be a valid IPv4 address.';
        }

        if ($this->routerPort < 1 || $this->routerPort > 65535) {
            $errors[] = 'ROUTEROS_PORT must be between 1 and 65535.';
        }

        if ($this->portRangeStart < 1
            || $this->portRangeEnd > 65535
            || $this->portRangeStart > $this->portRangeEnd) {
            $errors[] = 'The public port range is invalid.';
        }

        return $errors;
    }

    private static function env(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return is_string($value) ? $value : $default;
    }
}
