FROM php:8.2-cli

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    default-mysql-client \
    libzip-dev \
    zip \
    libonig-dev \
    libicu-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    pkg-config \
    && docker-php-ext-install pdo pdo_mysql intl zip opcache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["php", "-S", "0.0.0.0:80", "-t", "public"]