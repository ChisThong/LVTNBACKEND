<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow; // Ép chạy trực tiếp
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SellerActivityEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $activity;
    public $idShop;

    /**
     * Khởi tạo sự kiện kèm theo dữ liệu thông báo và ID của Shop nhận tin
     */
    public function __construct($activity, $idShop)
    {
        $this->activity = $activity;
        $this->idShop = $idShop; // Dùng để định danh kênh gửi đích xác cho Shop này
    }

    /**
     * Phát sự kiện lên kênh định danh riêng của Shop
     */
    public function broadcastOn(): array
    {
        return [
            // Tạo kênh có cấu trúc: seller-shop-channel.ID_SHOP
            new Channel('seller-shop-channel.' . $this->idShop)
        ];
    }

    /**
     * Tên sự kiện phía Frontend lắng nghe
     */
    public function broadcastAs(): string
    {
        return 'seller-new-activity';
    }
}