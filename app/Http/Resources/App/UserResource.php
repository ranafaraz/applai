<?php

namespace App\Http\Resources\App;

use App\Models\User;

/**
 * Shapes a User for the mobile API. Maps the CRM's `name` column to the app's
 * `full_name` field and exposes onboarding/profile fields. Never leaks
 * password, tenant internals, or role badges.
 */
class UserResource
{
    public static function make(User $user): array
    {
        return [
            'id'             => $user->id,
            'full_name'      => $user->name,
            'email'          => $user->email,
            'avatar_url'     => $user->avatar_url,
            'tracking_types' => $user->tracking_types ?? [],
            'initials'       => $user->initials(),
            'email_verified' => $user->email_verified_at !== null,
            'created_at'     => $user->created_at?->toISOString(),
        ];
    }
}
