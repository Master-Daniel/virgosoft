<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

Broadcast::channel('user.{userId}', function ($user, $userId) {
    Log::info('Broadcast channel authorization', [
        'user_id' => $user->id,
        'requested_user_id' => $userId,
        'authorized' => (int) $user->id === (int) $userId
    ]);
    
    return (int) $user->id === (int) $userId;
});

