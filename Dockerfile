FROM php:8.2-fpm

RUN docker-php-ext-install pdo pdo_mysql

# Install Nginx and envsubst
RUN apt-get update && apt-get install -y nginx gettext-base && rm -rf /var/lib/apt/lists/*

# Create Nginx config template
RUN mkdir -p /etc/nginx/conf.d
RUN cat > /etc/nginx/conf.d/default.conf.template << 'EOF'
server {
    listen ${PORT};
    server_name _;
    root /var/www/html;
    index index.php;
    
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
EOF

WORKDIR /var/www/html
COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html

# Start script that substitutes variables and starts services
RUN cat > /start.sh << 'EOF'
#!/bin/bash
export PORT=${PORT:-8080}
envsubst < /etc/nginx/conf.d/default.conf.template > /etc/nginx/conf.d/default.conf
php-fpm -D
nginx -g "daemon off;"
EOF

RUN chmod +x /start.sh

ENV PORT=8080
EXPOSE 8080

CMD ["/start.sh"]
