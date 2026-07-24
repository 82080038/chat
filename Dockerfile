FROM php:8.3-apache

# System dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip zip libzip-dev libonig-dev libxml2-dev libcurl4-openssl-dev \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mbstring xml curl zip opcache

# PECL extensions: redis
RUN pecl install redis && docker-php-ext-enable redis

# Apache: enable mod_rewrite, set ServerName, document root → public/
RUN a2enmod rewrite && \
    echo "ServerName localhost" >> /etc/apache2/apache2.conf && \
    sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Allow .htaccess overrides in document root
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
