<?php

namespace App\Policies;

use App\Models\Opportunity;
use App\Models\User;

class OpportunityPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Opportunity $opportunity): bool
    {
        return $user->id === $opportunity->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Opportunity $opportunity): bool
    {
        return $user->id === $opportunity->user_id;
    }

    public function delete(User $user, Opportunity $opportunity): bool
    {
        return $user->id === $opportunity->user_id;
    }
}
