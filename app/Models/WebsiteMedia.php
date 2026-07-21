<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteMedia extends Model
{
    use HasFactory;

    protected $table = 'website_media';

    protected $fillable = [
        'collection',
        'title',
        'alt_text',
        'caption',
        'media_type',
        'path',
        'external_url',
        'sort_order',
        'is_published',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publicUrl(): ?string
    {
        if ($this->external_url) {
            return $this->external_url;
        }

        return $this->path ? route('public.media.show', $this) : null;
    }
}
