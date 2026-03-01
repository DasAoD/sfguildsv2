<?php
/**
 * CLI: Reset user password
 * Usage: php cli/reset_password.php <username> [password]
 * If no password is given, a random one will be generated.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if ($argc < 2) {
    echo "Usage: php cli/reset_password.php <username> [password]\n";
    exit(1);
}

require_once __DIR__ . '/../config/database.php';

$username = $argv[1];
$password = $argv[2] ?? bin2hex(random_bytes(8));

$db = getDB();

$stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) {
    echo "Fehler: Benutzer '$username' nicht gefunden.\n";
    exit(1);
}

if (strlen($password) < 8) {
    echo "Fehler: Passwort muss mindestens 8 Zeichen lang sein.\n";
    exit(1);
}

$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $db->prepare("
    UPDATE users
    SET password_hash = ?,
        must_change_password = 1,
        updated_at = datetime('now')
    WHERE id = ?
");
$stmt->execute([$hash, $user['id']]);

echo "Passwort für '$username' wurde zurückgesetzt.\n";
echo "Temporäres Passwort: $password\n";
echo "Benutzer muss beim nächsten Login ein neues Passwort setzen.\n";
