FROM dunglas/frankenphp:1.0.0

WORKDIR /app

# Dependencies
RUN apt update && apt upgrade -y && apt install -y \
    curl zip libzip-dev libpng-dev libjpeg-dev \
    libmagickwand-dev libmagickcore-dev imagemagick \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install zip pcntl gd pdo_mysql \
    && pecl install imagick \
    && docker-php-ext-enable zip gd pdo_mysql imagick \
    && rm -rf /var/lib/apt/lists/*

# Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy project
COPY . /app

# Install PHP deps
RUN composer install --no-dev --optimize-autoloader

# Permission
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache && \
    chmod -R 775 /app/storage /app/bootstrap/cache

# Start frankenphp directly with .franken.yaml
CMD ["frankenphp", "serve"]

RUN composer require laravel/octane
RUN chmod -R 777 .
RUN php artisan octane:install
