<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;

    /**
     * Create a new event instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        // Broadcast to a public channel so all users can see new orders
        return [
            new Channel('orderbook.' . $this->order->symbol),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order.created';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'order' => [
                'id' => $this->order->id,
                'user_id' => $this->order->user_id,
                'symbol' => $this->order->symbol,
                'side' => $this->order->side,
                'price' => (float) $this->order->price,
                'amount' => (float) $this->order->amount,
                'status' => (int) $this->order->status,
                'created_at' => $this->order->created_at->toISOString(),
            ],
        ];
    }
}

