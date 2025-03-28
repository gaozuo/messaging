FROM composer:2.0 AS composer

ARG TESTING=false
ENV TESTING=$TESTING

WORKDIR /usr/local/src/

COPY composer.lock /usr/local/src/
COPY composer.json /usr/local/src/

RUN composer install \
    --ignore-platform-reqs \
    --optimize-autoloader \
    --no-plugins  \
    --no-scripts \
    --prefer-dist

FROM php:8.3.11-cli-alpine3.20

WORKDIR /usr/local/src/

COPY --from=composer /usr/local/src/vendor /usr/local/src/vendor
COPY . /usr/local/src/

CMD [ "tail", "-f", "/dev/null" ]