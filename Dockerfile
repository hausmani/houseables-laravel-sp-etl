# Use a PHP 8.3 base image
FROM php:8.3-fpm

# Install necessary dependencies and PHP extensions (including zip and pdo_pgsql)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    git \
    libxml2-dev \
    libpq-dev \
    libzip-dev && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install gd pdo pdo_pgsql zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set the working directory inside the container
WORKDIR /var/www/html

# Copy the current directory content into the container
COPY . .

# Run composer install to install dependencies
RUN composer install

# Expose the port
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]
