<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    /**
     * Get authenticated user's profile with balance and assets.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            
            // Safely get assets
            $assets = [];
            try {
                $assetsCollection = $user->assets()->get();
                $assets = $assetsCollection->map(function ($asset) {
                    return [
                        'symbol' => $asset->symbol,
                        'amount' => (float) $asset->amount,
                        'locked_amount' => (float) $asset->locked_amount,
                    ];
                })->toArray();
            } catch (\Exception $e) {
                $assets = [];
            }

            // Safely handle balance - ensure it's a number
            $balance = 0.0;
            if ($user->balance !== null && $user->balance !== '') {
                $balance = (float) $user->balance;
            }

            $response = [
                'user_id' => $user->id,
                'balance' => $balance,
                'assets' => $assets,
            ];

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
