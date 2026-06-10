<?php

namespace App\Http\Controllers;

use App\Models\TinhThanh;
use App\Models\Xa;
use App\Models\Ap;
use Illuminate\Http\Request;

class DiaLyController extends Controller
{
    public function getTinh(){
        $data=TinhThanh::all();
        return response()->json([
            'success'=>true,
            'data'=>$data
        ]);
    }
    public function getXa(Request $request){
        $data=Xa::where('ID_TinhThanh',$request->ID_TinhThanh)->get();
        return response()->json([
            'success'=>true,
            'data'=>$data
        ]);
    }
    public function getAp(Request $request){
        $data=Ap::where('ID_Xa',$request->ID_Xa)->get();
        return response()->json([
            'success'=>true,
            'data'=>$data
        ]);
    }
}
