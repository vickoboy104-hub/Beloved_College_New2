<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class Setting extends Model
{
    public const CACHE_KEY = 'beloved.settings.all';

    public const SENSITIVE_KEYS = [
        'mail_password',
        'paystack_secret_key',
        'paystack_webhook_secret',
        'palmpay_private_key',
        'palmpay_webhook_secret',
        'flutterwave_secret_key',
        'flutterwave_encryption_key',
        'flutterwave_secret_hash',
        'flutterwave_client_secret',
        'monnify_secret_key',
    ];

    protected const ENCRYPTED_PREFIX = 'encrypted:';

    protected static ?array $resolvedSettings = null;

    protected $fillable = ['group', 'key', 'value', 'type'];

    public static function allCached(): array
    {
        if (static::$resolvedSettings !== null) {
            return static::$resolvedSettings;
        }

        return static::$resolvedSettings = Cache::rememberForever(
            static::CACHE_KEY,
            fn (): array => static::query()
                ->get(['key', 'value'])
                ->mapWithKeys(fn (Setting $setting) => [
                    $setting->key => static::decodeValue($setting->key, $setting->value),
                ])
                ->all(),
        );
    }

    public static function publicSettings(?array $settings = null): array
    {
        return collect($settings ?? static::allCached())
            ->except(static::SENSITIVE_KEYS)
            ->all();
    }

    public static function forAdminForm(?array $settings = null): array
    {
        $formSettings = $settings ?? static::allCached();

        foreach (static::SENSITIVE_KEYS as $key) {
            if (array_key_exists($key, $formSettings)) {
                $formSettings[$key] = '';
            }
        }

        return $formSettings;
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        return static::allCached()[$key] ?? $default;
    }

    public static function setMany(array $settings, string $group = 'school'): void
    {
        DB::transaction(function () use ($settings, $group): void {
            foreach ($settings as $key => $value) {
                $existing = static::query()->where('key', $key)->first();

                if (static::isSensitive($key) && blank($value) && $existing && filled($existing->value)) {
                    continue;
                }

                $stringValue = is_array($value) ? json_encode($value) : (string) $value;
                $storedValue = static::isSensitive($key) && $stringValue !== ''
                    ? static::encodeValue($stringValue)
                    : $stringValue;

                static::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'group' => $group,
                        'value' => $storedValue,
                        'type' => is_array($value)
                            ? 'json'
                            : (static::isSensitive($key) ? 'encrypted' : 'string'),
                    ],
                );
            }
        });

        static::flushCache();
    }

    public static function encryptExistingSensitiveValues(): void
    {
        static::query()
            ->whereIn('key', static::SENSITIVE_KEYS)
            ->get()
            ->each(function (Setting $setting): void {
                if (blank($setting->value)
                    || str_starts_with((string) $setting->value, static::ENCRYPTED_PREFIX)) {
                    return;
                }

                $setting->forceFill([
                    'value' => static::encodeValue((string) $setting->value),
                    'type' => 'encrypted',
                ])->saveQuietly();
            });

        static::flushCache();
    }

    public static function decryptExistingSensitiveValues(): void
    {
        static::query()
            ->whereIn('key', static::SENSITIVE_KEYS)
            ->get()
            ->each(function (Setting $setting): void {
                if (! str_starts_with((string) $setting->value, static::ENCRYPTED_PREFIX)) {
                    return;
                }

                $setting->forceFill([
                    'value' => static::decodeValue($setting->key, $setting->value),
                    'type' => 'string',
                ])->saveQuietly();
            });

        static::flushCache();
    }

    public static function isSensitive(string $key): bool
    {
        return in_array($key, static::SENSITIVE_KEYS, true);
    }

    protected static function encodeValue(string $value): string
    {
        return static::ENCRYPTED_PREFIX.Crypt::encryptString($value);
    }

    protected static function decodeValue(string $key, mixed $value): mixed
    {
        $stringValue = (string) $value;

        if (! static::isSensitive($key)
            || ! str_starts_with($stringValue, static::ENCRYPTED_PREFIX)) {
            return $value;
        }

        try {
            return Crypt::decryptString(substr($stringValue, strlen(static::ENCRYPTED_PREFIX)));
        } catch (DecryptException $exception) {
            report($exception);

            return null;
        }
    }

    public static function flushCache(): void
    {
        static::$resolvedSettings = null;
        Cache::forget(static::CACHE_KEY);
    }
}
