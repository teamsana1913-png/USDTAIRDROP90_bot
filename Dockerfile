# Use an official PHP image with Apache
FROM php:8.2-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
  && docker-php-ext-install zip

# ---- FIX: ENABLE APACHE REWRITE MODULE ----
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set the working directory
WORKDIR /var/www/html

# Copy composer files and install dependencies
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader

# Copy the rest of the application files
COPY . .

# Create the users.json file and set permissions
# The web server (www-data) needs to be able to write to this file
RUN touch users.json && chown www-data:www-data users.json

# Expose port 80 and start apache
EXPOSE 80