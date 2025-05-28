# Dockerfile
FROM php:8.2-cli

ARG DEBIAN_FRONTEND=noninteractive

# Install system dependencies and PHP extensions
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

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy Symfony app files
COPY . .

# Install PHP dependencies
RUN composer install --no-interaction --prefer-dist

# Permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port 80 for built-in PHP server
EXPOSE 80

# Start PHP's built-in web server
CMD ["php", "-S", "0.0.0.0:80", "-t", "public"]
