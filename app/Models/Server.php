<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'priority',
        'host',
        'port',
        'username',
        'password',
        'ppp_profile',
        'ip_pool',
        'public_ip',
        'port_range_start',
        'port_range_end',
        'is_active',
        'status',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'is_active' => 'boolean',
            'priority' => 'integer',
            'port_range_start' => 'integer',
            'port_range_end' => 'integer',
            'last_checked_at' => 'datetime',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function managedVpnAccounts(): HasMany
    {
        return $this->hasMany(ManagedVpnAccount::class);
    }
}
