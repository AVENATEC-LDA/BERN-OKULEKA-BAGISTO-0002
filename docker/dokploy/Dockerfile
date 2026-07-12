FROM php:8.3-fpm-bookworm

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC
ENV CACHE_STORE=file
ENV APP_ENV=production
ENV APP_DEBUG=false

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        bash \
        curl \
        git \
        nginx \
        supervisor \
        unzip \
        libpng-dev \
        libjpeg-dev \
        libwebp-dev \
        libfreetype6-dev \
        libonig-dev \
        libxml2-dev \
        libicu-dev \
        libzip-dev \
        zlib1g-dev \
        libcurl4-openssl-dev \
        default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl bcmath gd zip opcache calendar \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html
WORKDIR /var/www/html

RUN mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/framework/testing public/uploads \
    && cp .env.example .env \
    && composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache public

COPY docker/dokploy/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/dokploy/default-site.conf /etc/nginx/conf.d/default.conf
COPY docker/dokploy/supervisord.conf /etc/supervisord.conf
COPY docker/dokploy/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN rm -f /etc/nginx/sites-enabled/default && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=80"]
