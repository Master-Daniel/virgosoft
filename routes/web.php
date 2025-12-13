<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\HandleCors;

Route::get('/', function () {
    return view('welcome');
});

// Handle OPTIONS preflight for broadcasting/auth
Route::options('/broadcasting/auth', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, X-Socket-ID, X-CSRF-TOKEN')
        ->header('Access-Control-Max-Age', '86400')
        ->header('Access-Control-Allow-Credentials', 'true');
})->middleware([HandleCors::class]);

// IMPORTANT: Register custom POST route BEFORE Broadcast::routes() to take precedence
// Custom broadcast auth endpoint to handle form-urlencoded data properly
Route::post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
    // Log immediately to confirm route is hit
    \Illuminate\Support\Facades\Log::info('=== CUSTOM BROADCAST AUTH ROUTE HIT ===', [
        'method' => $request->method(),
        'uri' => $request->getRequestUri(),
        'headers' => $request->headers->all(),
    ]);
    
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
        \Illuminate\Support\Facades\Log::error('Custom broadcast auth: No user');
        return response()->json(['error' => 'Unauthenticated'], 401);
    }
    
    $socketId = $request->input('socket_id');
    $channelName = $request->input('channel_name');
    
    \Illuminate\Support\Facades\Log::info('Custom broadcast auth', [
        'socket_id' => $socketId,
        'channel_name' => $channelName,
        'user_id' => $user->id,
        'raw_content' => $request->getContent(),
    ]);
    
    if (!$socketId || !$channelName) {
        \Illuminate\Support\Facades\Log::error('Custom broadcast auth: Missing params', [
            'has_socket_id' => !empty($socketId),
            'has_channel_name' => !empty($channelName),
        ]);
        return response()->json(['error' => 'Missing socket_id or channel_name'], 400);
    }
    
    // Extract channel name without 'private-' prefix
    $channelNameWithoutPrefix = str_replace('private-', '', $channelName);
    
    // Check if channel matches user.{userId} pattern
    if (preg_match('/^user\.(\d+)$/', $channelNameWithoutPrefix, $matches)) {
        $userId = (int) $matches[1];
        
        \Illuminate\Support\Facades\Log::info('Channel authorization check', [
            'user_id' => $user->id,
            'requested_user_id' => $userId,
            'authorized' => $user->id === $userId,
        ]);
        
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
        
        \Illuminate\Support\Facades\Log::info('Broadcast auth success', [
            'auth_response' => json_encode($auth),
        ]);
        
        return response()->json($auth);
    }
    
    \Illuminate\Support\Facades\Log::error('Custom broadcast auth: Invalid channel', [
        'channel_name' => $channelName,
        'channel_without_prefix' => $channelNameWithoutPrefix,
    ]);
    
    return response()->json(['error' => 'Invalid channel'], 400);
})->middleware([HandleCors::class, 'auth:sanctum']);

// Don't call Broadcast::routes() - it conflicts with our custom POST route
// We're handling POST manually above, which is all we need for Pusher
