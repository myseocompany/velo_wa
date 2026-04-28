<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): User
    {
        $users = $this->matchingUsers();

        if ($users->count() !== 1) {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        return $users->first();
    }

    /**
     * Return active tenant-scoped users whose password matches the submitted credentials.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\User>
     */
    public function matchingUsers(): Collection
    {
        $this->ensureIsNotRateLimited();

        $email = Str::lower((string) $this->string('email'));

        $users = User::query()
            ->with('tenant:id,name,slug')
            ->whereRaw('lower(email) = ?', [$email])
            ->whereNotNull('tenant_id')
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user): bool => Hash::check((string) $this->string('password'), $user->password))
            ->values();

        if ($users->isEmpty()) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        return $users;
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
