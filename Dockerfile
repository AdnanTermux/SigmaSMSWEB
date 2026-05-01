FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql

# Disable mpm_event in Apache's environment variables
# This prevents the entrypoint script from re-enabling it
RUN echo "APACHE_MODS_DISABLED='mpm_event'" >> /etc/apache2/envvars

# Ensure only mpm_prefork is enabled
RUN rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf
RUN a2enmod mpm_prefork
RUN a2enmod rewrite

WORKDIR /var/www/html
COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html

ENV APACHE_DOCUMENT_ROOT=/var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Configure Apache to listen on Railway's PORT (default 8080)
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf
ENV PORT=8080
EXPOSE 8080
