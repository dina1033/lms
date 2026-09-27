FROM php:8.4.1-fpm-alpine

RUN apk add --no-cache icu-dev \
    && docker-php-ext-install pdo pdo_mysql intl