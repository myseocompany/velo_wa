<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status'          => session('status'),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /** POST /profile/avatar */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $user = $request->user();

        if ($user->avatar_url) {
            $old = parse_url($user->avatar_url, PHP_URL_PATH);
            Storage::disk('s3')->delete(ltrim((string) $old, '/'));
        }

        $path = $request->file('avatar')->store(
            "tenants/{$user->tenant_id}/avatars",
            's3'
        );

        $url = Storage::disk('s3')->url($path);
        $user->update(['avatar_url' => $url]);

        return response()->json(['avatar_url' => $url]);
    }

    /** PATCH /profile/notifications */
    public function updateNotifications(Request $request): JsonResponse
    {
        $request->validate([
            'notification_preferences'                   => ['required', 'array'],
            'notification_preferences.new_message'       => ['boolean'],
            'notification_preferences.new_conversation'  => ['boolean'],
            'notification_preferences.assignment'        => ['boolean'],
            'notification_preferences.deal_stage_change' => ['boolean'],
            'notification_preferences.sound_enabled'     => ['boolean'],
        ]);

        $request->user()->update([
            'notification_preferences' => $request->notification_preferences,
        ]);

        return response()->json(['message' => 'Preferencias actualizadas.']);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
