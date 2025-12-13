<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

// Handle CORS preflight requests
Route::options('/{any}', function () {
    return response()->json([], 200);
})->where('any', '.*');

// Authentication routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Custom broadcast auth endpoint (moved here to avoid CSRF)
Route::post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
    // Parse form-encoded body manually
    $contentType = $request->header('Content-Type', '');
    if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
        parse_str($request->getContent(), $parsed);
        foreach ($parsed as $key => $value) {
            $request->request->set($key, $value);
        }
    }
    
    $user = \Illuminate\Support\Facades\Auth::user();
    
    if (!$user) {
        return response()->json(['error' => 'Unauthenticated'], 401);
    }
    
    $socketId = $request->input('socket_id');
    $channelName = $request->input('channel_name');
    
    if (!$socketId || !$channelName) {
        return response()->json(['error' => 'Missing socket_id or channel_name'], 400);
    }
    
    // Extract channel name without 'private-' prefix
    $channelNameWithoutPrefix = str_replace('private-', '', $channelName);
    
    // Check if channel matches user.{userId} pattern
    if (preg_match('/^user\.(\d+)$/', $channelNameWithoutPrefix, $matches)) {
        $userId = (int) $matches[1];
        
        if ($user->id !== $userId) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        
        // Generate auth signature for Pusher
        $pusher = new \Pusher\Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            config('broadcasting.connections.pusher.options')
        );
        
        $auth = $pusher->socket_auth($channelName, $socketId);
        
        // socket_auth() returns a JSON string, so we need to decode it first
        // then return it as proper JSON response
        $authData = json_decode($auth, true);
        
        return response()->json($authData, 200, [], JSON_UNESCAPED_SLASHES);
    }
    
    return response()->json(['error' => 'Invalid channel'], 400);
})->middleware([\App\Http\Middleware\HandleCors::class, 'auth:sanctum']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'index']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
});

