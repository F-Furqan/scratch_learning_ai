<?php

namespace App\Contracts\Navigation;

use App\Models\User;

interface DashboardDestinationResolver
{
    public function pathFor(User $user): string;
}
