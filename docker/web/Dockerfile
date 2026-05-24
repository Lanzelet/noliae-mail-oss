# Noliae Mail OSS — image web (PHP-FPM + Apache).
# Build : docker build -t ghcr.io/noliae/mail-web:latest .
FROM php:8.4-apache AS deps

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libonig-dev libpq-dev libpng-dev libjpeg-dev libfreetype6-dev \
        libicu-dev pkg-config \
        unzip gnupg gnupg-agent gpg ca-certificates curl \
        perl libio-socket-ssl-perl libnet-ssleay-perl \
        libdigest-hmac-perl libterm-readkey-perl libfile-copy-recursive-perl \
        libmail-imapclient-perl libdata-uniqid-perl libdist-checkconflicts-perl \
        libjson-perl libjson-webtoken-perl libtest-fatal-perl libtest-mockmodule-perl \
        libtest-pod-perl libtest-requires-perl libunicode-string-perl libregexp-common-perl \
        libio-tee-perl libfile-tail-perl libsys-meminfo-perl libio-compress-perl libfile-touch-perl \
        libencode-imaputf7-perl libcrypt-openssl-rsa-perl libfile-slurp-perl libauthen-ntlm-perl libproc-processtable-perl \
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

# PHP : config perf agressive pour webmail (grosses listes IMAP + body HTML).
RUN { \
      echo 'upload_max_filesize=50M'; \
      echo 'post_max_size=55M'; \
      echo 'memory_limit=1024M'; \
      echo 'max_execution_time=120'; \
      echo 'realpath_cache_size=4096K'; \
      echo 'realpath_cache_ttl=600'; \
      echo 'opcache.enable=1'; \
      echo 'opcache.enable_cli=1'; \
      echo 'opcache.memory_consumption=256'; \
      echo 'opcache.max_accelerated_files=20000'; \
      echo 'opcache.interned_strings_buffer=32'; \
      echo 'opcache.validate_timestamps=0'; \
      echo 'opcache.save_comments=1'; \
      echo 'opcache.jit_buffer_size=128M'; \
      echo 'opcache.jit=tracing'; \
    } > /usr/local/etc/php/conf.d/mail-oss.ini

# imapsync (script Perl single-file depuis GitHub) pour la migration admin
RUN curl -fsSL https://raw.githubusercontent.com/imapsync/imapsync/master/imapsync \
      -o /usr/local/bin/imapsync \
 && chmod +x /usr/local/bin/imapsync

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
 && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/public \
 && chmod -R 775 storage bootstrap/cache

# Entrypoint : migrations + cache + apache foreground
COPY docker/web/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Apache écoute sur 8080 (port non-privilégié = on peut tourner en non-root).
# UseCanonicalName Off + ServerName fictif : Apache ne doit JAMAIS générer
# d'URL avec :8080 (port interne) — Laravel construit les URLs publiques
# depuis APP_URL + X-Forwarded-* (TrustProxies).
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
 && sed -i 's/:80>/:8080>/' /etc/apache2/sites-enabled/000-default.conf \
 && echo 'ServerName localhost' >> /etc/apache2/apache2.conf \
 && echo 'UseCanonicalName Off' >> /etc/apache2/apache2.conf \
 && printf '<IfModule mpm_prefork_module>\n  StartServers 8\n  MinSpareServers 8\n  MaxSpareServers 32\n  MaxRequestWorkers 64\n  MaxConnectionsPerChild 1000\n</IfModule>\n' > /etc/apache2/conf-available/mpm-tune.conf \
 && a2enconf mpm-tune \
 && chown -R www-data:www-data /var/log/apache2 /var/run/apache2 /var/lib/apache2

EXPOSE 8080
USER www-data
ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
