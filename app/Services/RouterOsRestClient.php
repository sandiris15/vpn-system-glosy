<?php

namespace App\Services;

use App\Models\Server;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class RouterOsRestClient
{
    private PendingRequest $request;

    private function __construct(PendingRequest $request)
    {
        $this->request = $request;
    }

    public static function forServer(Server $server): self
    {
        return self::forCredentials($server->host, $server->port, $server->username, $server->password);
    }

    public static function forTemporaryConnection(
        string $host,
        int $port,
        string $username,
        string $password,
        ?string $resolvedIp = null,
    ): self {
        return self::forCredentials($host, $port, $username, $password, $resolvedIp);
    }

    private static function forCredentials(
        string $host,
        int $port,
        string $username,
        string $password,
        ?string $resolvedIp = null,
    ): self {
        $host = trim($host, '[]');
        $urlHost = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '['.$host.']' : $host;
        $options = [
            'allow_redirects' => false,
            'verify' => config('managed.router_os_ca_bundle') ?: true,
        ];

        if ($resolvedIp !== null) {
            $options['resolve'] = ["{$host}:{$port}" => $resolvedIp];
        }

        $request = Http::baseUrl("https://{$urlHost}:{$port}/rest/")
            ->withBasicAuth($username, $password)
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(8)
            ->withOptions($options);

        return new self($request);
    }

    public function collection(string $resource): Collection
    {
        $response = $this->request->get($resource);
        $response->throw();

        $data = $response->json();

        if (! is_array($data) || ! array_is_list($data)) {
            throw new UnexpectedValueException('RouterOS REST returned an unexpected response.');
        }

        return collect($data);
    }

    /**
     * @param  array<string, bool|int|string>  $attributes
     * @return array<string, mixed>
     */
    public function create(string $resource, array $attributes): array
    {
        return $this->object($this->request->put($resource, $attributes));
    }

    /**
     * @param  array<string, bool|int|string>  $attributes
     */
    public function update(string $resource, string $id, array $attributes): void
    {
        $this->request->patch($resource.'/'.rawurlencode($id), $attributes)->throw();
    }

    public function delete(string $resource, string $id): void
    {
        $this->request->delete($resource.'/'.rawurlencode($id))->throw();
    }

    /**
     * @return array<string, mixed>
     */
    private function object(Response $response): array
    {
        $response->throw();
        $data = $response->json();

        if (! is_array($data) || array_is_list($data)) {
            throw new UnexpectedValueException('RouterOS REST returned an unexpected response.');
        }

        return $data;
    }
}
