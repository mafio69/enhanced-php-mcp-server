FROM php:8.2-cli-alpine

# Install dependencies
RUN apk add --no-cache \
    git \
    unzip \
    curl \
    wget \
    libzip-dev \
    && docker-php-ext-install zip pcntl sockets

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy composer files first (cache)
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application
COPY . .

# Create logs directory
RUN mkdir -p logs && chmod 777 logs

EXPOSE 8080

CMD ["php", "index.php"]
