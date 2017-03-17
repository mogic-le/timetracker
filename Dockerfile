FROM debian:jessie

MAINTAINER "Stefan Berger" <berger@mogic.com>

ENV TERM xterm
ENV TZ=Europe/Berlin

RUN usermod -u 1000 www-data\
    && groupmod -g 1000 www-data\
    && apt-get -y update \
    && apt-get -y install\
        apache2\
        curl\
        git-core\
        libapache2-mod-php5\
        php5-curl\
        php5-intl\
        php5-json\
        php5-ldap\
        php5-mysqlnd\
        php5-oauth\
        php5\
    && apt-get clean\
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*\
    && a2enmod rewrite\
    && curl --output /usr/bin/composer https://getcomposer.org/composer.phar\
    && chmod 0755 /usr/bin/composer

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
    && cd /var/www/html/\
    && /usr/bin/composer install\
    && chown -R www-data:www-data /var/www

EXPOSE 80

VOLUME ["/var/www/html", "/var/log/apache2", "/etc/apache2"]

ENTRYPOINT ["/usr/sbin/apache2ctl", "-D", "FOREGROUND"]
