FROM php:8.4-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libonig-dev unzip gnupg libpq-dev \
 && docker-php-ext-install zip mbstring opcache bcmath sockets pdo_pgsql \
 && a2enmod rewrite \
 && rm -rf /var/lib/apt/lists/*

# Document root Laravel = /public
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' \
        /etc/apache2/sites-available/000-default.conf \
 && printf '<Directory /var/www/html/public>\n  AllowOverride All\n  Require all granted\n</Directory>\n' \
        > /etc/apache2/conf-available/laravel.conf \
 && a2enconf laravel

# Limites PHP — jusqu'à 1 Go par envoi (mode Stockage Noliae S3).
RUN { \
      echo 'upload_max_filesize=1024M'; \
      echo 'post_max_size=1100M'; \
      echo 'memory_limit=1280M'; \
      echo 'max_execution_time=300'; \
    } > /usr/local/etc/php/conf.d/noliae.ini

WORKDIR /var/www/html
EXPOSE 80
