HƯỚNG DẪN SỬ DỤNG HỆ THỐNG
1. Yêu cầu môi trường

Trước khi chạy hệ thống cần cài đặt:

PHP >= 8.2
Composer
Node.js >= 18
MySQL
XAMPP hoặc Laragon
Git
2. Cài đặt dự án
Clone source code
git clone <repository-url>
Backend (Laravel)

Di chuyển vào thư mục backend:

cd marketplace-backend

Cài đặt thư viện:

composer install

Tạo file môi trường:

cp .env.example .env

Sinh khóa ứng dụng:

php artisan key:generate

Cấu hình cơ sở dữ liệu trong file .env:

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=lvtn1
DB_USERNAME=root
DB_PASSWORD=

Chạy migration:

php artisan migrate

Khởi động Backend:

php artisan serve

Mặc định Backend chạy tại:

http://127.0.0.1:8000