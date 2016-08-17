FROM debian:jessie

MAINTAINER "Stefan Berger" <berger@mogic.com>

RUN usermod -u 1000 www-data
RUN groupmod -g 1000 www-data
ENV TERM xterm

RUN apt-get -y update \
   && apt-get -y install php5 apache2 libapache2-mod-php5 php5-curl php5-mysqlnd php5-ldap php5-oauth php5-json php5-intl curl git-core && \
   apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Install composer
ADD https://getcomposer.org/composer.phar /usr/bin/composer
RUN chmod 0755 /usr/bin/composer

RUN a2enmod rewrite

# Install composer
ADD https://getcomposer.org/composer.phar /usr/bin/composer
RUN chmod 0755 /usr/bin/composer

# apache2 conf
COPY docker/web/timetracker.conf /etc/apache2/sites-available/timetracker.conf
RUN a2ensite timetracker.conf
RUN a2dissite 000-default

ENV TZ=Europe/Berlin
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Install App CMS
RUN mkdir -p /var/www/html/
ADD ./ /var/www/html/
RUN ln -s /var/www/html/web/app.php /var/www/html/web/index.php

RUN cd  /var/www/html/ && /usr/bin/composer install
RUN chown -R www-data:www-data /var/www


EXPOSE 80

VOLUME ["/var/www/html /var/log/apache2 /etc/apache2"]

ENTRYPOINT ["/usr/sbin/apache2ctl", "-D", "FOREGROUND"]
