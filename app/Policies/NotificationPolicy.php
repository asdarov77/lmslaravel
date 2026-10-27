<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

class NotificationPolicy
{
    /**
     * Пользователь видит только свои уведомления.
     */
    public function view(User $user, Notification $notification): bool
    {
        return $notification->user_id === $user->id;
    }
}
