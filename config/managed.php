<?php

return [
    'router_os_ca_bundle' => env('ROUTEROS_CA_BUNDLE') ?: null,
    'vpn_primary_only' => env('VPN_PRIMARY_ONLY', true),
    'vpn_local_address' => env('VPN_LOCAL_ADDRESS', '10.20.188.1'),
    'operator_username' => env('OPERATOR_USERNAME', 'operator'),
    'operator_password' => env('OPERATOR_PASSWORD'),
    'additional_servers' => array_values(array_filter([
        [
            'name' => env('CLUSTER_SERVER_2_NAME', 'Router-JKT'),
            'priority' => 1,
            'host' => env('CLUSTER_SERVER_2_HOST'),
            'port' => (int) env('CLUSTER_SERVER_2_PORT', 8433),
            'public_ip' => env('CLUSTER_SERVER_2_PUBLIC_IP') ?: null,
            'port_range_start' => env('CLUSTER_SERVER_2_PORT_RANGE_START') ?: null,
            'port_range_end' => env('CLUSTER_SERVER_2_PORT_RANGE_END') ?: null,
        ],
        [
            'name' => env('CLUSTER_SERVER_3_NAME', 'Router-Remote-JKT'),
            'priority' => 2,
            'host' => env('CLUSTER_SERVER_3_HOST'),
            'port' => (int) env('CLUSTER_SERVER_3_PORT', 8433),
            'public_ip' => env('CLUSTER_SERVER_3_PUBLIC_IP') ?: null,
            'port_range_start' => env('CLUSTER_SERVER_3_PORT_RANGE_START') ?: null,
            'port_range_end' => env('CLUSTER_SERVER_3_PORT_RANGE_END') ?: null,
        ],
    ], fn (array $server): bool => is_string($server['host']) && trim($server['host']) !== '')),
    'initial_admin' => [
        'name' => env('INITIAL_ADMIN_NAME'),
        'email' => env('INITIAL_ADMIN_EMAIL'),
        'password' => env('INITIAL_ADMIN_PASSWORD'),
    ],
    'initial_server' => [
        'name' => env('INITIAL_SERVER_NAME'),
        'host' => env('INITIAL_SERVER_HOST'),
        'port' => (int) env('INITIAL_SERVER_PORT', 443),
        'username' => env('INITIAL_SERVER_USERNAME'),
        'password' => env('INITIAL_SERVER_PASSWORD'),
        'ppp_profile' => env('INITIAL_SERVER_PPP_PROFILE'),
        'ip_pool' => env('INITIAL_SERVER_IP_POOL'),
        'public_ip' => env('INITIAL_SERVER_PUBLIC_IP') ?: null,
        'port_range_start' => env('INITIAL_SERVER_PORT_RANGE_START') ?: null,
        'port_range_end' => env('INITIAL_SERVER_PORT_RANGE_END') ?: null,
    ],
];
