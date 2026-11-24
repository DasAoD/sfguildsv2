<?php
/**
 * Credits (Drittquellen und Mitwirkende)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/template.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <?php renderHead('Credits'); ?>
</head>
<body>
    <?php renderNavbar(''); ?>
    <main class="container">
        <div class="page-header"><h1>Credits</h1></div>
        <div class="section credits">
            <p>Inoffizielles Fanprojekt ohne Verbindung zu Playa Games. Shakes &amp; Fidget und die zugehörigen Grafiken, Namen und die Musik gehören ihren Rechteinhabern.</p>

            <h2 class="section-title">sf-api</h2>
            <p>Alle Daten-Importe (Mitglieder, Charaktere, Kampfberichte) laufen über die Rust-Bibliothek <a href="https://github.com/the-marenga/sf-api" target="_blank" rel="noopener">sf-api</a> von <a href="https://github.com/the-marenga" target="_blank" rel="noopener">the-marenga</a> (MIT-Lizenz). Vielen Dank an Marenga für die Bibliothek und ihre Pflege.</p>

            <h2 class="section-title">Spielmaterial</h2>
            <p>Item-Icons, Treffereffekte, Portrait-Ebenen, das Logo, Wappen-Teile und die Hellevator-Musik stammen aus Shakes &amp; Fidget (Playa Games). Die Hellevator-Bilder stammen aus einer Internetsuche und vermutlich ebenfalls von Playa Games.</p>

            <h2 class="section-title">Sonderportraits</h2>
            <p>Übernommen von <a href="https://sfportrait.12hp.de" target="_blank" rel="noopener">sfportrait.12hp.de</a>. Die Seite bedankt sich dort bei Eggman, EdiGrieg / Gwenno und Resist 2 Exist; bei EdiGrieg / Gwenno verweist sie auf <a href="http://www.trickfilmstimmen.de/avatare.htm" target="_blank" rel="noopener">trickfilmstimmen.de</a>.</p>

            <h2 class="section-title">Schrift</h2>
            <p>Die Gildennamen auf den automatisch erzeugten Wappen nutzen die Schrift Tobi von Rodrigo Araya Salas (<a href="https://www.rodrigotypo.com" target="_blank" rel="noopener">rodrigotypo.com</a>), bezogen über <a href="https://www.onlinewebfonts.com" target="_blank" rel="noopener">onlinewebfonts.com</a>, laut Anbieter unter CC BY 4.0.</p>

            <h2 class="section-title">Mitwirkende</h2>
            <p>Projektidee, Anforderungen und Tests: DasAoD. Code, Architektur, Dokumentation, Fehlerseiten-Illustrationen, Icons und Flaggen: <a href="https://claude.ai" target="_blank" rel="noopener">Claude</a> von <a href="https://anthropic.com" target="_blank" rel="noopener">Anthropic</a>.</p>
        </div>
    </main>
    <?php renderFooter(); ?>
</body>
</html>
