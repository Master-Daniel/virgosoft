<?php

namespace App\Services;

use App\Events\OrderMatched;
use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderMatchingService
{
    const COMMISSION_RATE = 0.015; // 1.5%

    /**
     * Match a new order with existing orders.
     */
    public function matchOrder(Order $newOrder): ?Trade
    {
        return DB::transaction(function () use ($newOrder) {
            // Lock the order for update
            $newOrder = Order::lockForUpdate()->find($newOrder->id);
            
            if ($newOrder->status !== Order::STATUS_OPEN) {
                return null;
            }

            $counterOrder = $this->findMatchingOrder($newOrder);

            if (!$counterOrder) {
                return null;
            }

            // Lock the counter order
            $counterOrder = Order::lockForUpdate()->find($counterOrder->id);
            
            if ($counterOrder->status !== Order::STATUS_OPEN) {
                return null;
            }

            return $this->executeMatch($newOrder, $counterOrder);
        });
    }

    /**
     * Find a matching order for the given order.
     */
    protected function findMatchingOrder(Order $order): ?Order
    {
        if ($order->side === 'buy') {
            // Find first SELL where sell.price <= buy.price
            return Order::open()
                ->forSymbol($order->symbol)
                ->where('side', 'sell')
                ->where('price', '<=', $order->price)
                ->where('user_id', '!=', $order->user_id)
                ->orderBy('price', 'asc')
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->first();
        } else {
            // Find first BUY where buy.price >= sell.price
            return Order::open()
                ->forSymbol($order->symbol)
                ->where('side', 'buy')
                ->where('price', '>=', $order->price)
                ->where('user_id', '!=', $order->user_id)
                ->orderBy('price', 'desc')
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->first();
        }
    }

    /**
     * Execute the match between two orders.
     */
    protected function executeMatch(Order $order1, Order $order2): Trade
    {
        // Determine buy and sell orders
        $buyOrder = $order1->side === 'buy' ? $order1 : $order2;
        $sellOrder = $order1->side === 'sell' ? $order1 : $order2;

        // Use the sell order price (market price)
        $matchPrice = $sellOrder->price;
        $amount = min($buyOrder->amount, $sellOrder->amount);

        // Calculate commission (1.5% of matched USD value)
        $usdValue = $amount * $matchPrice;
        $commission = $usdValue * self::COMMISSION_RATE;

        // Update orders status
        $buyOrder->status = Order::STATUS_FILLED;
        $buyOrder->save();

        $sellOrder->status = Order::STATUS_FILLED;
        $sellOrder->save();

        // Update buyer's balance and assets
        $buyer = User::lockForUpdate()->find($buyOrder->user_id);
        // Balance was already deducted when order was created, so only deduct commission
        $buyer->balance -= $commission;
        $buyer->save();

        // Add asset to buyer
        $buyerAsset = Asset::firstOrCreate(
            ['user_id' => $buyer->id, 'symbol' => $buyOrder->symbol],
            ['amount' => 0, 'locked_amount' => 0]
        );
        $buyerAsset->amount += $amount;
        $buyerAsset->save();

        // Update seller's assets and balance
        $seller = User::lockForUpdate()->find($sellOrder->user_id);
        $sellerAsset = Asset::lockForUpdate()
            ->where('user_id', $seller->id)
            ->where('symbol', $sellOrder->symbol)
            ->first();
        
        // Release locked amount (amount was already deducted from asset->amount when order was created)
        // Only need to reduce locked_amount, not amount again
        $sellerAsset->locked_amount -= $amount;
        $sellerAsset->save();

        // Add USD to seller (minus commission)
        $seller->balance += ($usdValue - $commission);
        $seller->save();

        // Create trade record
        $trade = Trade::create([
            'buy_order_id' => $buyOrder->id,
            'sell_order_id' => $sellOrder->id,
            'symbol' => $buyOrder->symbol,
            'price' => $matchPrice,
            'amount' => $amount,
            'commission' => $commission,
        ]);

        // Broadcast event to both users
        try {
            event(new OrderMatched($trade, $buyer->id, $seller->id));
        } catch (\Exception $e) {
            // Silently handle broadcast errors
        }

        // Broadcast order updates to orderbook so filled orders are removed
        try {
            event(new \App\Events\OrderUpdated($buyOrder));
            event(new \App\Events\OrderUpdated($sellOrder));
        } catch (\Exception $e) {
            // Silently handle broadcast errors
        }

        return $trade;
    }
}

