<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Services\Database;

$email = mb_strtolower(trim($argv[1] ?? ''));
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "Usage : php bin/create-admin.php adresse@email\n");
    exit(1);
}

fwrite(STDOUT, 'Mot de passe (12 caractères minimum) : ');
shell_exec('stty -echo');
$password = trim((string) fgets(STDIN));
shell_exec('stty echo');
fwrite(STDOUT, "\n");

if (mb_strlen($password) < 12) {
    fwrite(STDERR, "Mot de passe trop court.\n");
    exit(1);
}

Database::run(
    'INSERT INTO admin_users (email, password_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)',
    [$email, password_hash($password, PASSWORD_DEFAULT)]
);
echo "Compte administrateur prêt : $email\n";
