<?php

namespace App\Enums;

enum PublishStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Published = 'published';
    case ChangesRequested = 'changes_requested';
    case Rejected = 'rejected';
    case DeleteRequested = 'delete_requested';
    case Trashed = 'trashed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending',
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::Published => 'Published',
            self::ChangesRequested => 'Changes Requested',
            self::Rejected => 'Rejected',
            self::DeleteRequested => 'Delete Requested',
            self::Trashed => 'Trashed',
            self::Archived => 'Archived',
        };
    }

    public function isPublic(): bool
    {
        return $this === self::Published;
    }
}
