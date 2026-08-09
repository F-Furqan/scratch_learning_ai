<?php

namespace App\Enums;

enum CommunityReactionType: string
{
    case Upvote = 'upvote';
    case Like = 'like';
    case Helpful = 'helpful';
}
