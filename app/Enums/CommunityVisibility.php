<?php

namespace App\Enums;

enum CommunityVisibility: string
{
    case Public = 'public';
    case Members = 'members';
    case PaidMembers = 'paid_members';
}
