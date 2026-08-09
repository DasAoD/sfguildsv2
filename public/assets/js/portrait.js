/**
 * Charakter-Portrait-Compositor
 *
 * Setzt aus den einzelnen Sprite-Ebenen (Gesicht/Haare/Bart/Augen/...) ein
 * Portrait zusammen, analog zur Logik von sfportrait.12hp.de (deren
 * PortraitMaker-full-1.74.min.js wurde als Referenz für Ebenen-Reihenfolge,
 * Farbgruppen-Sonderfälle und Sprite-Offsets genutzt — die Sprites selbst
 * stammen aber aus den Unity-Asset-Bundles des Spiels, nicht von dort).
 *
 * Alle Grafiken + die Offset-Map liegen lokal im Repo (siehe
 * public/assets/images/portraits/ und public/assets/data/), damit die
 * Portraits nicht von der Verfügbarkeit einer fremden Seite abhängen.
 *
 * Sonder-/Streamer-Portraits (sf-api: `portrait.special_portrait`, laut
 * Kommentar im sf-api-Quellcode "Influencers get a special portrait") sind
 * fertige Einzelbilder statt einer Ebenen-Komposition — die anderen
 * Portrait-Felder sind für diese Charaktere alle 0/bedeutungslos. Die
 * Bild-ID ist der Betrag des (negativen) special_portrait-Werts, z.B.
 * special_portrait: -259 -> special259.webp. Verifiziert gegen
 * sfportrait.12hp.de, deren "Custom avatar"-Galerie #259 als
 * "ZsombeyHD (#259)" labelt, exakt passend zum gespeicherten Wert für
 * diesen Spieler. Die ~490 Bilder sind (anders als die übrigen
 * Portrait-Sprites) nicht aus den Spiel-Assets geripped, sondern von
 * dort übernommen (dortiger Pfad: portraits/special/specialN.webp) und
 * ebenso lokal vendored.
 */

const PORTRAIT_BASE = '/assets/images/portraits';
const PORTRAIT_OFFSET_MAP_URL = '/assets/data/portrait_spriteoffsetmap.json';
const PORTRAIT_MANIFEST_URL = '/assets/data/portrait_manifest.json';
const PORTRAIT_BORDER_URL = `${PORTRAIT_BASE}/frames/portrait_border.webp`;

// sf-api Class-Name -> Gesichts-Grafik-Suffix. Die 9 "Hybrid"-Klassen ohne
// eigenes Gesicht nutzen das einer Basisklasse (Nekromant/Pestdoktor -> Magier,
// Paladin -> Krieger), analog zur Item-Icon-Zuordnung.
const PORTRAIT_CLASS_FACE = {
    Warrior: 'warrior', Mage: 'mage', Scout: 'hunter', Assassin: 'assassin',
    BattleMage: 'warlock', Berserker: 'berserker', DemonHunter: 'demonhunter',
    Druid: 'druid', Bard: 'bard',
    Necromancer: 'mage', Paladin: 'warrior', PlagueDoctor: 'mage',
};

// sf-api Race-Name -> Dateiname-/Ordner-Bestandteil
const PORTRAIT_RACE_KEY = {
    Human: 'human', Elf: 'elf', Dwarf: 'dwarf', Gnome: 'gnome', Orc: 'orc',
    DarkElf: 'darkelf', Goblin: 'goblin', Demon: 'demon',
};

let portraitOffsetMapPromise = null;
function loadPortraitOffsetMap() {
    if (!portraitOffsetMapPromise) {
        portraitOffsetMapPromise = fetch(PORTRAIT_OFFSET_MAP_URL).then(r => r.json());
    }
    return portraitOffsetMapPromise;
}

let portraitManifestPromise = null;
function loadPortraitManifest() {
    if (!portraitManifestPromise) {
        portraitManifestPromise = fetch(PORTRAIT_MANIFEST_URL).then(r => r.json());
    }
    return portraitManifestPromise;
}

function raceGenderKey(race, gender) {
    return gender === 'female' ? `${race}_female` : race;
}

// Die Dateinamen sind nicht über alle Rassen hinweg einheitlich aufgebaut
// (z.B. hat Ork bei den Augenbrauen keine Farbgruppen-Ziffer im Namen, weil
// die Rasse keine Farbvarianten dafür hat; Dämon nutzt bei Haaren ein
// abweichendes "special2"-Namensschema). Deshalb werden pro Ebene mehrere
// Kandidaten-Dateinamen erzeugt und gegen das tatsächliche Datei-Manifest
// geprüft (erster Treffer gewinnt), statt einen Namen starr zu konstruieren.
function portraitSpriteCandidates(part, race, gender, styleIndex, colorGroup) {
    const rg = raceGenderKey(race, gender);
    switch (part) {
        case 'face': return [`face-${rg}_${styleIndex}`];
        case 'hair': return [
            `hair-${rg}_hair_${colorGroup}_${styleIndex}`,
            `hair-${rg}_hair${styleIndex}`,
            `hair-${rg}_special2_${colorGroup}_${styleIndex}`,
        ];
        case 'beard': return [
            `beard-${rg}_beard_${colorGroup}_${styleIndex}`,
            `beard-${rg}_beard${styleIndex}`,
        ];
        case 'brows': return [
            `brows-${rg}_brows_${colorGroup}_${styleIndex}`,
            `brows-${rg}_brows${styleIndex}`,
        ];
        case 'horn': return [
            `horn-${rg}_horn_${colorGroup}_${styleIndex}`,
            `horn-${rg}_horn${styleIndex}`,
        ];
        case 'eyes': return [`eyes-${rg}_eyes${styleIndex}`];
        case 'nose': return [`nose-${rg}_nose${styleIndex}`];
        case 'mouth': return [`mouth-${rg}_mouth${styleIndex}`];
        case 'ears': return [`ears-${rg}_ears${styleIndex}`];
        case 'extra': return [`special-${rg}_special${styleIndex}`];
        default: return [];
    }
}

function resolveExistingSprite(candidates, existingNames) {
    return candidates.find(name => existingNames.has(name)) || null;
}

// Der Offset ist nur für Farbgruppe "1" hinterlegt (Farbe ändert nur die
// Einfärbung, nicht die Form/Position) — bei Bart/Augenbrauen/Haaren/Hörnern
// wird die Farbgruppe für den Lookup daher immer auf 1 gesetzt.
function portraitOffsetKeyCandidates(part, race, gender, styleIndex) {
    if (part === 'beard' || part === 'brows' || part === 'hair' || part === 'horn') {
        return portraitSpriteCandidates(part, race, gender, styleIndex, 1);
    }
    return portraitSpriteCandidates(part, race, gender, styleIndex);
}

// Ebenen-Reihenfolge (hinten -> vorne) je Rasse/Geschlecht. 1:1 aus
// sfportrait.12hp.de's Kompositions-Logik übernommen.
function buildPortraitLayerOrder(race, gender, layers) {
    const { face, hair, horn, brows, ears, eyes, nose, mouth, beard, extra } = layers;
    const isDemonFemale = race === 'demon' && gender === 'female';

    if (race === 'goblin' || (gender === 'female' && race === 'dwarf')) {
        return [
            face, eyes, gender === 'male' ? hair : null, ears, extra, brows,
            gender === 'male' ? beard : null, mouth, race === 'dwarf' ? nose : null,
            gender === 'female' ? hair : null, race === 'goblin' ? nose : null,
        ];
    }
    if (gender === 'female' && race === 'elf') {
        return [face, extra, nose, mouth, brows, hair, eyes, ears];
    }
    if (gender === 'female') {
        return [
            face, ears, extra, nose, eyes, isDemonFemale ? beard : null, mouth,
            brows, isDemonFemale ? horn : null, hair,
        ];
    }
    if (race === 'demon') {
        return [face, eyes, nose, brows, mouth, beard, extra, ears, horn];
    }
    if (race === 'darkelf') {
        return [face, eyes, mouth, beard, nose, extra, brows, hair, ears];
    }
    // human, dwarf (male), gnome, orc, elf (male)
    const beardHairSwap = race === 'elf' || race === 'dwarf';
    return [
        face, eyes, mouth, extra, brows, ears,
        beardHairSwap ? beard : hair, beardHairSwap ? hair : beard, nose,
    ];
}

/**
 * @param {object} portrait sf-api Portrait-Struct wie in char_data_json
 *   gespeichert (gender, hair_color, hair, mouth, brows, eyes, beards,
 *   nose, ears, extra, horns, special_portrait)
 * @param {string} raceName sf-api Race-Name (z.B. "DarkElf")
 * @param {string} className sf-api Class-Name (z.B. "BattleMage")
 * @returns {{part:string, styleIndex:number, colorGroup:?number}[]}
 */
function resolvePortraitParts(portrait, raceName, className) {
    const race = PORTRAIT_RACE_KEY[raceName];
    const gender = portrait.gender === 'Female' ? 'female' : 'male';
    if (!race) return null;

    const faceSuffix = PORTRAIT_CLASS_FACE[className] || 'warrior';
    const isDemon = race === 'demon';
    const isDemonFemale = isDemon && gender === 'female';
    const isDemonMale = isDemon && gender === 'male';

    // Bart-Farbgruppe: bei Dämonen an die Hornfarbe gekoppelt (die uns nicht
    // vorliegt, siehe unten), sonst an die Haarfarbe.
    const beardColorGroup = isDemonFemale ? 1 : (isDemonMale ? 1 : (portrait.hair_color || 1));
    // Augenbrauen-Farbe ist bei Dämon/Ork/Goblin sowie weiblichen Dunkelelfen fix.
    const browsColorGroup = (isDemon || race === 'orc' || race === 'goblin' || (gender === 'female' && race === 'darkelf'))
        ? 1 : (portrait.hair_color || 1);
    // sf-api liefert keine eigene Hornfarbe (nur den kombinierten Rohwert,
    // aus dem nur der Stil-Anteil öffentlich zugänglich ist) — Farbgruppe 1
    // als bestmögliche Annäherung.
    const hornColorGroup = 1;

    const parts = {
        face: { part: 'face', race, gender, styleIndex: faceSuffix, colorGroup: null },
        hair: (portrait.hair > 0 && portrait.hair_color > 0)
            ? { part: 'hair', race, gender, styleIndex: portrait.hair, colorGroup: portrait.hair_color } : null,
        beard: (portrait.beards > 0)
            ? { part: 'beard', race, gender, styleIndex: portrait.beards, colorGroup: beardColorGroup } : null,
        brows: (portrait.brows > 0)
            ? { part: 'brows', race, gender, styleIndex: portrait.brows, colorGroup: browsColorGroup } : null,
        horn: (isDemon && portrait.horns > 0)
            ? { part: 'horn', race, gender, styleIndex: portrait.horns, colorGroup: hornColorGroup } : null,
        eyes: (portrait.eyes > 0) ? { part: 'eyes', race, gender, styleIndex: portrait.eyes, colorGroup: null } : null,
        nose: (portrait.nose > 0) ? { part: 'nose', race, gender, styleIndex: portrait.nose, colorGroup: null } : null,
        mouth: (portrait.mouth > 0) ? { part: 'mouth', race, gender, styleIndex: portrait.mouth, colorGroup: null } : null,
        ears: (portrait.ears > 0) ? { part: 'ears', race, gender, styleIndex: portrait.ears, colorGroup: null } : null,
        extra: (portrait.extra > 0) ? { part: 'extra', race, gender, styleIndex: portrait.extra, colorGroup: null } : null,
    };

    return buildPortraitLayerOrder(race, gender, parts).filter(p => p);
}

function loadPortraitImage(src) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => reject(new Error(`Portrait-Bild fehlt: ${src}`));
        img.src = src;
    });
}

/**
 * Zeichnet ein Sonder-/Streamer-Portrait (fertiges Einzelbild statt
 * Ebenen-Komposition) auf den bereits geleerten Canvas-Context.
 * @param {CanvasRenderingContext2D} ctx
 * @param {number} w Canvas-Breite
 * @param {number} h Canvas-Höhe
 * @param {number} specialPortraitId roher sf-api-Wert (negativ = Sonder-ID)
 * @returns {Promise<boolean>}
 */
async function renderSpecialPortrait(ctx, w, h, specialPortraitId) {
    const id = Math.abs(specialPortraitId);
    let img;
    try {
        img = await loadPortraitImage(`${PORTRAIT_BASE}/special/special${id}.webp`);
    } catch (e) {
        // Sonderportrait (noch) nicht lokal vorhanden — sauber auf den
        // Buchstaben-Platzhalter zurückfallen statt einen Fehler zu zeigen.
        console.warn(e.message);
        return false;
    }

    // Die Bilder sind bereits nahezu quadratisch und rahmenlos (reines
    // Charakter-Artwork) — mit kleinem Rand einpassen, damit die Zeichnung
    // den goldenen Rahmen nicht überdeckt.
    const inset = Math.round(Math.min(w, h) * 0.015);
    const size = Math.min(w, h) - inset * 2;
    ctx.drawImage(img, (w - size) / 2, (h - size) / 2, size, size);

    try {
        const border = await loadPortraitImage(PORTRAIT_BORDER_URL);
        ctx.drawImage(border, 0, 0, w, h);
    } catch (e) {
        console.warn(e.message);
    }

    return true;
}

/**
 * Zeichnet das Portrait eines Charakters auf einen Canvas.
 * @param {HTMLCanvasElement} canvas
 * @param {object} data char_data_json-Objekt mit race/class/portrait
 * @returns {Promise<boolean>} true bei Erfolg, false wenn nicht darstellbar
 *   (z.B. Sonderportrait ohne lokal vorhandenes Bild, oder fehlende Daten)
 */
async function renderCharacterPortrait(canvas, data) {
    const portrait = data?.portrait;
    if (!portrait) return false;

    const ctx = canvas.getContext('2d');
    const w = canvas.width;
    const h = canvas.height;
    ctx.clearRect(0, 0, w, h);

    if (portrait.special_portrait && portrait.special_portrait !== 0) {
        return renderSpecialPortrait(ctx, w, h, portrait.special_portrait);
    }

    if (!data.race || !data.class) return false;

    const race = PORTRAIT_RACE_KEY[data.race];
    if (!race) return false;
    const gender = portrait.gender === 'Female' ? 'female' : 'male';

    const parts = resolvePortraitParts(portrait, data.race, data.class);
    if (!parts || parts.length === 0) return false;

    let offsetMap, manifest;
    try {
        [offsetMap, manifest] = await Promise.all([loadPortraitOffsetMap(), loadPortraitManifest()]);
    } catch (e) {
        console.error(e);
        offsetMap = {};
        manifest = {};
    }

    const bundle = `${race}${gender}sprites_sd`;
    const existingNames = new Set(manifest[bundle] || []);

    const loaded = [];
    for (const p of parts) {
        const spriteName = resolveExistingSprite(
            portraitSpriteCandidates(p.part, p.race, p.gender, p.styleIndex, p.colorGroup),
            existingNames
        );
        if (!spriteName) continue; // Ebene in dieser Rasse/Geschlecht-Kombination nicht vorhanden
        const offsetKey = portraitOffsetKeyCandidates(p.part, p.race, p.gender, p.styleIndex)
            .find(k => offsetMap?.[p.gender]?.[p.race]?.[k]);
        const offset = (offsetKey && offsetMap[p.gender][p.race][offsetKey]) || { offsetX: 0, offsetY: 0 };
        try {
            const img = await loadPortraitImage(`${PORTRAIT_BASE}/${bundle}/${spriteName}.png`);
            loaded.push({ img, offset });
        } catch (e) {
            // Einzelne fehlende Ebene soll den Rest des Portraits nicht verhindern.
            console.warn(e.message);
        }
    }

    if (loaded.length === 0) return false;

    for (const { img, offset } of loaded) {
        const x = (w - img.width) / 2 + (offset.offsetX || 0);
        const y = (h - img.height) / 2 + (offset.offsetY || 0);
        ctx.drawImage(img, x, y);
    }

    try {
        const border = await loadPortraitImage(PORTRAIT_BORDER_URL);
        ctx.drawImage(border, 0, 0, w, h);
    } catch (e) {
        console.warn(e.message);
    }

    return true;
}
