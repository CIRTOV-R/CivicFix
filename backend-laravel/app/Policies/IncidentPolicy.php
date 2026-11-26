<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Incident;
use App\Models\User;

final class IncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Incident $incident): bool
    {
        if ($user->canManage()) {
            return true;
        }

        if ($user->hasRole(RoleName::CIUDADANO)) {
            return $incident->citizen_id === $user->id;
        }

        return $incident->crew_id !== null && $incident->crew_id === $user->crew_id;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function assign(User $user, Incident $incident): bool
    {
        return $user->canManage();
    }

    public function start(User $user, Incident $incident): bool
    {
        if ($user->canManage()) {
            return true;
        }

        return $incident->crew_id !== null && $incident->crew_id === $user->crew_id;
    }

    public function resolve(User $user, Incident $incident): bool
    {
        return $this->start($user, $incident);
    }

    public function reject(User $user, Incident $incident): bool
    {
        return $user->canManage();
    }
}
