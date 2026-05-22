<?php

namespace App\Policies;

use App\Models\EmailMessage;
use App\Models\User;

class EmailMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, EmailMessage $emailMessage): bool
    {
        return $user->id === $emailMessage->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, EmailMessage $emailMessage): bool
    {
        return $user->id === $emailMessage->user_id;
    }

    public function delete(User $user, EmailMessage $emailMessage): bool
    {
        return $user->id === $emailMessage->user_id;
    }
}
