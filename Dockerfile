FROM php:8.2-apache

# Instalar extensões
RUN docker-php-ext-install curl json session

# Copiar php.ini
COPY php.ini /usr/local/etc/php/

# Copiar arquivos do projeto
COPY . /var/www/html/

# Configurar permissões
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Habilitar rewrite
RUN a2enmod rewrite

EXPOSE 80

CMD ["apache2-foreground"]