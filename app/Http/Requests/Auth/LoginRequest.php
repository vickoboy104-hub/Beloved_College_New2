<?php

namespace App\Http\Requests\Auth;

use App\Enums\LoginAudience;
use App\Services\Identity\LoginIdentifierResolver;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'audience' => ['required', Rule::enum(LoginAudience::class)],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('email') && ! $this->filled('login')) {
            $this->merge(['login' => $this->input('email')]);
        }

        $this->merge([
            'audience' => $this->input('audience', LoginAudience::Generic->value),
        ]);
    }

    public function authenticate(LoginIdentifierResolver $resolver): void
    {
        $this->ensureIsNotRateLimited();

        $audience = LoginAudience::from($this->string('audience')->toString());
        $user = $resolver->resolve($this->string('login')->toString(), $audience);
        $password = $this->string('password')->toString();

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $password])->save();
        }

        Auth::login($user, $this->boolean('remember'));
        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower($this->string('login')->toString())
            .'|'.$this->ip()
            .'|'.$this->string('audience')->toString(),
        );
    }
}
