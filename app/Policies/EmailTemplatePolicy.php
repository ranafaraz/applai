<?php

namespace App\Policies;

use App\Models\EmailTemplate;
use App\Models\User;

class EmailTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, EmailTemplate $emailTemplate): bool
    {
        return $user->id === $emailTemplate->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, EmailTemplate $emailTemplate): bool
    {
        return $user->id === $emailTemplate->user_id;
    }

    public function delete(User $user, EmailTemplate $emailTemplate): bool
    {
        return $user->id === $emailTemplate->user_id;
    }
}
