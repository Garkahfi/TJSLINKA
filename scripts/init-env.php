<?php

declare(strict_types=1);

$envPath = dirname(__DIR__).DIRECTORY_SEPARATOR.'.env';

if (is_file($envPath)) {
    exit(0);
}

$content = <<<'ENV'
APP_NAME=TJSLINKA
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=sqlite
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
MAIL_MAILER=log
ENV;

if (file_put_contents($envPath, $content.PHP_EOL, LOCK_EX) === false) {
    fwrite(STDERR, "Gagal membuat file .env lokal.\n");
    exit(1);
}
