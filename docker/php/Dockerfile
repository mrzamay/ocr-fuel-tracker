FROM php:8.3-fpm-alpine

# Установка системных зависимостей
RUN apk add --no-cache \
    linux-headers \
    build-base \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libpq-dev \
    zip \
    unzip \
    git \
    curl

# Установка расширений PHP (в т.ч. для PostgreSQL)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql pgsql gd bcmath

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN { \
    echo 'upload_max_filesize=32M'; \
    echo 'post_max_size=36M'; \
    echo 'memory_limit=256M'; \
  } > /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www

CMD ["php-fpm"]
EXPOSE 9000
