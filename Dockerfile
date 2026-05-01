FROM php:8.2-fpm

RUN docker-php-ext-install pdo pdo_mysql

# Install and configure Nginx instead of Apache
RUN apt-get update && apt-get install -y nginx && rm -rf /var/lib/apt/lists/*

# Copy Nginx config
RUN mkdir -p /etc/nginx/conf.d
RUN echo 'server { \
    listen ${PORT:-8080}; \
    server_name _; \
    root /var/www/html; \
    index index.php; \
    location ~ \.php$ { \
        fastcgi_pass 127.0.0.1:9000; \
        fastcgi_index index.php; \
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; \
        include fastcgi_params; \
    } \
}' > /etc/nginx/conf.d/default.conf

WORKDIR /var/www/html
COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html

# Start both PHP-FPM and Nginx
CMD php-fpm -D && nginx -g "daemon off;"

ENV PORT=8080
EXPOSE 8080
