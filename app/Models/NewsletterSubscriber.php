<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'name',
        'consent',
        'consent_version',
        'consented_at',
        'unsubscribed_at',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'consent' => 'boolean',
            'consented_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }
}
