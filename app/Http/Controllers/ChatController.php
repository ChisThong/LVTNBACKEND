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
        $idUser = Auth::id();
        $idShop = $request->ID_Shop;
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
    public function guiTinNhan(Request $request)
    {
        $request->validate([
            'ID_PhongChat' => 'required|exists:phongchat,ID_PhongChat',
            'NoiDung'      => 'required|string',
            'LoaiNguoiGui' => 'required|in:user,shop'
        ]);
        $idUserHienTai = Auth::id();
        $tinNhan = TinNhan::create([
            'ID_PhongChat' => $request->ID_PhongChat,
            'LoaiNguoiGui' => $request->LoaiNguoiGui,
            'ID_NguoiGui'  => $idUserHienTai,
            'NoiDung'      => $request->NoiDung,
            'DaDoc'        => 0,
            'ThoiGianGui'  => now()
        ]);
        $phongChat = PhongChat::find($request->ID_PhongChat);
        $phongChat->update([
            'TinNhanCuoi'     => $request->NoiDung,
            'ThoiGianCapNhat' => now()
        ]);
        broadcast(new Message($tinNhan))->toOthers();

        return response()->json([
            'trang_thai' => 'Thành công',
            'du_lieu'    => $tinNhan
        ]);
    }

    public function layTinNhan($idPhongChat)
    {
        $idUser = Auth::id();
        $myShop = Shop::where('ID_User', $idUser)->first();
        $room = PhongChat::find($idPhongChat);
        if ($room) {
            if ((int)$room->ID_User === (int)$idUser) {
                TinNhan::where('ID_PhongChat', $idPhongChat)
                    ->where('LoaiNguoiGui', 'shop')
                    ->where('DaDoc', 0)
                    ->update(['DaDoc' => 1]);
            } elseif ($myShop && (int)$room->ID_Shop === (int)$myShop->ID_Shop) {
                TinNhan::where('ID_PhongChat', $idPhongChat)
                    ->where('LoaiNguoiGui', 'user')
                    ->where('DaDoc', 0)
                    ->update(['DaDoc' => 1]);
            }
        }
        $danhSachTinNhan = TinNhan::where('ID_PhongChat', $idPhongChat)
                            ->orderBy('ThoiGianGui', 'asc')
                            ->take(100)
                            ->get();

        return response()->json($danhSachTinNhan);
    }

    public function soTinChuaDoc(Request $request)
    {
        $idUser = Auth::id();
        $myShop = Shop::where('ID_User', $idUser)->first();
        // 1. Số tin chưa đọc khi đóng vai trò người mua 
        $userUnread = TinNhan::join('phongchat', 'tinnhanchat.ID_PhongChat', '=', 'phongchat.ID_PhongChat')
            ->where('phongchat.ID_User', $idUser)
            ->where('tinnhanchat.LoaiNguoiGui', 'shop')
            ->where('tinnhanchat.DaDoc', 0)
            ->count();
        // 2. Số tin chưa đọc khi đóng vai trò shop
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
    public function layDanhSachPhongChat(Request $request)
    {
        $idUser = Auth::id();
        // $isSellerRoute = $request->query('role') === 'seller';
        // if ($isSellerRoute) {
        //     $shop = Shop::where('ID_User', $idUser)->first();
        //     if (!$shop) {
        //         return response()->json([
        //             'success' => false,
        //             'message' => 'Bạn không có gian hàng.'
        //         ], 403);
        //     }
        //     $danhSach = PhongChat::where('phongchat.ID_Shop', $shop->ID_Shop)
        //         ->leftJoin('user', 'phongchat.ID_User', '=', 'user.ID_User')
        //         ->select('phongchat.*', 'user.HoTen as ten_doi_tac', 'user.email as email_doi_tac')
        //         ->selectRaw("'customer' as vai_tro")
        //         ->selectRaw("(SELECT COUNT(*) FROM tinnhanchat WHERE tinnhanchat.ID_PhongChat = phongchat.ID_PhongChat AND tinnhanchat.DaDoc = 0 AND tinnhanchat.LoaiNguoiGui = 'user') as tin_chua_doc")
        //         ->orderBy('phongchat.ThoiGianCapNhat', 'desc')
        //         ->get();

        //     return response()->json([
        //         'success' => true,
        //         'data'    => $danhSach
        //     ]);
        // }

        // Lấy các phòng chat mình đi mua (vai trò là Khách hàng) và đếm tin chưa đọc
        $chatsAsBuyer = PhongChat::where('phongchat.ID_User', $idUser)
            ->leftJoin('shop', 'phongchat.ID_Shop', '=', 'shop.ID_Shop')
            ->select('phongchat.*', 'shop.TenShop as ten_doi_tac', 'shop.logo as logo_doi_tac')
            ->selectRaw("'shop' as vai_tro")
            ->selectRaw("(SELECT COUNT(*) FROM tinnhanchat WHERE tinnhanchat.ID_PhongChat = phongchat.ID_PhongChat AND tinnhanchat.DaDoc = 0 AND tinnhanchat.LoaiNguoiGui = 'shop') as tin_chua_doc")
            ->get();

        // Lấy các phòng chat khách hàng nhắn đến Shop của mình (nếu mình là chủ Shop) và đếm tin chưa đọc
        $myShop = Shop::where('ID_User', $idUser)->first();
        $chatsAsSeller = collect();
        if ($myShop) {
            $chatsAsSeller = PhongChat::where('phongchat.ID_Shop', $myShop->ID_Shop)
                ->leftJoin('user', 'phongchat.ID_User', '=', 'user.ID_User')
                ->select('phongchat.*', 'user.HoTen as ten_doi_tac')
                ->selectRaw("'customer' as vai_tro")
                ->selectRaw("(SELECT COUNT(*) FROM tinnhanchat WHERE tinnhanchat.ID_PhongChat = phongchat.ID_PhongChat AND tinnhanchat.DaDoc = 0 AND tinnhanchat.LoaiNguoiGui = 'user') as tin_chua_doc")
                ->get();
        }
        $danhSach = $chatsAsBuyer->merge($chatsAsSeller)->sortByDesc('ThoiGianCapNhat')->values();

        return response()->json([
            'success' => true,
            'data'    => $danhSach
        ]);
    }
}
