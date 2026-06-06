FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    curl \
    zip \
    unzip \
    git

RUN docker-php-ext-install pdo pdo_mysql

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY composer.json composer.lock ./
RUN composer install --no-scripts  

# Copy the rest of the app
COPY . .

# Now run the scripts after artisan is available
RUN composer run-script post-autoload-dump

RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage \
    && chmod -R 755 /var/www/bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]