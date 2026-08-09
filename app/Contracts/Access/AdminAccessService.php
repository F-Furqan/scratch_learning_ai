<?php

namespace App\Contracts\Access;

use App\Models\User;

interface AdminAccessService
{
    public function canAccess(User $user): bool;
}
