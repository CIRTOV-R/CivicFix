<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Crew;
use App\Models\User;

final class CrewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Crew $crew): bool
    {
        return $user->canManage() || $user->crew_id === $crew->id;
    }

    public function updateLocation(User $user, Crew $crew): bool
    {
        return $user->canManage() || ($user->hasRole('CUADRILLA') && $user->crew_id === $crew->id);
    }
}
