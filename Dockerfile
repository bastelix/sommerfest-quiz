FROM php:8.2.29-alpine

# allow Composer to run as root inside the container
ARG COMPOSER_RETRY=5
ARG COMPOSER_PROCESS_TIMEOUT=300
ARG GITHUB_TOKEN
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_MEMORY_LIMIT=-1
ENV COMPOSER_RETRY=${COMPOSER_RETRY}
ENV COMPOSER_PROCESS_TIMEOUT=${COMPOSER_PROCESS_TIMEOUT}

RUN apk add --no-cache \
    curl git \
    libpng libjpeg-turbo freetype libwebp libzip postgresql-client imagemagick \
    python3 py3-pip \
    && apk add --no-cache --virtual .build-deps \
       libpng-dev libjpeg-turbo-dev freetype-dev libwebp-dev libzip-dev postgresql-dev imagemagick-dev $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd pdo_pgsql exif zip \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && apk del .build-deps

# install composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY composer.json composer.lock /var/www/
ARG COMPOSER_NO_DEV=0
RUN test -f composer.lock \
    && rm -rf /var/www/vendor \
    && if [ -n "${GITHUB_TOKEN}" ]; then \
        composer config -g github-oauth.github.com "${GITHUB_TOKEN}"; \
        INSTALL_PREFERENCE="--prefer-dist"; \
       else \
        INSTALL_PREFERENCE="--prefer-source"; \
       fi \
    && if [ "${COMPOSER_NO_DEV}" = "1" ]; then \
        composer install --no-interaction ${INSTALL_PREFERENCE} --no-progress --no-dev; \
       else \
        composer install --no-interaction ${INSTALL_PREFERENCE} --no-progress; \
       fi
COPY . /var/www
RUN mkdir -p /var/www/logs && chown www-data:www-data /var/www/logs
RUN mkdir -p /var/www/backup \
    && chown www-data:www-data /var/www/backup \
    && test -w /var/www/backup

# include custom PHP configuration
COPY config/php.ini /usr/local/etc/php/conf.d/custom.ini

# run static analysis during image build
# increase memory limit for phpstan to avoid out-of-memory errors
RUN if [ -f vendor/bin/phpstan ]; then vendor/bin/phpstan --no-progress --memory-limit=512M; fi

# entrypoint to install dependencies if host volume lacks vendor/
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 CMD ["php", "/var/www/scripts/check_stripe_config.php"]
EXPOSE 8080
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
