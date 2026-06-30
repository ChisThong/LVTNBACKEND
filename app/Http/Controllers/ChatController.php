<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\PhongChat;
use App\Models\TinNhan;
use App\Events\Message;
use App\Models\Shop;

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
        $idUser = Auth::id();
        $myShop = Shop::where('ID_User', $idUser)->first();

        // Đánh dấu tất cả tin nhắn gửi từ bên kia là đã đọc
        $room = PhongChat::find($idPhongChat);
        if ($room) {
            if ((int)$room->ID_User === (int)$idUser) {
                // Mình là Buyer, đánh dấu các tin từ Shop là đã đọc
                TinNhan::where('ID_PhongChat', $idPhongChat)
                    ->where('LoaiNguoiGui', 'shop')
                    ->where('DaDoc', 0)
                    ->update(['DaDoc' => 1]);
            } elseif ($myShop && (int)$room->ID_Shop === (int)$myShop->ID_Shop) {
                // Mình là Seller, đánh dấu các tin từ Buyer là đã đọc
                TinNhan::where('ID_PhongChat', $idPhongChat)
                    ->where('LoaiNguoiGui', 'user')
                    ->where('DaDoc', 0)
                    ->update(['DaDoc' => 1]);
            }
        }

        // Lấy 50 tin nhắn cũ nhất của phòng chat này sắp xếp theo thời gian
        $danhSachTinNhan = TinNhan::where('ID_PhongChat', $idPhongChat)
                            ->orderBy('ThoiGianGui', 'asc')
                            ->take(50)
                            ->get();

        return response()->json($danhSachTinNhan);
    }

    /**
     * 3.1 HÀM LẤY TỔNG SỐ TIN NHẮN CHƯA ĐỌC CỦA USER/SHOP
     */
    public function soTinChuaDoc(Request $request)
    {
        $idUser = Auth::id();
        $myShop = Shop::where('ID_User', $idUser)->first();

        // 1. Số tin chưa đọc khi đóng vai trò người mua (nhận từ shop)
        $userUnread = TinNhan::join('phongchat', 'tinnhanchat.ID_PhongChat', '=', 'phongchat.ID_PhongChat')
            ->where('phongchat.ID_User', $idUser)
            ->where('tinnhanchat.LoaiNguoiGui', 'shop')
            ->where('tinnhanchat.DaDoc', 0)
            ->count();

        // 2. Số tin chưa đọc khi đóng vai trò shop (nhận từ khách)
        $shopUnread = 0;
        if ($myShop) {
            $shopUnread = TinNhan::join('phongchat', 'tinnhanchat.ID_PhongChat', '=', 'phongchat.ID_PhongChat')
                ->where('phongchat.ID_Shop', $myShop->ID_Shop)
                ->where('tinnhanchat.LoaiNguoiGui', 'user')
                ->where('tinnhanchat.DaDoc', 0)
                ->count();
        }

        return response()->json([
            'success' => true,
            'tong_chua_doc' => $userUnread + $shopUnread
        ]);
    }

    /**
     * 4. HÀM LẤY DANH SÁCH PHÒNG CHAT CỦA USER HOẶC SHOP
     */
    public function layDanhSachPhongChat(Request $request)
    {
        $idUser = Auth::id();
        $isSellerRoute = $request->query('role') === 'seller';

        // 1. Nếu gọi từ trang Quản lý Shop (chỉ hiển thị khách hàng chat đến)
        if ($isSellerRoute) {
            $shop = Shop::where('ID_User', $idUser)->first();
            if (!$shop) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có gian hàng.'
                ], 403);
            }

            // Lấy các phòng chat của shop này, kèm thông tin của Buyer (người dùng)
            $danhSach = PhongChat::where('phongchat.ID_Shop', $shop->ID_Shop)
                ->leftJoin('user', 'phongchat.ID_User', '=', 'user.ID_User')
                ->select('phongchat.*', 'user.HoTen as ten_doi_tac', 'user.email as email_doi_tac')
                ->selectRaw("'customer' as vai_tro")
                ->orderBy('phongchat.ThoiGianCapNhat', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data'    => $danhSach
            ]);
        }

        // 2. Nếu gọi từ trang chủ / Navbar (hiển thị cả 2: các Shop mình đi mua, và Khách hàng nhắn cho Shop mình nếu có)
        // Lấy các phòng chat mình đi mua (vai trò là Khách hàng)
        $chatsAsBuyer = PhongChat::where('phongchat.ID_User', $idUser)
            ->leftJoin('shop', 'phongchat.ID_Shop', '=', 'shop.ID_Shop')
            ->select('phongchat.*', 'shop.TenShop as ten_doi_tac', 'shop.logo as logo_doi_tac')
            ->selectRaw("'shop' as vai_tro")
            ->get();

        // Lấy các phòng chat khách hàng nhắn đến Shop của mình (nếu mình là chủ Shop)
        $myShop = Shop::where('ID_User', $idUser)->first();
        $chatsAsSeller = collect();
        if ($myShop) {
            $chatsAsSeller = PhongChat::where('phongchat.ID_Shop', $myShop->ID_Shop)
                ->leftJoin('user', 'phongchat.ID_User', '=', 'user.ID_User')
                ->select('phongchat.*', 'user.HoTen as ten_doi_tac')
                ->selectRaw("'customer' as vai_tro")
                ->get();
        }

        // Gộp hai danh sách và sắp xếp theo thời gian cập nhật mới nhất
        $danhSach = $chatsAsBuyer->merge($chatsAsSeller)->sortByDesc('ThoiGianCapNhat')->values();

        return response()->json([
            'success' => true,
            'data'    => $danhSach
        ]);
    }
}
