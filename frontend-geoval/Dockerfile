FROM node:20-alpine

# Définir le dossier de travail
WORKDIR /app

# Copier les fichiers de dépendances
COPY package*.json ./

# Installer les dépendances
RUN npm install

# Copier le reste du code
COPY . .

# Copier le script d'entrypoint
COPY docker-entrypoint.sh /usr/local/bin/
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh && chmod +x /usr/local/bin/docker-entrypoint.sh

# Exposer le port de développement d'Angular
EXPOSE 4200

# Définir l'entrypoint
ENTRYPOINT ["docker-entrypoint.sh"]

# Lancer le serveur Angular avec accès externe activé
CMD ["npx", "ng", "serve", "--host", "0.0.0.0", "--poll", "2000"]
