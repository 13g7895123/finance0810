<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\ChatConversation;

class NewChatMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $lineUserId;

    public function __construct(ChatConversation $message, $lineUserId)
    {
        $this->message = $message;
        $this->lineUserId = $lineUserId;
    }

    public function broadcastOn()
    {
        return [
            new PrivateChannel("chat.{$this->lineUserId}"),
            new PrivateChannel('chat.admin')
        ];
    }

    public function broadcastAs()
    {
        return 'new-message';
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->message->id,
            'content' => $this->message->message_content,
            'timestamp' => $this->message->message_timestamp->toISOString(),
            'is_from_customer' => $this->message->is_from_customer,
            'status' => $this->message->status,
            'message_type' => $this->message->message_type,
            'line_user_id' => $this->lineUserId,
            'customer' => $this->message->customer ? [
                'id' => $this->message->customer->id,
                'name' => $this->message->customer->name,
                'phone' => $this->message->customer->phone
            ] : null
        ];
    }
}