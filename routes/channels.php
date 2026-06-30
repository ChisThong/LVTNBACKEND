<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->ID_User === (int) $id;
});
Broadcast::channel('phong-chat.{idPhongChat}', function ($user, $idPhongChat) {
    $phongChat = \App\Models\PhongChat::find($idPhongChat);
    if (!$phongChat) return false;
    // Lấy thông tin shop để kiểm tra chủ sở hữu
    $shop = \App\Models\Shop::find($phongChat->ID_Shop);
    // Chỉ cho phép 2 bên tham gia phòng chat (Người mua OR Người bán/Chủ shop) được kết nối
    return (int) $user->ID_User === (int) $phongChat->ID_User 
        || ($shop && (int) $user->ID_User === (int) $shop->ID_User);
});