<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\HinhAnh;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    // ─── Eager-load mặc định ─────────────────────────────────────────────────
    private array $with = ['shop', 'phanLoai', 'hinhAnh', 'tinhThanh'];

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/products
    // Public — mọi người đều xem được
    // ─────────────────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = Product::with($this->with)
            ->where('TrangThai', 1);

        // Tìm kiếm theo tên
        if ($request->filled('search')) {
            $query->where('TenSanPham', 'like', '%' . $request->search . '%');
        }

        // Lọc theo phân loại
        if ($request->filled('ID_PhanLoai')) {
            $query->where('ID_PhanLoai', $request->ID_PhanLoai);
        }

        // Lọc theo shop
        if ($request->filled('ID_Shop')) {
            $query->where('ID_Shop', $request->ID_Shop);
        }

        // Lọc theo tỉnh/thành
        if ($request->filled('ID_TinhThanh')) {
            $query->where('ID_TinhThanh', $request->ID_TinhThanh);
        }

        // Lọc theo khoảng giá
        if ($request->filled('gia_min')) {
            $query->where('Gia', '>=', $request->gia_min);
        }
        if ($request->filled('gia_max')) {
            $query->where('Gia', '<=', $request->gia_max);
        }

        // Sắp xếp
        $sortBy  = in_array($request->sort_by, ['Gia', 'TenSanPham', 'ID_SanPham'])
                   ? $request->sort_by : 'ID_SanPham';
        $sortDir = $request->sort_dir === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        $perPage = (int) ($request->per_page ?? 12);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Danh sách sản phẩm.',
            'data'    => $products,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/products/{id}
    // Public — mọi người đều xem được
    // ─────────────────────────────────────────────────────────────────────────
    public function show(int $id): JsonResponse
    {
        $product = Product::with($this->with)->find($id);

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Sản phẩm không tồn tại.',
                'data'    => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Chi tiết sản phẩm.',
            'data'    => $product,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/products
    // Middleware: auth:sanctum + role:Admin,NguoiBan
    // ─────────────────────────────────────────────────────────────────────────
    public function store(StoreProductRequest $request): JsonResponse
    {
        $user = $request->user();

        // NguoiBan chỉ được tạo sản phẩm cho shop của chính mình
        if ($user->role->Ten_role === 'NguoiBan') {
            $shopBelongsToUser = Shop::where('ID_Shop', $request->ID_Shop)
                ->where('ID_User', $user->ID_User)
                ->exists();

            if (! $shopBelongsToUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền tạo sản phẩm cho shop này.',
                ], 403);
            }
        }

        $data = $request->safe()->except('hinh_anh');

        // TrangThai mặc định = 1 (đang bán), nhưng nếu SoLuongTon = 0
        // thì boot() hook trong model sẽ tự động set TrangThai = 0
        $data['TrangThai'] = $data['TrangThai'] ?? Product::TRANG_THAI_HIEN;

        $product = Product::create($data);

        // Upload hình ảnh nếu có
        if ($request->hasFile('hinh_anh')) {
            foreach ($request->file('hinh_anh') as $file) {
                $path = $file->store('products', 'public');
                HinhAnh::create([
                    'HinhAnh'    => $path,
                    'ID_SanPham' => $product->ID_SanPham,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Tạo sản phẩm thành công.',
            'data'    => $product->load($this->with),
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUT /api/products/{id}
    // Middleware: auth:sanctum + role:Admin,NguoiBan
    // ─────────────────────────────────────────────────────────────────────────
    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Sản phẩm không tồn tại.',
                'data'    => null,
            ], 404);
        }

        $user = $request->user();

        // NguoiBan chỉ được sửa sản phẩm trong shop của mình
        if ($user->role->Ten_role === 'NguoiBan') {
            $shopBelongsToUser = Shop::where('ID_Shop', $product->ID_Shop)
                ->where('ID_User', $user->ID_User)
                ->exists();

            if (! $shopBelongsToUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền chỉnh sửa sản phẩm này.',
                ], 403);
            }
        }

        $data = $request->safe()->except(['hinh_anh', 'xoa_hinh_anh']);
        $product->update($data);

        // Upload ảnh mới nếu có
        if ($request->hasFile('hinh_anh')) {
            foreach ($request->file('hinh_anh') as $file) {
                $path = $file->store('products', 'public');
                HinhAnh::create([
                    'HinhAnh'    => $path,
                    'ID_SanPham' => $product->ID_SanPham,
                ]);
            }
        }

        // Xoá ảnh theo danh sách ID nếu có
        if ($request->filled('xoa_hinh_anh')) {
            $hinhAnhToDelete = HinhAnh::whereIn('ID_HinhAnh', $request->xoa_hinh_anh)
                ->where('ID_SanPham', $product->ID_SanPham)
                ->get();

            foreach ($hinhAnhToDelete as $ha) {
                Storage::disk('public')->delete($ha->HinhAnh);
                $ha->delete();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật sản phẩm thành công.',
            'data'    => $product->fresh($this->with),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE /api/products/{id}
    // Middleware: auth:sanctum + role:Admin,NguoiBan
    // Không xóa cứng — chỉ cập nhật TrangThai = 0 (Ngừng bán)
    // ─────────────────────────────────────────────────────────────────────────
    public function destroy(Request $request, int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Sản phẩm không tồn tại.',
                'data'    => null,
            ], 404);
        }

        // Nếu đã ngừng bán rồi, không cần thực hiện lại
        if ((int) $product->TrangThai === Product::TRANG_THAI_AN) {
            return response()->json([
                'success' => false,
                'message' => 'Sản phẩm này đã được ngừng bán trước đó.',
                'data'    => $product->load($this->with),
            ], 409);
        }

        $user = $request->user();

        // NguoiBan chỉ được ngừng bán sản phẩm trong shop của mình
        if ($user->role->Ten_role === 'NguoiBan') {
            $shopBelongsToUser = Shop::where('ID_Shop', $product->ID_Shop)
                ->where('ID_User', $user->ID_User)
                ->exists();

            if (! $shopBelongsToUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền ngừng bán sản phẩm này.',
                ], 403);
            }
        }

        // Soft delete: chỉ cập nhật TrangThai = 0, KHÔNG xóa bản ghi khỏi DB
        $product->update(['TrangThai' => Product::TRANG_THAI_AN]);

        return response()->json([
            'success' => true,
            'message' => 'Sản phẩm đã được ngừng bán thành công.',
            'data'    => $product->fresh($this->with),
        ]);
    }
}
