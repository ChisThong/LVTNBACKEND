<?php

namespace App\Events;

use App\Models\TinNhan;
use App\Models\Shop;
use App\Models\PhongChat;
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
        $channels = [
            new PrivateChannel('phong-chat.' . $this->idPhongChat),
        ];

        // Tìm người nhận để phát tin nhắn đến kênh riêng của họ (App.Models.User.{id})
        try {
            $room = PhongChat::find($this->idPhongChat);
            if ($room) {
                $recipientId = null;
                if ($this->tinNhan->LoaiNguoiGui === 'user') {
                    // Người gửi là Buyer -> Người nhận là Seller
                    $shop = Shop::find($room->ID_Shop);
                    if ($shop) {
                        $recipientId = $shop->ID_User;
                    }
                } else {
                    // Người gửi là Shop -> Người nhận là Buyer
                    $recipientId = $room->ID_User;
                }

                if ($recipientId) {
                    $channels[] = new PrivateChannel('App.Models.User.' . $recipientId);
                }
            }
        } catch (\Exception $e) {
            // Tránh lỗi ngắt quãng tiến trình lưu
        }

        return $channels;
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
