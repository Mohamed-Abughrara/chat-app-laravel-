<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:login');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'throttle:chat-api'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('me', [ProfileController::class, 'show']);
        Route::patch('me', [ProfileController::class, 'update']);
        Route::get('friends', [ProfileController::class, 'friends']);
        Route::post('friends', [ProfileController::class, 'addFriend']);
        Route::post('devices/push-token', [ProfileController::class, 'pushToken']);

        Route::get('conversations', [ChatController::class, 'conversations']);
        Route::get('conversations/{id}/messages', [ChatController::class, 'history'])->whereNumber('id');
        Route::get('groups/{id}/messages', [ChatController::class, 'groupHistory'])->whereNumber('id');
        Route::post('groups', [ChatController::class, 'createGroup'])->middleware('throttle:group-create');
        Route::post('groups/{id}/members', [ChatController::class, 'addMembers'])->whereNumber('id');
        Route::delete('groups/{id}/members', [ChatController::class, 'removeMembers'])->whereNumber('id');

        Route::get('sync', [ChatController::class, 'sync']);

        // Real-time actions. Reverb client events are only relayed to other clients (never run on the
        // server), so the client's message.send / presence.heartbeat / message.delivered use these
        // authenticated endpoints, which run the same use cases. Server -> client events stay on WebSocket.
        Route::post('messages', [ChatController::class, 'send'])->middleware('throttle:messages');
        Route::post('messages/{messageId}/delivered', [ChatController::class, 'delivered'])->whereNumber('messageId');
        Route::post('presence/heartbeat', [ChatController::class, 'heartbeat']);
    });
});
