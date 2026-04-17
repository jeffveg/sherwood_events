<?php
/**
 * CLI helper: prompts for a password, prints a bcrypt hash.
 * Paste the output into config/config.php as ADMIN_PASSWORD_HASH.
 *
 * Usage:   php scripts/make_password_hash.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

echo "Enter admin password: ";
// Turn off echo if possible (not available on Windows cmd)
if (DIRECTORY_SEPARATOR === '/') {
    system('stty -echo');
}
$pass = trim(fgets(STDIN));
if (DIRECTORY_SEPARATOR === '/') {
    system('stty echo');
    echo "\n";
}

if (strlen($pass) < 10) {
    fwrite(STDERR, "Refusing: password must be at least 10 characters.\n");
    exit(1);
}

$hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);

echo "\nAdd this to config/config.php:\n\n";
echo "const ADMIN_PASSWORD_HASH = '" . $hash . "';\n\n";
