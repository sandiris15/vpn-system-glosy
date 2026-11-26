<?php

namespace App\Services;

use InvalidArgumentException;

class RouterOsScriptLibraryGenerator
{
    public function clearPppSecretAddresses(): string
    {
        return implode("\n", [
            '# Clear local-address and remote-address from every PPP secret.',
            '/ppp secret set [find] !local-address !remote-address',
            '',
        ]);
    }

    public function backupPppSecrets(string $fileName): string
    {
        if (preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $fileName) !== 1) {
            throw new InvalidArgumentException('The backup file name may only contain letters, numbers, underscores, and hyphens.');
        }

        return implode("\n", [
            '# Exports all PPP secrets, including sensitive values, to a RouterOS file.',
            '/ppp secret export file='.$fileName.' show-sensitive',
            '',
        ]);
    }
}
