FROM php:8.4-cli


RUN apt-get update && apt-get install -y libzip-dev libpq-dev
RUN docker-php-ext-install zip pdo pdo_pgsql

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"
    && docker-php-ext-install pdo pdo_pgsql

WORKDIR /app

COPY . .

RUN composer install
RUN if [ -f database.sql ]; then psql $DATABASE_URL -f database.sql || true; fi

CMD ["bash", "-c", "make start"]
