FROM php:8.4-cli-alpine

# Installer les dépendances système et PHP requises, ainsi que Python 3
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    python3 \
    py3-pip

# Installer les extensions PHP
RUN docker-php-ext-install pdo pdo_pgsql zip

# Installer Composer en le copiant depuis l'image officielle
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Définir le dossier de travail
WORKDIR /var/www/html

# Copier le code source
COPY . .

# Rendre le script d'entrée exécutable et corriger les sauts de ligne CRLF si présents
RUN chmod +x entrypoint.sh && sed -i 's/\r$//' entrypoint.sh

# Exposer le port du serveur de développement Laravel
EXPOSE 8000

# Définir le script d'entrée
ENTRYPOINT ["/var/www/html/entrypoint.sh"]
