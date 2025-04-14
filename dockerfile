FROM dunglas/frankenphp:php8.3

ENV SERVER_NAME=":80"
ENV FRANKENPHP_RESPONSE_BUFFERING="off"

WORKDIR /app

COPY . /app

RUN apt update && apt install -y \
    zip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libmagickwand-dev \
    libmagickcore-dev \
    imagemagick \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install zip gd pdo_mysql \
    && pecl install imagick \
    && docker-php-ext-enable zip gd pdo_mysql imagick

RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/memlimit.ini

RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

RUN composer install
