<?php

namespace SimpleVpn;

interface RouterOsApi
{
    /**
     * @return list<array<string, mixed>>
     */
    public function collection(string $resource): array;

    /**
     * @param  array<string, bool|int|string>  $attributes
     * @return array<string, mixed>
     */
    public function create(string $resource, array $attributes): array;

    public function delete(string $resource, string $id): void;
}
