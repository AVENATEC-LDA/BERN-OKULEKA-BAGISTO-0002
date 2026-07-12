FROM php:8.3-fpm-alpine

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

RUN apk add --no-cache \
    bash \
    curl \
    git \
    nginx \
    supervisor \
    unzip \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    oniguruma-dev \
    libxml2-dev \
    icu-dev \
    libzip-dev \
    zlib-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl bcmath gd zip opcache \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY . /var/www/html
WORKDIR /var/www/html

RUN cp .env.example .env \
    && composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev \
    && mkdir -p storage/framework/{cache,sessions,views,testing} bootstrap/cache public/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache public

COPY docker/dokploy/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/dokploy/supervisord.conf /etc/supervisord.conf
COPY docker/dokploy/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
