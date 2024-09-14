# Use the official PHP image as a parent image
FROM php:8.2-fpm

# Set environment variables
ENV APP_ENV=prod
ENV APP_DEBUG=0

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libicu-dev \
    libzip-dev \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd \
    && docker-php-ext-install intl \
    && docker-php-ext-install pdo pdo_mysql zip

# Set working directory
WORKDIR /var/www/html

# Copy the composer.lock and composer.json files
COPY composer.lock composer.json ./

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install PHP dependencies
RUN composer install --no-autoloader --no-scripts

# Copy the application files
COPY . .

# Install PHP autoload files
RUN composer dump-autoload --optimize

RUN php bin/console make:migration

RUN bin/console doctrine:migrations:migrate

# Expose port 9000
EXPOSE 9000

# Start the PHP FastCGI Process Manager
CMD ["php-fpm"]
