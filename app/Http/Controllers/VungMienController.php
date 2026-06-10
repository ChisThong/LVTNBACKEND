<?php

namespace App\Http\Controllers;

use App\Models\Map;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Map\StoreMapRequest;
use App\Http\Requests\Map\UpdateMapRequest;
use Illuminate\Support\Facades\Storage;

class VungMienController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Map::query();

            $query->when($request->filled('ID_TinhThanh'), function ($T) use ($request) {
                $T->where('ID_TinhThanh', $request->ID_TinhThanh);
            });
            $query->when($request->filled('ID_Xa'), function ($X) use ($request) {
                $X->where('ID_Xa', $request->ID_Xa);
            });
            $query->when($request->filled('ID_Ap'), function ($A) use ($request) {
                $A->where('ID_Ap', $request->ID_Ap);
            });

            $query->when($request->filled('search_map'), function ($search) use ($request) {
                $search->where('TenDacSan', 'like', '%' . $request->search_map . '%');
            });
            $data = $query->orderby('ID_map', 'desc')->paginate(10);

            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi Hệ Thống: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(StoreMapRequest $request)
    {
        try {
            $data = $request->validated();

            if ($request->hasFile('HinhAnh')) {
                $data['HinhAnh'] = $request->file('HinhAnh')->store('Map', 'public');
            }

            $Map = Map::create($data);
            return response()->json([
                'success' => true,
                'message' => 'Thêm Bản Đồ thành công',
                'data' => $Map
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống ' . $e->getMessage()
            ], 500);
        }
    }
    public function  update(UpdateMapRequest $request, String $id)
    {
        try {
            $map = Map::findOrFail($id);
            $data = $request->validated();

            if ($request->hasFile('HinhAnh')) {
                if ($map->HinhAnh) {
                    Storage::disk('public')->delete($map->HinhAnh);
                }
                $data['HinhAnh'] = $request->file('HinhAnh')->store('Map', 'public');
            }
            $map->update($data);
            return response()->json([
                'success' => true,
                'message' => 'Cập nhật thành công',
                'data' => $map
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ Thống ' . $e->getMessage()
            ], 500);
        }
    }
    public function destroy(String $id)
    {
        try {
            $map = Map::where('ID', $id)->first();
            if (!$map) {
                return response()->json(['success' => false, 'message' => 'Không tìm Bản đồ cần xóa'], 404);
            }

            $map->delete();
            return response()->json(['success' => true, 'message' => 'Xóa thành công'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
