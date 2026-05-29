FROM php:8.4-cli

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    ffmpeg \
    python3 \
    python3-pip

# Install latest yt-dlp
RUN python3 -m pip install -U yt-dlp --break-system-packages

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

# Install Laravel packages
RUN composer install --no-dev --optimize-autoloader

# Laravel permissions
RUN chmod -R 777 storage bootstrap/cache

EXPOSE 10000

CMD php artisan serve --host=0.0.0.0 --port=10000