# Minimal PHP runtime for local development and demos.
#
# Built on the CLI image because the container runs PHP's built-in web server:
# no Apache configuration is needed, and the document root is passed on the
# command line so it cannot drift from `public/`.

FROM php:8.2-cli-alpine

# Extensions the application requires. pdo_sqlite is included so the test suite
# can run inside the container as well.
RUN set -eux; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql; \
    apk add --no-cache libpng-dev libjpeg-turbo-dev freetype-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" gd; \
    pecl install pcov >/dev/null 2>&1 || true; \
    apk del .build-deps

WORKDIR /app

# Install dependencies first so the layer is cached across source edits.
# The application runs without Composer, so a missing vendor directory is fine.
COPY composer.json ./
RUN if [ -f composer.json ]; then \
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer; \
    fi

COPY . .

# storage is written to at runtime by sessions and uploads.
RUN mkdir -p storage/sessions storage/uploads && chmod -R 0777 storage

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
