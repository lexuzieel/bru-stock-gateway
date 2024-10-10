FROM php:8.2-fpm

RUN apt-get update && apt-get install -y git zip unzip

COPY --from=composer/composer:latest-bin /composer /usr/bin/composer

COPY . .

RUN composer install
