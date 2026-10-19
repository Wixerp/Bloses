<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SellerStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $seller_id,
        public int $user_id,
        public ?int $old_status_id,
        public ?int $status_id
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('seller.' . $this->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'seller.status.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'seller_id' => $this->seller_id,
            'user_id' => $this->user_id,
            'old_status_id' => $this->old_status_id,
            'status_id' => $this->status_id,
            'is_approved' => $this->status_id === 36,
        ];
    }
}
