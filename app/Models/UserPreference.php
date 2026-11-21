<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    protected $fillable = [
        'user_id',
        'wallpaper_id',
        'wallpaper_upload_path',
        'blur',
        'overlay',
    ];

    protected function casts(): array
    {
        return [
            'blur' => 'integer',
            'overlay' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
