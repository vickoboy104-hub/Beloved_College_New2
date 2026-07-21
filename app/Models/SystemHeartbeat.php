<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemHeartbeat extends Model
{
    use HasFactory;

    protected $fillable = [
        'service',
        'status',
        'last_seen_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
