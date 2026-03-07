<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;

// Private channel per user
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return $user->id === $id;
});

// Private channel per tenant (for real-time inbox updates)
Broadcast::channel('tenant.{tenantId}', function ($user, $tenantId) {
    return $user->tenant_id === $tenantId;
});

// Private channel per conversation (for message updates)
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    // User must belong to the same tenant as the conversation
    return \App\Models\Conversation::withoutGlobalScope('tenant')
        ->where('id', $conversationId)
        ->where('tenant_id', $user->tenant_id)
        ->exists();
});
