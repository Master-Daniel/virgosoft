<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Order;
use App\Services\OrderMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    protected OrderMatchingService $matchingService;

    public function __construct(OrderMatchingService $matchingService)
    {
        $this->matchingService = $matchingService;
    }

    /**
     * Get all orders (optionally filtered by symbol).
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $query = Order::orderBy('created_at', 'desc');

        // For orderbook, show all open orders (all users)
        if ($request->has('orderbook') && $request->orderbook) {
            $query->where('status', Order::STATUS_OPEN);
            if ($request->has('symbol')) {
                $query->where('symbol', $request->symbol);
            }
        } else {
            // For "My Orders", only show current user's orders
            $query->where('user_id', $user->id);
        }

        if ($request->has('symbol') && !$request->has('orderbook')) {
            $query->where('symbol', $request->symbol);
        }

        $orders = $query->get()->map(function ($order) {
            return [
                'id' => $order->id,
                'user_id' => $order->user_id,
                'symbol' => $order->symbol,
                'side' => $order->side,
                'price' => (float) $order->price,
                'amount' => (float) $order->amount,
                'status' => (int) $order->status,
                'created_at' => $order->created_at->toISOString(),
            ];
        });

        return response()->json($orders);
    }

    /**
     * Create a new limit order.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'symbol' => 'required|string|in:BTC,ETH',
            'side' => 'required|string|in:buy,sell',
            'price' => 'required|numeric|min:0.01',
            'amount' => 'required|numeric|min:0.00000001',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first(),
            ], 422);
        }

        $user = Auth::user();

        try {
            return DB::transaction(function () use ($request, $user) {
                // Lock user for update
                $user = $user->lockForUpdate()->find($user->id);

                if ($request->side === 'buy') {
                    // Check if user has enough balance
                    $requiredBalance = $request->amount * $request->price;
                    if ($user->balance < $requiredBalance) {
                        return response()->json([
                            'error' => 'Insufficient balance',
                        ], 400);
                    }

                    // Deduct balance
                    $user->balance -= $requiredBalance;
                    $user->save();
                } else {
                    // Check if user has enough asset
                    // Try to find existing asset with lock
                    $asset = Asset::lockForUpdate()
                        ->where('user_id', $user->id)
                        ->where('symbol', $request->symbol)
                        ->first();

                    // If asset doesn't exist, create it (this should be rare)
                    if (!$asset) {
                        $asset = new Asset([
                            'user_id' => $user->id,
                            'symbol' => $request->symbol,
                            'amount' => 0,
                            'locked_amount' => 0,
                        ]);
                        $asset->save();
                        // Reload with lock for consistency
                        $asset = Asset::lockForUpdate()
                            ->where('user_id', $user->id)
                            ->where('symbol', $request->symbol)
                            ->first();
                    }

                    if ($asset->amount < $request->amount) {
                        return response()->json([
                            'error' => 'Insufficient asset balance. You have ' . number_format((float)$asset->amount, 8) . ' ' . $request->symbol . ' available.',
                        ], 400);
                    }

                    // Move amount to locked
                    $asset->amount -= $request->amount;
                    $asset->locked_amount += $request->amount;
                    $asset->save();
                }

                // Create order
                $order = Order::create([
                    'user_id' => $user->id,
                    'symbol' => $request->symbol,
                    'side' => $request->side,
                    'price' => $request->price,
                    'amount' => $request->amount,
                    'status' => Order::STATUS_OPEN,
                ]);

                // Broadcast order creation to all users (for orderbook updates)
                if ($order->status === Order::STATUS_OPEN) {
                    event(new \App\Events\OrderCreated($order));
                }

                // Try to match the order
                $trade = $this->matchingService->matchOrder($order);
                
                // Reload order to get updated status if it was matched
                $order->refresh();

                return response()->json([
                    'order' => [
                        'id' => $order->id,
                        'user_id' => $order->user_id,
                        'symbol' => $order->symbol,
                        'side' => $order->side,
                        'price' => $order->price,
                        'amount' => $order->amount,
                        'status' => $order->status,
                        'created_at' => $order->created_at,
                    ],
                    'matched' => $trade !== null,
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel an open order.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();

        try {
            return DB::transaction(function () use ($user, $id) {
                $order = Order::lockForUpdate()
                    ->where('id', $id)
                    ->where('user_id', $user->id)
                    ->first();

                if (!$order) {
                    return response()->json([
                        'error' => 'Order not found',
                    ], 404);
                }

                if ($order->status !== Order::STATUS_OPEN) {
                    return response()->json([
                        'error' => 'Order cannot be cancelled',
                    ], 400);
                }

                // Refund locked funds
                if ($order->side === 'buy') {
                    // Refund USD
                    $user = $user->lockForUpdate()->find($user->id);
                    $user->balance += ($order->amount * $order->price);
                    $user->save();
                } else {
                    // Release locked asset
                    $asset = Asset::lockForUpdate()
                        ->where('user_id', $user->id)
                        ->where('symbol', $order->symbol)
                        ->first();

                    if ($asset) {
                        $asset->locked_amount -= $order->amount;
                        $asset->amount += $order->amount;
                        $asset->save();
                    }
                }

                // Update order status
                $order->status = Order::STATUS_CANCELLED;
                $order->save();

                return response()->json([
                    'message' => 'Order cancelled successfully',
                    'order' => [
                        'id' => $order->id,
                        'status' => $order->status,
                    ],
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to cancel order: ' . $e->getMessage(),
            ], 500);
        }
    }
}
