<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class DashboardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }
}
