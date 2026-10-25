#!/bin/sh
set -e
cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi

php -r '
$host = getenv("DB_HOST") ?: "mysql";
$port = getenv("DB_PORT") ?: "3306";
$user = getenv("DB_USERNAME") ?: "consapp";
$pass = getenv("DB_PASSWORD") ?: "secret";
$database = getenv("DB_DATABASE") ?: "consapp";
for ($i = 0; $i < 60; $i++) {
    try {
        new PDO("mysql:host={$host};port={$port};dbname={$database}", $user, $pass);
        exit(0);
    } catch (Throwable $e) {
        sleep(1);
    }
}
fwrite(STDERR, "MySQL is not ready\n");
exit(1);
'

php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction
exec php artisan serve --host=0.0.0.0 --port=8000
