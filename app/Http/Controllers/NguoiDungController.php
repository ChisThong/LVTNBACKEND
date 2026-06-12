<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NguoiDungController extends Controller
{
    public function index(Request $request): JsonResponse{
        try{
            $query=User::query();
            if($request->filled('ID_role')){
                $query->where('ID_role', $request->ID_role);
            }
            if($request->filled('TrangThai')){
                $query->where('TrangThai',$request->TrangThai);
            }
            if ($request->filled('search')) {
                $searchTerm = '%' . $request->search . '%';
                $query->where(function ($subQuery) use ($searchTerm) {
                    $subQuery->where('HoTen', 'like', $searchTerm)
                             ->orWhere('email', 'like', $searchTerm)
                             ->orWhere('sdt', 'like', $searchTerm);
                });
            }
            $data=$query->orderby('ID_User','asc')->paginate(10);
            $countAdmin=User::where('ID_role',1)->count();
            $countSeller=User::where('ID_role',3)->count();
            $countclock=User::where('TrangThai',2)->count();
            $total=User::count();

            return response()->json([
                'success'=> true,
                'data'=> $data,
                'demadmin'=>$countAdmin,
                'demseller'=>$countSeller,
                'demblock'=>$countclock,
                'tong'=>$total
            ],200);
        }catch(\Exception $e){
            return response()->json([
                'success'=>false,
                'message'=>'Lỗi hệ thống: '.$e->getMessage()
            ],500);
        }
    }
    public function changeclock(String $id){
        try{
            $user=User::findOrFail($id);
            if($user->TrangThai==1){
                $user->TrangThai=2;
                $user->save();
                $message='Khóa tài khoảng' .$user->HoTen.' thành công.';
            }else{
                $user->TrangThai=1;
                $user->save();
                $message='Mở khóa tài khoảng' .$user->HoTen.' thành công.';
            }
            return response()->json([
                'success'=>true,
                'message'=>$message
            ],200);
        }catch(\Exception $e){
            return response()->json([
                'success'=>false,
                'message'=>'Lỗi hệ thống: '.$e->getMessage()
            ],500);
        }
    }
    public function update(Request $request,String $id){
     try{
            $user=User::findOrFail($id);
            $user->ID_role=$request->ID_role;
            $user->save();
           
            return response()->json([
                'success'=>true,
                'message'=>'Cập nhật thành công'
            ],200);
        }catch(\Exception $e){
            return response()->json([
                'success'=>false,
                'message'=>'Lỗi hệ thống: '.$e->getMessage()
            ],500);
        }
    }
}
