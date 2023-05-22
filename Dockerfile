# Utiliza la imagen base de PHP 7.4 con FPM
FROM php:7.4-fpm

# Establece el directorio de trabajo dentro del contenedor
WORKDIR /var/www/html

# Instala las dependencias del sistema necesarias
RUN apt-get update && apt-get install -y \
    libonig-dev \
    libzip-dev \
    unzip \
    git

# Instala las extensiones de PHP necesarias
RUN docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath

# Instala Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copia los archivos de la aplicación a la imagen
COPY . .

# Instala las dependencias de la aplicación con Composer
RUN composer install --no-interaction --optimize-autoloader

# Copia el archivo de configuración de entorno
COPY .env.example .env

# Genera la clave de aplicación de Laravel
RUN php artisan key:generate

# Establece los permisos adecuados en el directorio de almacenamiento de Laravel
RUN chown -R www-data:www-data /var/www/html/storage
RUN chmod -R 775 /var/www/html/storage

# Expone el puerto 9000 para PHP-FPM
EXPOSE 9000

# Ejecuta el servidor PHP-FPM
CMD ["php-fpm"]