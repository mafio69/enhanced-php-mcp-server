FROM php:8.2-cli-alpine

RUN apk add --no-cache \
    git \
    unzip \
    curl \
    wget \
    libzip-dev \
    linux-headers \
    && docker-php-ext-install zip pcntl sockets

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY . .

RUN mkdir -p logs storage && chmod 777 logs storage

ARG PORT=8795
ENV PORT=$PORT

EXPOSE $PORT

CMD php -S 0.0.0.0:$PORT -t /app/public /app/public/index.php
