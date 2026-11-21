<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class AuditLogger
{
    /**
     * @param  array<string, bool|int|string|null>  $metadata
     */
    public function record(
        User $user,
        Server $server,
        string $event,
        string $subject,
        array $metadata = [],
        ?string $failureMessage = null,
    ): void
    {
        try {
            AuditLog::query()->create([
                'user_id' => $user->getKey(),
                'server_id' => $server->getKey(),
                'event' => $event,
                'subject' => $subject,
                'metadata' => $metadata,
            ]);
        } catch (QueryException $exception) {
            Log::critical('Failed to write a managed operation audit event.', [
                'user_id' => $user->getKey(),
                'server_id' => $server->getKey(),
                'event' => $event,
                'exception' => $exception::class,
            ]);

            throw new AuditLoggingException(
                $failureMessage ?? 'RouterOS changed, but the audit event could not be saved. Verify the router before retrying.',
                previous: $exception,
            );
        }
    }
}
