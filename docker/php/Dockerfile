FROM php:8.4-fpm

# Dependências de sistema (Debian — sem problemas de compilação do Alpine)
RUN apt-get update && apt-get install -y \
    libpng-dev libxml2-dev libzip-dev \
    libpq-dev libonig-dev curl unzip \
    && docker-php-ext-install \
        bcmath mbstring opcache pdo pdo_pgsql xml zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

RUN useradd -u 1000 -ms /bin/bash www
USER www

EXPOSE 9000
CMD ["php-fpm"]
