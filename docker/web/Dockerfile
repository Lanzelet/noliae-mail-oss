# Noliae Mail OSS — image web (PHP-FPM + Apache).
# Build : docker build -t ghcr.io/noliae/mail-web:latest .
FROM php:8.4-apache AS deps

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libonig-dev libpq-dev libpng-dev libjpeg-dev libfreetype6-dev \
        libicu-dev pkg-config \
        unzip gnupg gnupg-agent gpg ca-certificates curl \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install zip mbstring opcache bcmath sockets pdo_pgsql gd intl \
 && pecl install redis && docker-php-ext-enable redis \
 && a2enmod rewrite headers expires \
 && rm -rf /var/lib/apt/lists/*

# Document root = /public
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
 && printf '<Directory /var/www/html/public>\n  AllowOverride All\n  Require all granted\n</Directory>\n' \
        > /etc/apache2/conf-available/laravel.conf \
 && a2enconf laravel

# PHP : 50 Mo par envoi mail (largement assez pour pièces jointes).
RUN { \
      echo 'upload_max_filesize=50M'; \
      echo 'post_max_size=55M'; \
      echo 'memory_limit=512M'; \
      echo 'max_execution_time=120'; \
      echo 'opcache.enable=1'; \
      echo 'opcache.memory_consumption=128'; \
      echo 'opcache.max_accelerated_files=10000'; \
      echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/mail-oss.ini

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node 20 pour build assets
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
 && apt-get install -y --no-install-recommends nodejs \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Install PHP deps
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Install JS deps + build
COPY package.json package-lock.json vite.config.js postcss.config.js tailwind.config.js ./
RUN npm ci --no-audit --no-fund

COPY . .
# bootstrap/cache et storage/framework/{cache,sessions,views} sont gitignored,
# absents d'un clone frais → on les crée sinon package:discover plante.
RUN mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
 && composer dump-autoload --optimize --no-dev \
 && npm run build \
 && rm -rf node_modules /root/.composer \
 && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# Entrypoint : migrations + cache + apache foreground
COPY docker/web/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
