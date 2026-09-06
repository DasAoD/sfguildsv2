<?php
/**
 * sfguildsv2 – Deploy-Webhook-Receiver (Forgejo "Push"-Webhook auf main)
 *
 * VORLAGE. Ziel auf dem Server:  /var/www/sfguilds-deploy/webhook.php
 * Liegt BEWUSST ausserhalb von /var/www/sfguildsv2/ – sonst wuerde das
 * `git clean -fd` im Deploy-Script diese Datei loeschen.
 *
 * Ablauf:
 *   Forgejo Push-Webhook
 *     -> HMAC-SHA256(Body, Shared Secret) == X-Forgejo-Signature ?
 *     -> payload.ref == "refs/heads/main" ?
 *     -> schreibt die Trigger-Datei; eine systemd .path-Unit startet daraufhin
 *        sfguildsv2-deploy.service -> /usr/local/bin/deploy-sfguilds.sh
 *
 * Bewusst KEIN sudo / shell_exec: der PHP-Prozess fasst nichts Privilegiertes
 * an, er beruehrt nur eine Datei. Rechtetrennung uebernimmt systemd.
 *
 * Einrichtung: siehe install/WEBHOOK-DEPLOY.md
 */

declare(strict_types=1);

const SECRET_FILE   = '/var/www/sfguilds-deploy/.webhook-secret';
const TRIGGER_FILE  = '/var/www/sfguilds-deploy/deploy.trigger';
const WEBHOOK_LOG   = '/var/log/sfguilds-webhook.log';
const DEPLOY_REF    = 'refs/heads/main';

function respond(int $code, string $msg): never
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg . "\n";
    exit;
}

function wlog(string $line): void
{
    @file_put_contents(WEBHOOK_LOG, '[' . date('c') . '] ' . $line . "\n", FILE_APPEND);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, 'method not allowed');
}

$secret = @file_get_contents(SECRET_FILE);
if ($secret === false || trim((string)$secret) === '') {
    wlog('FEHLER: Secret-Datei fehlt oder leer: ' . SECRET_FILE);
    respond(500, 'server misconfigured');
}
$secret = trim((string)$secret);

$body = file_get_contents('php://input');
if ($body === false) {
    respond(400, 'no body');
}

// Forgejo: X-Forgejo-Signature / X-Gitea-Signature = hex(HMAC-SHA256).
// GitHub-Stil (falls mal umgestellt): X-Hub-Signature-256 = "sha256=<hex>".
$sig = $_SERVER['HTTP_X_FORGEJO_SIGNATURE']
    ?? $_SERVER['HTTP_X_GITEA_SIGNATURE']
    ?? $_SERVER['HTTP_X_HUB_SIGNATURE_256']
    ?? '';
$sig = preg_replace('/^sha256=/', '', trim($sig)) ?? '';

$expected = hash_hmac('sha256', $body, $secret);
if ($sig === '' || !hash_equals($expected, $sig)) {
    wlog('403: Signatur ungueltig');
    respond(403, 'bad signature');
}

try {
    $payload = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    respond(400, 'invalid json');
}

$ref = (string)($payload['ref'] ?? '');
if ($ref !== DEPLOY_REF) {
    wlog('ignoriert: ref=' . ($ref !== '' ? $ref : '(leer)'));
    respond(200, 'ignored (not ' . DEPLOY_REF . ')');
}

$after = (string)($payload['after'] ?? ($payload['head_commit']['id'] ?? ''));
$mark  = date('c') . ' ' . ($after !== '' ? $after : 'unknown') . "\n";

if (@file_put_contents(TRIGGER_FILE, $mark, LOCK_EX) === false) {
    wlog('FEHLER: Trigger-Datei nicht schreibbar: ' . TRIGGER_FILE);
    respond(500, 'cannot write trigger');
}

wlog('Deploy getriggert fuer main' . ($after !== '' ? ' @ ' . substr($after, 0, 12) : ''));
respond(202, 'deploy triggered');
