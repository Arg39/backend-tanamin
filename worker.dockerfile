FROM php:8.3-cli

WORKDIR /app

# Copy composer.json and composer.lock first to leverage Docker cache
COPY --chown=www-data:www-data composer.json composer.lock /app/

# Install dependencies & extensions
RUN apt update && apt upgrade -y && apt install -y \
    curl zip libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libonig-dev \
    libmagickwand-dev libmagickcore-dev imagemagick git unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install zip pcntl gd pdo_mysql mbstring bcmath \
    && pecl install imagick \
    && docker-php-ext-enable imagick

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy the entire application
COPY --chown=www-data:www-data . /app

# Install Laravel Octane and dependencies
RUN composer install --optimize-autoloader --no-dev && \
    composer require laravel/octane && \
    php artisan octane:install --server=frankenphp

# Increase PHP memory limit to 512M
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/memory-limit.ini

# Ensure permissions for development
RUN chown -R www-data:www-data /app

EXPOSE 8000

CMD php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000
