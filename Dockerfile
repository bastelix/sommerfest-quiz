FROM php:8.2-alpine

RUN apk add --no-cache libpng libjpeg-turbo freetype \
    && apk add --no-cache --virtual .build-deps libpng-dev libjpeg-turbo-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd \
    && apk del .build-deps

# install composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . /var/www
RUN composer install --no-interaction --prefer-dist --no-progress

# run static analysis during image build
RUN if [ -f vendor/bin/phpstan ]; then \
        vendor/bin/phpstan --no-progress; \
    fi

# entrypoint to install dependencies if host volume lacks vendor/
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

