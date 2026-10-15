<?php
/**
 * Datenschutzerklärung (Verantwortlicher aus den Impressums-Angaben)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/template.php';
require_once __DIR__ . '/../includes/site_settings.php';

if (!legalPageActive('privacy')) {
    http_response_code(404);
    readfile(__DIR__ . '/errors/404.html');
    exit;
}
$s = getLegalSettings();
$hasContact = $s['imprint_name'] !== '' && $s['imprint_email'] !== '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <?php renderHead('Datenschutzerklärung'); ?>
</head>
<body>
    <?php renderNavbar(''); ?>
    <main class="container">
        <div class="page-header"><h1>Datenschutzerklärung</h1></div>
        <div class="section">
            <h2 class="section-title">Verantwortlicher</h2>
            <?php if ($hasContact): ?>
            <p>
                <?php echo e($s['imprint_name']); ?><br>
                <?php if ($s['imprint_street'] !== ''): ?><?php echo e($s['imprint_street']); ?><br><?php endif; ?>
                <?php if ($s['imprint_city'] !== ''): ?><?php echo e($s['imprint_city']); ?><br><?php endif; ?>
                E-Mail: <?php echo e($s['imprint_email']); ?>
            </p>
            <?php else: ?>
            <p>Der Betreiber dieser Seite hat noch keine Kontaktdaten hinterlegt.</p>
            <?php endif; ?>

            <h2 class="section-title">Besuch der Seite</h2>
            <p>Beim Aufruf verarbeitet der Webserver technisch notwendige Daten (IP-Adresse, Zeitpunkt, aufgerufene Seite, Browser-Kennung) in Zugriffsprotokollen. Zweck ist der sichere und stabile Betrieb (Art. 6 Abs. 1 lit. f DSGVO). Die Protokolle werden nach spätestens 15 Tagen gelöscht, nicht mit anderen Daten zusammengeführt und nicht an Dritte weitergegeben. Die Seite wird auf einem Server des Betreibers im privaten Haushalt betrieben; ein externer Hosting-Anbieter ist nicht beteiligt.</p>

            <h2 class="section-title">Cookies</h2>
            <p>Es wird ausschließlich ein technisch notwendiges Sitzungs-Cookie gesetzt, und nur bei der Anmeldung. Es enthält keine Tracking-Informationen und wird nach Ende der Sitzung ungültig. Tracking, Statistik- oder Werbedienste sowie externe Einbindungen kommen nicht zum Einsatz.</p>

            <h2 class="section-title">Benutzerkonten</h2>
            <p>Für angemeldete Benutzer werden Benutzername, ein gehashtes Passwort und die Rolle gespeichert. Die Konten werden vom Betreiber vergeben. Rechtsgrundlage ist Art. 6 Abs. 1 lit. b bzw. f DSGVO. Die Daten werden gelöscht, sobald das Konto gelöscht wird.</p>

            <h2 class="section-title">Spieldaten</h2>
            <p>Die Seite zeigt Daten aus dem Browserspiel Shakes &amp; Fidget, insbesondere Spielernamen, Level, Gildenzugehörigkeit, Online-Zeiten und Kampfergebnisse. Diese Daten sind im Spiel selbst für andere Spieler sichtbar. Es handelt sich um Pseudonyme; Klarnamen werden nicht erhoben. Rechtsgrundlage ist Art. 6 Abs. 1 lit. f DSGVO (Organisation der Gilden). Die Daten werden gespeichert, solange die jeweilige Gilde auf dieser Seite verwaltet wird.</p>

            <h2 class="section-title">Ihre Rechte</h2>
            <p>Sie haben das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung der Verarbeitung, Datenübertragbarkeit und Widerspruch. Außerdem können Sie sich bei einer Datenschutz-Aufsichtsbehörde beschweren. Wenden Sie sich dafür an den oben genannten Verantwortlichen.</p>
        </div>
    </main>
    <?php renderFooter(); ?>
</body>
</html>
