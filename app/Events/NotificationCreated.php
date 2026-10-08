<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $notification_id,
        public int $user_id,
        public ?int $type_id,
        public ?int $order_id,
        public ?int $payment_id,
        public ?int $shipment_id,
        public ?int $return_id,
        public ?string $title,
        public ?string $message,
        public ?string $action_url,
    ) {
    }

    /**
     * Public Reverb channel.
     */
    public function broadcastOn(): array
    {
        return [
            new \Illuminate\Broadcasting\Channel('user.' . $this->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'notification_id' => $this->notification_id,
            'user_id' => $this->user_id,
            'type_id' => $this->type_id,
            'order_id' => $this->order_id,
            'payment_id' => $this->payment_id,
            'shipment_id' => $this->shipment_id,
            'return_id' => $this->return_id,
            'title' => $this->title,
            'message' => $this->message,
            'action_url' => $this->action_url,
        ];
    }
}
