#!/bin/sh
set -e
cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

# `php artisan serve` starts PHP with a cleaned environment: when .env
# exists, every variable except a short passthrough list is removed so
# each request loads .env itself. Compose settings such as DB_HOST=mysql
# never reach HTTP. The copied .env.example still has DB_HOST=127.0.0.1,
# and the session query then fails with "connection refused".
php <<'PHP'
<?php
$path = '.env';
$contents = file_get_contents($path);
$newline = str_contains($contents, "\r\n") ? "\r\n" : "\n";
$lines = preg_split("/\r\n|\n|\r/", $contents);

foreach ($lines as $i => $line) {
    if (! preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/', $line, $matches)) {
        continue;
    }

    $key = $matches[1];
    $value = getenv($key);

    if ($value === false) {
        continue;
    }

    $lines[$i] = $key.'='.format_env($value);
}

$updated = implode($newline, $lines);
if ($updated !== '' && ! str_ends_with($updated, "\n")) {
    $updated .= $newline;
}

file_put_contents($path, $updated);

function format_env(string $value): string
{
    if (preg_match('/^[A-Za-z0-9_.:\/@+\-]*$/', $value) === 1) {
        return $value;
    }

    return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
}
PHP

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
