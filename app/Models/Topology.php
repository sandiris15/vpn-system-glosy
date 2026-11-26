<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Topology extends Model
{
    protected $fillable = ['user_id', 'name', 'password_hash', 'design'];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return ['design' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
