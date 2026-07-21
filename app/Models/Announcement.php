<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'body',
        'category',
        'priority',
        'audience_mode',
        'role_targets',
        'class_ids',
        'user_ids',
        'portal_enabled',
        'email_enabled',
        'is_published',
        'published_at',
        'starts_at',
        'expires_at',
        'dispatched_at',
        'status',
        'author_id',
    ];

    protected function casts(): array
    {
        return [
            'role_targets' => 'array',
            'class_ids' => 'array',
            'user_ids' => 'array',
            'portal_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'dispatched_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(AnnouncementDelivery::class);
    }

    public function scopeDueForDispatch(Builder $query): Builder
    {
        return $query
            ->where('status', 'scheduled')
            ->whereNull('dispatched_at')
            ->where(function (Builder $builder): void {
                $builder->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $builder): void {
                $builder->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }
}
