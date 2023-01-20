FROM php:8.1-cli

ARG COMPOSER_FLAGS="--prefer-dist --no-interaction"
ARG DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_PROCESS_TIMEOUT 3600

WORKDIR /code/

COPY docker/php-prod.ini /usr/local/etc/php/php.ini
COPY docker/composer-install.sh /tmp/composer-install.sh

RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev \
        libssl-dev \
        git \
        ssh \
        locales \
        unzip \
        wget \
    && rm -r /var/lib/apt/lists/* \
	&& sed -i 's/^# *\(en_US.UTF-8\)/\1/' /etc/locale.gen \
	&& locale-gen \
	&& chmod +x /tmp/composer-install.sh \
	&& /tmp/composer-install.sh

ENV LANGUAGE=en_US.UTF-8
ENV LANG=en_US.UTF-8
ENV LC_ALL=en_US.UTF-8

RUN wget https://fastdl.mongodb.org/tools/db/mongodb-database-tools-debian10-x86_64-100.5.2.deb \
    && wget https://downloads.mongodb.com/compass/mongodb-mongosh_1.3.1_amd64.deb \
    && apt install ./mongodb-database-tools-debian10-x86_64-100.5.2.deb \
    && apt install ./mongodb-mongosh_1.3.1_amd64.deb

# Intl is required for league/uri
RUN docker-php-ext-configure intl \
    && docker-php-ext-install intl

RUN pecl install mongodb \
  && docker-php-ext-enable mongodb

## Composer - deps always cached unless changed
# First copy only composer files
COPY composer.* /code/

# Download dependencies, but don't run scripts or init autoloaders as the app is missing
RUN composer install $COMPOSER_FLAGS --no-scripts --no-autoloader

# Copy rest of the app
COPY . /code/

# Run normal composer - all deps are cached already
RUN composer install $COMPOSER_FLAGS

# Make self-signed certificate trusted
COPY docker/certificates/ca-cert.pem /usr/local/share/ca-certificates/ca-cert.pem
RUN update-ca-certificates

CMD ["php", "/code/src/run.php"]
