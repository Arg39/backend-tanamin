FROM php:8.3-cli

WORKDIR /app

# Copy composer.json and composer.lock first to leverage Docker cache
COPY --chown=www-data:www-data composer.json composer.lock /app/

# Install dependencies
RUN apt update && apt upgrade -y && apt install -y \
    curl zip libzip-dev libpng-dev libjpeg-dev \
    libmagickwand-dev libmagickcore-dev imagemagick \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install zip pcntl gd pdo_mysql \
    && pecl install imagick \
    && docker-php-ext-enable zip gd pdo_mysql imagick \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy the entire application (including artisan)
COPY --chown=www-data:www-data . /app

# Install Laravel Octane and dependencies
RUN composer install && \
    composer require laravel/octane && \
    php artisan octane:install --server=frankenphp

# Ensure permissions for development
RUN chown -R www-data:www-data /app

EXPOSE 8000

CMD php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000