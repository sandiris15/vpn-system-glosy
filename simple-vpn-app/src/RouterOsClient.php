<?php

namespace SimpleVpn;

use RuntimeException;

final class RouterOsClient implements RouterOsApi
{
    public function __construct(private readonly Config $config) {}

    public function collection(string $resource): array
    {
        $data = $this->request('GET', $resource);

        if (! is_array($data) || ! array_is_list($data)) {
            throw new RuntimeException('RouterOS returned an unexpected collection response.');
        }

        return $data;
    }

    public function create(string $resource, array $attributes): array
    {
        $data = $this->request('PUT', $resource, $attributes);

        if (! is_array($data) || array_is_list($data)) {
            throw new RuntimeException('RouterOS did not confirm the new configuration.');
        }

        return $data;
    }

    public function delete(string $resource, string $id): void
    {
        $this->request('DELETE', $resource.'/'.rawurlencode($id));
    }

    /**
     * @param  array<string, bool|int|string>|null  $payload
     */
    private function request(string $method, string $resource, ?array $payload = null): mixed
    {
        $host = trim($this->config->routerHost, '[]');
        $urlHost = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '['.$host.']' : $host;
        $url = 'https://'.$urlHost.':'.$this->config->routerPort.'/rest/'.$resource;
        $handle = curl_init($url);

        if ($handle === false) {
            throw new RuntimeException('Could not initialize the RouterOS HTTPS request.');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->config->routerUsername.':'.$this->config->routerPassword,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if ($this->config->routerCaBundle !== null) {
            $caBundle = str_starts_with($this->config->routerCaBundle, DIRECTORY_SEPARATOR)
                ? $this->config->routerCaBundle
                : dirname(__DIR__).'/'.$this->config->routerCaBundle;

            if (! is_file($caBundle) || ! is_readable($caBundle)) {
                throw new RuntimeException('The configured RouterOS CA bundle is missing or unreadable.');
            }

            $options[CURLOPT_CAINFO] = $caBundle;
        }

        if ($payload !== null) {
            try {
                $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new RuntimeException('The RouterOS request contains invalid text.', previous: $exception);
            }
        }

        curl_setopt_array($handle, $options);
        $responseBody = curl_exec($handle);
        $curlError = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        if ($responseBody === false) {
            throw new RuntimeException('RouterOS HTTPS connection failed: '.$curlError);
        }

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("RouterOS rejected the request (HTTP {$status}).");
        }

        if ($responseBody === '') {
            return null;
        }

        try {
            return json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('RouterOS returned invalid JSON.', previous: $exception);
        }
    }
}
