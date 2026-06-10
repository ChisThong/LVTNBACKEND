<?php

namespace App\Http\Controllers;

use App\Http\Requests\Blog\StoreBlogRequest;
use App\Http\Requests\Blog\UpdateBlogRequest;
use App\Models\Blog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class BaiVietController extends Controller
{
    /**
     * 1. Danh sách bài viết (public) + Có bộ lọc tìm kiếm
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $blog = Blog::with('tinhThanh:ID_TinhThanh,TenTinhThanh', 'user:ID_User,HoTen')
                ->when($request->search, function ($q) use ($request) {
                    $keyword = mb_strtolower($request->search, 'UTF-8');
                    return $q->whereRaw('LOWER(tittel) like ?', ["%{$keyword}%"]);
                })
                ->orderByDesc('ID_Blog')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'message' => 'Lấy dữ liệu thành công',
                'data'    => $blog
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 2. Xem chi tiết 1 bài viết
     */
    public function show(string $id): JsonResponse
    {
        try {
            $blog = Blog::with('tinhThanh:ID_TinhThanh,TenTinhThanh')
                ->where('ID_Blog', $id)
                ->first();

            if (!$blog) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy bài viết'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lấy chi tiết bài viết thành công',
                'data'    => $blog
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 3. Tạo bài viết mới
     */
    public function store(StoreBlogRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['ID_User'] = Auth::id();
            if ($request->hasFile('hinhanh')) {
                $data['hinhanh'] = $request->file('hinhanh')
                    ->store('blogs', 'public');
            }

            $blog = Blog::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Thêm bài viết thành công',
                'data'    => $blog
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 4. Cập nhật bài viết 
     */
    public function update(UpdateBlogRequest $request, string $id): JsonResponse
    {
        try {
            $blog = Blog::where('ID_Blog', $id)->first();

            if (!$blog) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy bài viết để cập nhật'
                ], 404);
            }

            $data = $request->validated();
            $data['ID_User'] = Auth::id();
            if ($request->hasFile('hinhanh')) {
                if ($blog->hinhanh) {
                    Storage::disk('public')->delete($blog->hinhanh);
                }
                $data['hinhanh'] = $request->file('hinhanh')
                    ->store('blogs', 'public');
            }

            $blog->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật bài viết thành công',
                'data'    => $blog
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 5. Xóa bài viết
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $blog = Blog::where('ID_Blog', $id)->first();
            if (!$blog) {
                return response()->json(['success' => false, 'message' => 'Không tìm thấy bài viết'], 404);
            }

            $blog->delete();
            return response()->json(['success' => true, 'message' => 'Xóa thành công'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
