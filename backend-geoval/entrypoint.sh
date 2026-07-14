#!/bin/sh

# Convert potential CRLF to LF just in case
# (Avoids execution errors on Windows checkouts)

echo "Configuration de l'environnement..."
if [ ! -f .env ]; then
    cp .env.example .env
    # Mettre à jour les variables de connexion pour Docker
    sed -i 's/DB_HOST=127.0.0.1/DB_HOST=db/g' .env
    sed -i 's/DB_PASSWORD=.*/DB_PASSWORD=secret/g' .env
fi

echo "Attente du démarrage de PostgreSQL..."
php -r "
\$max = 30;
while (\$max-- > 0) {
    try {
        \$db = new PDO('pgsql:host=db;port=5432;dbname=geoval', 'postgres', 'secret');
        echo 'PostgreSQL est prêt !' . PHP_EOL;
        exit(0);
    } catch (Exception \$e) {
        echo 'En attente de PostgreSQL... (' . \$e->getMessage() . ')' . PHP_EOL;
        sleep(2);
    }
}
exit(1);
"

if [ $? -ne 0 ]; then
    echo "Erreur: PostgreSQL n'a pas démarré à temps."
    exit 1
fi

echo "Installation des dépendances Composer..."
composer install --no-interaction --optimize-autoloader

echo "Génération de la clé d'application..."
php artisan key:generate --no-interaction

echo "Exécution des migrations..."
php artisan migrate --force

echo "Vérification des seeds..."
USER_COUNT=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null | tail -n 1)
if [ "$USER_COUNT" = "0" ] || [ -z "$USER_COUNT" ]; then
    echo "Base de données vide, exécution des seeds..."
    php artisan db:seed --force
else
    echo "Des données existent déjà, seeds ignorés."
fi

echo "Démarrage du serveur backend Laravel..."
exec php artisan serve --host=0.0.0.0 --port=8000
