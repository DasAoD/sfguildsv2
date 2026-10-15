<?php
/**
 * Impressum (Inhalt aus Admin-Bereich)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/template.php';
require_once __DIR__ . '/../includes/site_settings.php';

if (!legalPageActive('imprint')) {
    http_response_code(404);
    readfile(__DIR__ . '/errors/404.html');
    exit;
}
$s = getLegalSettings();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <?php renderHead('Impressum'); ?>
</head>
<body>
    <?php renderNavbar(''); ?>
    <div class="container">
        <div class="page-header"><h1>Impressum</h1></div>
        <div class="section">
            <h2 class="section-title">Angaben gemäß § 5 DDG</h2>
            <p>
                <?php echo e($s['imprint_name']); ?><br>
                <?php echo e($s['imprint_street']); ?><br>
                <?php echo e($s['imprint_city']); ?>
            </p>
            <h2 class="section-title">Kontakt</h2>
            <p>
                <?php if ($s['imprint_phone'] !== ''): ?>Telefon: <?php echo e($s['imprint_phone']); ?><br><?php endif; ?>
                E-Mail: <?php echo e($s['imprint_email']); ?>
            </p>
        </div>
    </div>
    <?php renderFooter(); ?>
</body>
</html>
