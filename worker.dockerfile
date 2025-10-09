FROM dunglas/frankenphp:latest

RUN apt-get update && apt-get install -y \
    git unzip curl \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev default-mysql-client \
    libzip-dev \
    autoconf g++ make \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pcntl pdo pdo_mysql mbstring zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get remove -y autoconf g++ make \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/* \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install gosu (official way)
RUN set -eux; \
    arch="$(dpkg --print-architecture)"; \
    url="https://github.com/tianon/gosu/releases/download/1.16/gosu-${arch}"; \
    curl -fsSL "$url" -o /usr/local/bin/gosu; \
    chmod +x /usr/local/bin/gosu; \
    gosu nobody true

WORKDIR /app

COPY . /app

RUN composer clear-cache

# Hapus baris ini:
# RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs --no-interaction

# Hapus baris ini:
# RUN chown -R www-data:www-data /app

RUN mkdir -p /app/storage /app/public/storage /app/bootstrap/cache

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]