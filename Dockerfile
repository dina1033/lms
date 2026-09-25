FROM php:8.4.1-fpm-alpine

RUN docker-php-ext-install pdo pdo_mysql
