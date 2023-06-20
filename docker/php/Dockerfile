FROM php:8.1.4

WORKDIR /var/www/karma8

RUN apt update

RUN apt install -y zlib1g-dev g++ git libicu-dev zip libzip-dev libpq-dev\
    && docker-php-ext-install pdo pdo_pgsql pgsql sockets

RUN apt update && apt install -y librabbitmq-dev libssh-dev \
    && docker-php-ext-install bcmath sockets \
    && pecl install amqp \
    && docker-php-ext-enable amqp

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN apt-get update && apt-get install -y libmcrypt-dev

RUN apt-get update && apt-get install -y cron

COPY ./composer.json composer.json
COPY ./composer.lock composer.lock
RUN composer install \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --no-dev \
    --prefer-dist

COPY . .
RUN composer dump-autoload

RUN crontab -l | { cat; echo "0 5 * * * /usr/local/bin/php /var/www/karma8/src/MailingListPreparer.php -d=1"; } | crontab -
RUN crontab -l | { cat; echo "15 5 * * * /usr/local/bin/php /var/www/karma8/src/MailingListPreparer.php -d=3"; } | crontab -
RUN crontab -l | { cat; echo "*/10 * * * * /usr/local/bin/php /var/www/karma8/src/MailerConsumer.php"; } | crontab -
RUN crontab -l | { cat; echo "*/10 * * * * /usr/local/bin/php /var/www/karma8/src/MailerConsumer.php"; } | crontab -
RUN crontab -l | { cat; echo "*/10 * * * * /usr/local/bin/php /var/www/karma8/src/CheckerConsumer.php"; } | crontab -
RUN crontab -l | { cat; echo "*/10 * * * * /usr/local/bin/php /var/www/karma8/src/CheckerConsumer.php"; } | crontab -
