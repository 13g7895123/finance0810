<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Chat channels for specific LINE users
Broadcast::channel('chat.{lineUserId}', function ($user, $lineUserId) {
    // Allow access if user is admin/manager or if they are assigned to this customer
    if ($user->hasRole(['admin', 'executive', 'manager'])) {
        return true;
    }
    
    // For staff users, check if they are assigned to the customer with this LINE user ID
    if ($user->hasRole('staff')) {
        $customer = \App\Models\Customer::where('line_user_id', $lineUserId)->first();
        return $customer && $customer->assigned_to === $user->id;
    }
    
    return false;
});

// Admin channel for all chat notifications
Broadcast::channel('chat.admin', function ($user) {
    // Only admin, executive, and manager roles can access admin channel
    return $user->hasRole(['admin', 'executive', 'manager']);
});

// General presence channel for online users
Broadcast::channel('online', function ($user) {
    return [
        'id' => $user->id,
        'name' => $user->name,
        'role' => $user->roles->first()?->name ?? 'user'
    ];
});