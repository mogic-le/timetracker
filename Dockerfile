ARG BASEIMAGE=debian:buster

FROM ${BASEIMAGE}

MAINTAINER "Stefan Berger" <berger@mogic.com>

ENV TERM xterm
ENV TZ=Europe/Berlin

RUN usermod -u 1000 www-data\
    && groupmod -g 1000 www-data\
    && apt-get -y update \
    && apt-get -y upgrade \
    && apt-get -y install apt-transport-https lsb-release ca-certificates curl\
    && curl -sSL -o /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg\
    && sh -c 'echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list'\
    && apt-get update\
    && apt-get -y install\
        apache2\
        curl\
        git-core\
        libapache2-mod-php7.4\
        locales\
        php7.4-curl\
        php7.4-gd\
        php7.4-intl\
        php7.4-json\
        php7.4-ldap\
        php7.4-mbstring\
        php7.4-mysqlnd\
        php7.4-oauth\
        php7.4-xml\
        php7.4-zip\
        php7.4\
    && apt-get clean\
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*\
    && a2enmod rewrite\
    && curl -s --output /usr/bin/composer https://getcomposer.org/download/latest-2.2.x/composer.phar\
    && chmod 0755 /usr/bin/composer\
    && echo 'de_DE.UTF-8 UTF-8' > /etc/locale.gen\
    && locale-gen de_DE.UTF-8

# apache2 conf
COPY docker/web/timetracker.conf /etc/apache2/sites-available/timetracker.conf
RUN a2ensite timetracker.conf\
    && a2dissite 000-default\
    && ln -snf /usr/share/zoneinfo/$TZ /etc/localtime\
    && echo $TZ > /etc/timezone

# Install App CMS
RUN mkdir -p /var/www/html/
ADD ./ /var/www/html/
RUN ln -s /var/www/html/web/app.php /var/www/html/web/index.php\
    && cd /var/www/html/ && composer install\
    && chown -R www-data:www-data /var/www\
    && test -d /var/www/html/timalytics && (cd /var/www/html/timalytics && composer install)

EXPOSE 80

VOLUME ["/var/www/html", "/var/log/apache2", "/etc/apache2"]

ENTRYPOINT ["/usr/sbin/apache2ctl", "-D", "FOREGROUND"]
