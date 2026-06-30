<?php

namespace App\Events;

use App\Models\TinNhan;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;


class Message implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $tinNhan;
    public $idPhongChat;

    /**
     * Create a new event instance.
     */
    public function __construct(TinNhan $tinNhan)
    {
        $this->tinNhan = $tinNhan;
        $this->idPhongChat = $tinNhan->ID_PhongChat;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('phong-chat.' . $this->idPhongChat),
        ];
    }
    public function broadcastAs(): string
    {
        return 'tin-nhan.moi';
    }
    public function broadcastWith(): array
    {
        return [
            'ID_TinNhan'   => $this->tinNhan->ID_TinNhan,
            'ID_PhongChat' => $this->tinNhan->ID_PhongChat,
            'LoaiNguoiGui' => $this->tinNhan->LoaiNguoiGui,
            'ID_NguoiGui'  => $this->tinNhan->ID_NguoiGui,
            'NoiDung'      => $this->tinNhan->NoiDung,
            'ThoiGianGui'  => $this->tinNhan->ThoiGianGui,
        ];
    }
}
