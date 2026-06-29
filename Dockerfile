FROM php:8.2-fpm-alpine

# Cài đặt các extension cần thiết cho Laravel
RUN apk add --no-cache --update \
    nginx \
    supervisor \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    oniguruma-dev \
    postgresql-dev

RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd

# Cài đặt Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Thiết lập thư mục làm việc
WORKDIR /var/www/html

# Copy toàn bộ code vào container
COPY . .

# Cài đặt dependencies của Laravel (tự động fallback sang git clone nếu tải zip lỗi)
RUN composer install --no-interaction --optimize-autoloader --no-dev --prefer-install=auto

# Tạo liên kết lưu trữ để truy cập ảnh công khai
RUN php artisan storage:link

# Cấu hình Nginx và Supervisor
COPY ./docker/nginx.conf /etc/nginx/nginx.conf
COPY ./docker/supervisord.conf /etc/supervisord.conf

# Cấp quyền cho thư mục storage và bootstrap/cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]