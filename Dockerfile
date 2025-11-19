FROM php:8.2-apache

# Enable Apache Rewrite Module
RUN a2enmod rewrite

# Copy all API files to Apache root
COPY . /var/www/html/

# Permissions (optional but safe)
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
