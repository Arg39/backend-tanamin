FROM dunglas/frankenphp:php8.3

ENV SERVER_NAME=":80"
ENV FRANKENPHP_RESPONSE_BUFFERING="off"

WORKDIR /app

# Copy project
COPY . /app

# Install system dependencies + PHP extensions
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

# ✅ Tambah setting PHP memory limit
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/memlimit.ini

# Copy composer from official composer image
COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

# Install PHP dependencies
RUN composer install
