<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagedVpnAccount extends Model
{
    protected $fillable = [
        'server_id',
        'routeros_id',
        'username',
        'remote_address',
        'nat_rule_ids',
    ];

    protected function casts(): array
    {
        return [
            'nat_rule_ids' => 'array',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
