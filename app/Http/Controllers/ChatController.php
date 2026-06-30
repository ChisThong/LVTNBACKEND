<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\PhongChat;
use App\Models\TinNhan;
use App\Events\Message;

class ChatController extends Controller
{
    public function vaoPhongChat(Request $request)
    {
        $request->validate([
            'ID_Shop' => 'required|exists:shop,ID_Shop'
        ]);

        $idUser = Auth::id(); // Demo mặc định ID = 5 nếu chưa làm đăng nhập xong
        $idShop = $request->ID_Shop;

        // Tìm phòng chat giữa Người Mua này và Cửa Hàng này, nếu chưa có thì tự động tạo mới
        $phongChat = PhongChat::firstOrCreate(
            [
                'ID_User' => $idUser,
                'ID_Shop' => $idShop
            ],
            [
                'ThoiGianTao' => now(),
                'ThoiGianCapNhat' => now()
            ]
        );

        return response()->json([
            'tin_nhan' => 'Vào phòng chat thành công',
            'du_lieu'  => $phongChat
        ]);
    }

    /**
     * 2. HÀM GỬI TIN NHẮN REAL-TIME
     */
    public function guiTinNhan(Request $request)
    {
        $request->validate([
            'ID_PhongChat' => 'required|exists:phongchat,ID_PhongChat',
            'NoiDung'      => 'required|string',
            'LoaiNguoiGui' => 'required|in:user,shop' // Để phân biệt ai đang chat
        ]);

        $idUserHienTai = Auth::id() ?? 5; // Mặc định ID_User hiện tại đang thao tác

        // 1. Lưu tin nhắn mới vào database bảng tinnhanchat
        $tinNhan = TinNhan::create([
            'ID_PhongChat' => $request->ID_PhongChat,
            'LoaiNguoiGui' => $request->LoaiNguoiGui,
            'ID_NguoiGui'  => $idUserHienTai,
            'NoiDung'      => $request->NoiDung,
            'DaDoc'        => 0,
            'ThoiGianGui'  => now()
        ]);

        // 2. Cập nhật nội dung tin nhắn cuối cùng và thời gian tương tác vào bảng phongchat
        $phongChat = PhongChat::find($request->ID_PhongChat);
        $phongChat->update([
            'TinNhanCuoi'     => $request->NoiDung,
            'ThoiGianCapNhat' => now()
        ]);

        // 3. Kích hoạt phát sóng sự kiện Real-time
        broadcast(new Message($tinNhan))->toOthers();

        return response()->json([
            'trang_thai' => 'Thành công',
            'du_lieu'    => $tinNhan
        ]);
    }

    /**
     * 3. HÀM LẤY LỊCH SỬ TIN NHẮN CŨ CỦA PHÒNG CHAT
     */
    public function layTinNhan($idPhongChat)
    {
        // Lấy 50 tin nhắn cũ nhất của phòng chat này sắp xếp theo thời gian
        $danhSachTinNhan = TinNhan::where('ID_PhongChat', $idPhongChat)
                            ->orderBy('ThoiGianGui', 'asc')
                            ->take(50)
                            ->get();

        return response()->json($danhSachTinNhan);
    }
}
