FROM dunglas/frankenphp:latest

# Install dependencies & PHP extensions
RUN apt-get update && apt-get install -y \
    git unzip curl \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pcntl pdo pdo_mysql mbstring \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY . /app

RUN composer clear-cache
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs --no-interaction

RUN chown -R www-data:www-data /app

EXPOSE 80

CMD ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=80"]
