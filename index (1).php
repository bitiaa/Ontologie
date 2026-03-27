<?php
declare(strict_types=1);
/**
 * GraphWeave — OWL / RDFS Visualizer
 * PHP 8 — fichier unique (page + API AJAX)
 */

// ── Configuration ────────────────────────────────────────────
const MAX_FILE_SIZE  = 10 * 1024 * 1024; // 10 Mo
const ALLOWED_EXT    = ['owl','rdf','rdfs','xml','json','jsonld'];
const ONTO_DIR       = __DIR__ . '/';

// Ontologies préchargées disponibles sur le serveur
$PRELOADED = [
    'geo_usa'  => ['file' => null,                           'label' => 'geo_usa.owl',                 'desc' => 'Géographie USA (défaut)'],
    'bckm'     => ['file' => 'bckmJSON.owl',                 'label' => 'bckmJSON.owl',                'desc' => 'BCKM — JSON-LD'],
    'human'    => ['file' => 'human_2007_09_11.rdfs',        'label' => 'human_2007_09_11.rdfs',       'desc' => 'Humans — RDFS'],
    'wildlife' => ['file' => 'AfricanWildlifeOntology1.owl', 'label' => 'AfricanWildlifeOntology1.owl','desc' => 'African Wildlife — OWL'],
];

// ── Helpers ──────────────────────────────────────────────────
function jsonOk(mixed $data): never {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}
function jsonErr(string $msg): never {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// ── API : chargement ontologie préchargée (GET ?preload=key) ─
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['preload'])) {
    $key = $_GET['preload'];
    if (!array_key_exists($key, $PRELOADED) || $PRELOADED[$key]['file'] === null) {
        jsonErr('Ontologie introuvable.');
    }
    $path = ONTO_DIR . $PRELOADED[$key]['file'];
    if (!is_file($path)) {
        jsonErr('Fichier manquant sur le serveur : ' . htmlspecialchars(basename($path)));
    }
    $content = file_get_contents($path);
    if ($content === false) jsonErr('Impossible de lire le fichier.');
    jsonOk(['success' => true, 'filename' => basename($path), 'content' => $content, 'size' => strlen($content)]);
}

// ── API : upload fichier local (POST multipart) ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['ontology'])) {
    $file = $_FILES['ontology'];
    if ($file['error'] !== UPLOAD_ERR_OK) jsonErr('Erreur upload (code ' . $file['error'] . ').');
    if ($file['size'] > MAX_FILE_SIZE)    jsonErr('Fichier trop grand (max 10 Mo).');
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXT, true)) {
        jsonErr('Extension non autorisée. Formats acceptés : ' . implode(', ', ALLOWED_EXT));
    }
    $content = file_get_contents($file['tmp_name']);
    if ($content === false) jsonErr('Impossible de lire le fichier.');
    jsonOk(['success' => true, 'filename' => $file['name'], 'content' => $content, 'size' => $file['size']]);
}

// ── Page principale ──────────────────────────────────────────
// Injecter la liste des ontologies côté JS
$ontoListJson = json_encode(
    array_map(
        fn(string $k, array $v) => ['key' => $k, 'label' => $v['label'], 'desc' => $v['desc']],
        array_keys($PRELOADED),
        $PRELOADED
    ),
    JSON_THROW_ON_ERROR
);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>GraphWeave — OWL / RDFS Visualizer</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/d3/7.8.5/d3.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@300;400;500&family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,600;0,9..144,900;1,9..144,300&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#f0ede8;
  --surf:#ffffff;
  --surf2:#f7f4ef;
  --bdr:#e2ddd6;
  --bdr2:#ccc8c0;
  --ink:#1a1714;
  --ink2:#5c5751;
  --ink3:#9c978f;
  --acc:#c4410c;
  --acc2:#e85d23;
  --blu:#1d4ed8;
  --grn:#15803d;
  --pur:#7c3aed;
  --yel:#b45309;
  --panel:280px;
  --rad:10px;
}
*{margin:0;padding:0;box-sizing:border-box}
body{
  font-family:'DM Mono',monospace;
  background:var(--bg);
  color:var(--ink);
  height:100vh;
  overflow:hidden;
  display:flex;
  flex-direction:column;
  background-image:
    radial-gradient(circle at 20% 80%,rgba(196,65,12,.04) 0%,transparent 50%),
    radial-gradient(circle at 80% 20%,rgba(29,78,216,.04) 0%,transparent 50%);
}

/* ── header ── */
header{
  display:flex;align-items:center;gap:10px;
  padding:8px 16px;
  background:var(--surf);
  border-bottom:1.5px solid var(--bdr);
  flex-shrink:0;flex-wrap:wrap;
  box-shadow:0 1px 0 rgba(0,0,0,.04);
}
.logo{
  font-family:'Fraunces',serif;font-weight:900;font-size:1.25rem;
  color:var(--ink);letter-spacing:-1px;white-space:nowrap;
  display:flex;align-items:center;gap:6px;
}
.logo-mark{
  width:24px;height:24px;background:var(--acc);border-radius:6px;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.logo-mark svg{width:14px;height:14px;fill:white}
.logo em{color:var(--acc);font-style:normal}
.tbar{display:flex;gap:5px;align-items:center;flex-wrap:wrap;flex:1}
.btn{
  background:transparent;border:1.5px solid var(--bdr);color:var(--ink2);
  padding:4px 11px;border-radius:6px;cursor:pointer;
  font:400 .67rem 'DM Mono',monospace;transition:all .14s;white-space:nowrap;
}
.btn:hover{background:var(--surf2);border-color:var(--bdr2);color:var(--ink)}
.btn.active{background:var(--acc);border-color:var(--acc);color:#fff}
.btn.warn:hover{border-color:var(--acc);color:var(--acc);background:rgba(196,65,12,.06)}
.sep{width:1px;height:20px;background:var(--bdr);flex-shrink:0;margin:0 2px}
input[type=text]{
  background:var(--surf2);border:1.5px solid var(--bdr);color:var(--ink);
  padding:4px 9px;border-radius:6px;font:400 .67rem 'DM Mono',monospace;
}
input[type=text]::placeholder{color:var(--ink3)}
input[type=text]:focus{outline:none;border-color:var(--acc)}
input[type=range]{width:65px;accent-color:var(--acc);cursor:pointer}
.fbl{
  font-size:.62rem;color:var(--grn);
  border:1.5px solid rgba(21,128,61,.25);background:rgba(21,128,61,.06);
  padding:2px 8px;border-radius:20px;max-width:190px;
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
}
.sbar{display:flex;gap:10px;font-size:.59rem;color:var(--ink3)}
.sbar b{color:var(--acc);font-weight:500}

/* ── layout ── */
.main{display:flex;flex:1;overflow:hidden}

/* ── left panel ── */
.panel{
  width:var(--panel);background:var(--surf);border-right:1.5px solid var(--bdr);
  display:flex;flex-direction:column;overflow:hidden;flex-shrink:0;
}
.psec{padding:10px 13px;border-bottom:1px solid var(--bdr)}
.ptit{
  font-family:'Fraunces',serif;font-size:.65rem;font-weight:600;
  color:var(--ink3);text-transform:uppercase;letter-spacing:2px;margin-bottom:8px;
}
.flist{list-style:none;max-height:175px;overflow-y:auto}
.fi{
  display:flex;align-items:center;gap:7px;font-size:.64rem;
  padding:3px 5px;border-radius:5px;cursor:pointer;color:var(--ink2);
}
.fi:hover{background:var(--surf2)}
.fdot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.fcb{accent-color:var(--acc);cursor:pointer}
.ibox{flex:1;overflow-y:auto;padding:12px}
.iname{
  font-family:'Fraunces',serif;font-size:.95rem;font-weight:900;
  color:var(--ink);word-break:break-all;line-height:1.2;margin-bottom:5px;
}
.ibadge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:.59rem;margin-bottom:8px;font-weight:500}
.icmt{
  font-size:.61rem;font-style:italic;color:var(--ink2);line-height:1.6;
  margin-bottom:6px;padding:4px 8px;background:var(--surf2);
  border-radius:6px;border-left:3px solid var(--bdr2);
}
.irow{font-size:.64rem;margin-bottom:4px;padding-bottom:4px;border-bottom:1px solid var(--bdr)}
.ikey{color:var(--ink3);font-size:.58rem;display:block;margin-bottom:1px;text-transform:uppercase;letter-spacing:.5px}
.ival{color:var(--ink);word-break:break-all;line-height:1.5}
.ilink{color:var(--blu);cursor:pointer;text-decoration:underline;text-underline-offset:2px;display:block;line-height:1.8;font-size:.63rem}
.irgrp{margin-bottom:5px}
.irn{display:block;color:var(--acc);font-size:.61rem;margin-bottom:2px;font-weight:500}

/* ── canvas ── */
.carea{flex:1;position:relative;overflow:hidden;background:var(--bg)}
svg.msv{width:100%;height:100%}
.link{fill:none}
.node{cursor:pointer}
.node text{font:400 8px 'DM Mono',monospace;fill:var(--ink);pointer-events:none;text-anchor:middle;dominant-baseline:central}
.node.hi circle,.node.hi ellipse,.node.hi rect,.node.hi polygon{stroke-width:3.5!important}
.node.dim circle,.node.dim ellipse,.node.dim rect,.node.dim polygon{opacity:.12}
.node.dim text{opacity:.12}
.link.hi{stroke-width:3!important;opacity:1!important}
.link.dim{opacity:.05!important}
.radring{fill:none;stroke:var(--bdr);stroke-width:.6}

/* ── canvas grid bg ── */
.carea::before{
  content:'';position:absolute;inset:0;
  background-image:
    linear-gradient(rgba(26,23,20,.04) 1px,transparent 1px),
    linear-gradient(90deg,rgba(26,23,20,.04) 1px,transparent 1px);
  background-size:32px 32px;pointer-events:none;
}

/* ── overlays ── */
.mbadge{
  position:absolute;top:10px;right:10px;
  background:rgba(255,255,255,.9);border:1.5px solid var(--bdr);border-radius:8px;
  padding:4px 11px;font-size:.61rem;color:var(--ink2);
  pointer-events:none;backdrop-filter:blur(8px);box-shadow:0 2px 8px rgba(0,0,0,.08);
}
.mbadge b{color:var(--acc);font-weight:500}
#leg{
  position:absolute;bottom:10px;left:10px;
  background:rgba(255,255,255,.9);border:1.5px solid var(--bdr);border-radius:8px;
  padding:8px 11px;font-size:.59rem;
  pointer-events:none;backdrop-filter:blur(8px);box-shadow:0 2px 8px rgba(0,0,0,.08);
}
.lt{color:var(--ink3);font-size:.55rem;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:5px;font-family:'Fraunces',serif}
.lr{display:flex;align-items:center;gap:6px;margin-bottom:3px}
.ld{width:7px;height:7px;border-radius:50%;flex-shrink:0}
#tip{
  position:absolute;background:rgba(255,255,255,.97);border:1.5px solid var(--bdr);
  border-radius:8px;padding:7px 11px;font-size:.64rem;pointer-events:none;
  opacity:0;transition:opacity .1s;max-width:215px;z-index:200;line-height:1.6;
  box-shadow:0 4px 16px rgba(0,0,0,.1);
}
#bc{position:absolute;top:10px;left:10px;display:flex;gap:4px;align-items:center;flex-wrap:wrap;max-width:55%}
.crumb{
  background:rgba(255,255,255,.9);border:1.5px solid var(--bdr);border-radius:5px;
  padding:2px 7px;font-size:.59rem;cursor:pointer;color:var(--ink2);backdrop-filter:blur(4px);
}
.crumb.cur{color:var(--acc);border-color:rgba(196,65,12,.35);background:rgba(196,65,12,.06)}
.csep{color:var(--ink3);font-size:.7rem}

/* ── path panel ── */
.pi{display:flex;flex-direction:column;gap:5px}
.pi input{width:100%;font-size:.63rem}
#pr{font-size:.62rem;color:var(--ink2);margin-top:5px;line-height:1.7}
#pr .phi{color:var(--acc);font-weight:500}

/* ── drop overlay ── */
#dov{display:none;position:fixed;inset:0;background:rgba(240,237,232,.94);z-index:500;justify-content:center;align-items:center;backdrop-filter:blur(6px)}
#dov.on{display:flex}
.dbox{
  background:var(--surf);border:2px dashed var(--bdr2);border-radius:16px;
  padding:36px 40px;text-align:center;max-width:420px;width:95vw;
  box-shadow:0 8px 40px rgba(0,0,0,.1);
}
.dbox h2{font-family:'Fraunces',serif;color:var(--ink);margin-bottom:12px;font-size:1.05rem;font-weight:900}
.ftags{display:flex;gap:6px;justify-content:center;flex-wrap:wrap;margin-bottom:14px}
.ftag{font-size:.59rem;padding:3px 9px;border-radius:20px;font-weight:500}

/* ── liste ontologies préchargées ── */
.onto-sep{display:flex;align-items:center;gap:8px;margin:14px 0 10px;color:var(--ink3);font-size:.62rem}
.onto-sep::before,.onto-sep::after{content:'';flex:1;height:1px;background:var(--bdr)}
.onto-grid{display:flex;flex-direction:column;gap:5px;max-height:180px;overflow-y:auto;text-align:left}
.onto-card{
  display:flex;align-items:center;gap:10px;
  background:var(--surf2);border:1.5px solid var(--bdr);border-radius:8px;
  padding:7px 11px;cursor:pointer;transition:all .14s;
}
.onto-card:hover{border-color:var(--acc);background:rgba(196,65,12,.04)}
.onto-card-icon{font-size:1rem;flex-shrink:0;line-height:1}
.onto-card-info{flex:1;overflow:hidden}
.onto-card-name{font-size:.65rem;color:var(--ink);font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block}
.onto-card-desc{font-size:.57rem;color:var(--ink3);display:block;margin-top:1px}

/* ── loading overlay ── */
#lov{display:none;position:fixed;inset:0;background:rgba(240,237,232,.8);z-index:600;justify-content:center;align-items:center;backdrop-filter:blur(6px)}
#lov.on{display:flex}
.lbox{
  background:var(--surf);border:1.5px solid var(--bdr);border-radius:12px;
  padding:28px 36px;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,.1);
}
.lbox-title{font-family:'Fraunces',serif;font-size:1rem;font-weight:900;color:var(--ink);margin-bottom:6px}
.lbox-sub{font-size:.68rem;color:var(--ink3)}
.lbox-spinner{
  width:28px;height:28px;margin:0 auto 12px;
  border:3px solid var(--bdr);border-top-color:var(--acc);
  border-radius:50%;animation:spin .7s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg)}}

/* ── empty state ── */
.empty-state{color:var(--ink3);font-size:.68rem;text-align:center;margin-top:50px;line-height:2.5}
.empty-icon{
  width:40px;height:40px;margin:0 auto 10px;
  border:2px solid var(--bdr2);border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  color:var(--bdr2);font-size:1.2rem;
}

::-webkit-scrollbar{width:4px}
::-webkit-scrollbar-track{background:var(--surf2)}
::-webkit-scrollbar-thumb{background:var(--bdr2);border-radius:2px}
</style>
</head>
<body>

<!-- Loading overlay -->
<div id="lov">
  <div class="lbox">
    <div class="lbox-spinner"></div>
    <div class="lbox-title">Chargement…</div>
    <div class="lbox-sub">Traitement de l'ontologie</div>
  </div>
</div>

<!-- Charger overlay -->
<div id="dov">
  <div class="dbox">
    <h2>Charger une ontologie</h2>

    <!-- Ontologies préchargées (injectées par PHP) -->
    <div style="text-align:left">
      <div class="ptit" style="margin-bottom:7px">Ontologies disponibles</div>
      <div class="onto-grid" id="ontoGrid"></div>
    </div>

    <div class="onto-sep">ou importer un fichier local</div>

    <div class="ftags">
      <span class="ftag" style="background:rgba(196,65,12,.08);color:var(--acc);border:1.5px solid rgba(196,65,12,.25)">JSON-LD .owl</span>
      <span class="ftag" style="background:rgba(21,128,61,.08);color:var(--grn);border:1.5px solid rgba(21,128,61,.25)">RDF/XML .owl</span>
      <span class="ftag" style="background:rgba(29,78,216,.08);color:var(--blu);border:1.5px solid rgba(29,78,216,.25)">RDFS .rdfs</span>
    </div>
    <input type="file" id="fi" accept=".owl,.rdf,.rdfs,.xml,.json,.jsonld" style="display:none">
    <button class="btn active" onclick="document.getElementById('fi').click()">Choisir un fichier local</button>
    <br><br>
    <button class="btn warn" onclick="hideDov()">Annuler</button>
  </div>
</div>

<header>
  <div class="logo">
    <div class="logo-mark">
      <svg viewBox="0 0 14 14">
        <circle cx="7" cy="3" r="2"/>
        <circle cx="2" cy="11" r="2"/>
        <circle cx="12" cy="11" r="2"/>
        <line x1="7" y1="3" x2="2" y2="11" stroke="white" stroke-width="1.2"/>
        <line x1="7" y1="3" x2="12" y2="11" stroke="white" stroke-width="1.2"/>
        <line x1="2" y1="11" x2="12" y2="11" stroke="white" stroke-width="1.2"/>
      </svg>
    </div>
    Graph<em>Weave</em>
  </div>
  <div class="tbar">
    <button class="btn active" id="bForce"  onclick="setMode('force')">⬡ Force</button>
    <button class="btn"        id="bRadial" onclick="setMode('radial')">◎ Radial</button>
    <button class="btn"        id="bTree"   onclick="setMode('tree')">⊞ Arbre</button>
    <button class="btn"        id="bSlice"  onclick="setMode('slice')">⊙ Coupe</button>
    <div class="sep"></div>
    <input type="text" id="sb" placeholder="Chercher…" oninput="doSearch(this.value)" style="width:125px">
    <div class="sep"></div>
    <span style="font-size:.64rem;color:var(--ink3)">Prof.</span>
    <input type="range" id="dr" min="1" max="6" value="2" oninput="setDepth(+this.value)">
    <span id="dv" style="font-size:.66rem;min-width:10px;color:var(--ink2)">2</span>
    <div class="sep"></div>
    <button class="btn" id="bPath" onclick="togPath()">⇝ Chemin</button>
    <div class="sep"></div>
    <button class="btn" onclick="showDov()">↑ Charger</button>
    <button class="btn" onclick="doExport()">↓ JSON</button>
    <button class="btn warn" onclick="doReset()">↺</button>
    <div class="sep"></div>
    <span class="fbl" id="fbl">geo_usa.owl</span>
    <div class="sbar" id="sbar"></div>
  </div>
</header>

<div class="main">
  <!-- Panel gauche -->
  <div class="panel">
    <div class="psec">
      <div class="ptit">Types de nœuds</div>
      <ul class="flist" id="flist"></ul>
    </div>
    <div class="psec" id="pp" style="display:none">
      <div class="ptit">Chemin</div>
      <div class="pi">
        <input type="text" id="pfrom" placeholder="Source…">
        <input type="text" id="pto"   placeholder="Cible…">
        <button class="btn active" onclick="findPath()">→ Trouver</button>
        <button class="btn warn"   onclick="clearPath()">✕ Effacer</button>
        <div id="pr"></div>
      </div>
    </div>
    <div class="ibox" id="ibox">
      <div class="empty-state">
        <div class="empty-icon">⬡</div>
        Cliquez sur un nœud<br>pour ses détails
      </div>
    </div>
  </div>

  <!-- Canvas -->
  <div class="carea" id="ca">
    <svg class="msv" id="sv"></svg>
    <div class="mbadge"><b id="mn">FORCE DIRIGÉE</b> · <span id="nc">0</span> nœuds</div>
    <div id="leg"></div>
    <div id="bc"></div>
    <div id="tip"></div>
  </div>
</div>

<script>
// ═══════════════════════════════════════════════════════════
// ONTOLOGIES PRÉCHARGÉES (injectées par PHP 8)
// ═══════════════════════════════════════════════════════════
const PRELOADED_ONTOLOGIES = <?= $ontoListJson ?>;

const ONTO_ICONS = {
  geo_usa : '🗺️', bckm : '🧬', human : '🧑', wildlife : '🦁'
};

function buildOntoGrid() {
  const grid = document.getElementById('ontoGrid');
  grid.innerHTML = '';
  for (const onto of PRELOADED_ONTOLOGIES) {
    const card = document.createElement('button');
    card.className = 'onto-card btn';
    card.style.cssText = 'width:100%;text-align:left;border:1.5px solid var(--bdr)';
    card.innerHTML =
      `<span class="onto-card-icon">${ONTO_ICONS[onto.key] || '📄'}</span>` +
      `<span class="onto-card-info">` +
        `<span class="onto-card-name">${onto.label}</span>` +
        `<span class="onto-card-desc">${onto.desc}</span>` +
      `</span>`;
    card.addEventListener('click', () => { hideDov(); loadPreloaded(onto.key); });
    grid.appendChild(card);
  }
}

// ── Chargement préchargé via PHP (GET) ──────────────────────
function loadPreloaded(key) {
  if (key === 'geo_usa') { loadOnto(DEFAULT_OWL, 'geo_usa.owl'); return; }
  showLoader();
  fetch(`graphweave.php?preload=${encodeURIComponent(key)}`)
    .then(r => r.json())
    .then(data => {
      hideLoader();
      if (!data.success) { alert('Erreur serveur : ' + data.error); return; }
      try { loadOnto(data.content, data.filename); }
      catch (err) { alert('Erreur de parsing :\n' + err.message); console.error(err); }
    })
    .catch(err => { hideLoader(); alert('Erreur réseau : ' + err.message); });
}

// ── Upload fichier local via PHP (POST) ─────────────────────
function uploadFile(file) {
  const fd = new FormData();
  fd.append('ontology', file);
  showLoader();
  fetch('graphweave.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      hideLoader();
      if (!data.success) { alert('Erreur serveur : ' + data.error); return; }
      try { loadOnto(data.content, data.filename); }
      catch (err) { alert('Erreur de parsing :\n' + err.message); console.error(err); }
    })
    .catch(err => { hideLoader(); alert('Erreur réseau : ' + err.message); });
}

function showLoader() { document.getElementById('lov').classList.add('on'); }
function hideLoader() { document.getElementById('lov').classList.remove('on'); }

// ═══════════════════════════════════════════════════════════
// UTILITIES
// ═══════════════════════════════════════════════════════════
const RDF_NS  = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
const RDFS_NS = 'http://www.w3.org/2000/01/rdf-schema#';
const OWL_NS  = 'http://www.w3.org/2002/07/owl#';
const XML_NS  = 'http://www.w3.org/XML/1998/namespace';

function lname(uri){
  if(!uri) return '';
  const h=uri.lastIndexOf('#'), s=uri.lastIndexOf('/');
  return uri.substring(Math.max(h,s)+1)||uri;
}
function pickLabel(arr){
  if(!arr||!arr.length) return null;
  const en=arr.find(c=>c&&typeof c==='object'&&c['@language']==='en');
  if(en) return en['@value']||null;
  const fr=arr.find(c=>c&&typeof c==='object'&&c['@language']==='fr');
  if(fr) return fr['@value']||null;
  const f=arr[0];
  if(typeof f==='string') return f;
  return f['@value']||f['@id']||null;
}
function xmlAttr(el,ns,local){
  return el.getAttributeNS(ns,local)||el.getAttribute(local)||null;
}
function xmlId(el){
  return xmlAttr(el,RDF_NS,'about')||xmlAttr(el,RDF_NS,'ID')||null;
}
function xmlRes(el){
  return xmlAttr(el,RDF_NS,'resource')||null;
}
function xmlLabel(el,nsLabel){
  const lblEls=el.getElementsByTagNameNS(nsLabel,'label');
  let fallback=null;
  for(const l of lblEls){
    const lang=l.getAttributeNS(XML_NS,'lang')||l.getAttribute('xml:lang')||'';
    if(lang==='en') return l.textContent.trim();
    if(!fallback) fallback=l.textContent.trim();
  }
  return fallback;
}
function xmlComment(el,nsLabel){
  const els=el.getElementsByTagNameNS(nsLabel,'comment');
  let fb=null;
  for(const c of els){
    const lang=c.getAttributeNS(XML_NS,'lang')||c.getAttribute('xml:lang')||'';
    if(lang==='en') return c.textContent.trim();
    if(!fb) fb=c.textContent.trim();
  }
  return fb;
}
function e(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function ea(s){return String(s||'').replace(/\\/g,'\\\\').replace(/'/g,"\\'");}

// ═══════════════════════════════════════════════════════════
// PARSER 1 — JSON-LD EXPANDED
// ═══════════════════════════════════════════════════════════
function parseJSONLD(text){
  let arr;
  try{ arr=JSON.parse(text); }
  catch(ex){ throw new Error('JSON-LD invalide : '+ex.message); }
  if(!Array.isArray(arr)) arr=arr['@graph']?arr['@graph']:[arr];

  const BCKM='http://desiree-project.eu/bckm#';
  const nodes=new Map(), edges=[];

  const restr=new Map();
  for(const item of arr){
    const id=item['@id']||'';
    if(id.startsWith('_:')&&(item['@type']||[]).includes(OWL_NS+'Restriction')){
      const onP=((item[OWL_NS+'onProperty']||[])[0]||{})['@id'];
      const svf=((item[OWL_NS+'someValuesFrom']||item[OWL_NS+'allValuesFrom']||[])[0]||{})['@id'];
      if(onP&&svf) restr.set(id,{prop:lname(onP),target:lname(svf)});
    }
  }

  const TYPE_MAP={
    [OWL_NS+'Class']:'Class',
    [OWL_NS+'ObjectProperty']:'ObjectProperty',
    [OWL_NS+'DatatypeProperty']:'DatatypeProperty',
    [OWL_NS+'AnnotationProperty']:'AnnotationProperty',
    [OWL_NS+'NamedIndividual']:'Individual'
  };

  function en(id,type,label){
    if(!id||id.startsWith('_:')) return;
    const k=lname(id);
    if(!nodes.has(k)) nodes.set(k,{id:k,fullUri:id,label:label||k,type:'Unknown',comment:'',props:{}});
    const n=nodes.get(k);
    if(type&&n.type==='Unknown') n.type=type;
    if(label&&n.label===k) n.label=label;
  }

  for(const item of arr){
    const id=item['@id']||''; if(id.startsWith('_:')) continue;
    const types=item['@type']||[];
    let ntype='Unknown';
    for(const t of types){ if(TYPE_MAP[t]){ntype=TYPE_MAP[t];break;} }
    if(ntype==='Unknown') continue;

    const lbl=pickLabel(item[RDFS_NS+'label'])||pickLabel(item[BCKM+'NCI_label'])||lname(id);
    const cmt=pickLabel(item[RDFS_NS+'comment'])||pickLabel(item[BCKM+'NCI_DEFINITION'])||'';

    en(id,ntype,lbl);
    const node=nodes.get(lname(id));
    if(node){
      node.comment=cmt||'';
      const code=pickLabel(item[BCKM+'NCI_code']);
      if(code) node.props['NCI Code']=code;
    }

    for(const sc of (item[RDFS_NS+'subClassOf']||[])){
      const tid=(sc['@id']||'');
      if(tid.startsWith('_:')&&restr.has(tid)){
        const r=restr.get(tid);
        en(r.target,'Class',r.target);
        edges.push({source:lname(id),target:r.target,rel:r.prop,type:'restriction'});
      } else if(tid&&!tid.startsWith('_:')){
        en(tid,'Class',lname(tid));
        edges.push({source:lname(id),target:lname(tid),rel:'subClassOf',type:'subclass'});
      }
    }
    for(const ep of (item[OWL_NS+'equivalentClass']||[])){
      const tid=(ep['@id']||''); if(!tid||tid.startsWith('_:')) continue;
      en(tid,'Class',lname(tid));
      edges.push({source:lname(id),target:lname(tid),rel:'equivalentClass',type:'equiv'});
    }
    for(const inv of (item[OWL_NS+'inverseOf']||[])){
      const tid=(inv['@id']||''); if(!tid) continue;
      en(tid,ntype,lname(tid));
      edges.push({source:lname(id),target:lname(tid),rel:'inverseOf',type:'inverse'});
    }
    for(const sp of (item[RDFS_NS+'subPropertyOf']||[])){
      const tid=(sp['@id']||''); if(!tid||tid.startsWith('_:')) continue;
      en(tid,ntype,lname(tid));
      edges.push({source:lname(id),target:lname(tid),rel:'subPropertyOf',type:'subprop'});
    }
    for(const d of (item[RDFS_NS+'domain']||[])){
      const tid=(d['@id']||''); if(!tid||tid.startsWith('_:')) continue;
      en(tid,'Class',lname(tid));
      edges.push({source:lname(id),target:lname(tid),rel:'domain',type:'propdef'});
    }
    for(const r of (item[RDFS_NS+'range']||[])){
      const tid=(r['@id']||''); if(!tid||tid.startsWith('_:')) continue;
      en(tid,'Class',lname(tid));
      edges.push({source:lname(id),target:lname(tid),rel:'range',type:'propdef'});
    }
    for(const t of (item[RDF_NS+'type']||[])){
      const tid=(t['@id']||''); if(!tid||tid.startsWith('_:')) continue;
      if(!Object.values(TYPE_MAP).includes(lname(tid))){
        en(tid,'Class',lname(tid));
        edges.push({source:lname(id),target:lname(tid),rel:'type',type:'instance'});
      }
    }
  }
  return {nodes:[...nodes.values()],edges};
}

// ═══════════════════════════════════════════════════════════
// PARSER 2 — RDF/XML (.owl)
// ═══════════════════════════════════════════════════════════
function parseRDFXML(text){
  const doc=new DOMParser().parseFromString(text,'text/xml');
  const nodes=new Map(), edges=[];

  function en(rawId,type,label){
    const k=lname(rawId);
    if(!k) return '';
    if(!nodes.has(k)) nodes.set(k,{id:k,fullUri:rawId,label:label||k,type:'Unknown',comment:'',props:{}});
    const n=nodes.get(k);
    if(type&&n.type==='Unknown') n.type=type;
    if(label&&n.label===k) n.label=label;
    return k;
  }

  const typeMap={
    'Class':'Class','ObjectProperty':'ObjectProperty',
    'DatatypeProperty':'DatatypeProperty','AnnotationProperty':'AnnotationProperty',
    'NamedIndividual':'Individual'
  };

  for(const [owlTag,ntype] of Object.entries(typeMap)){
    for(const el of doc.getElementsByTagNameNS(OWL_NS,owlTag)){
      const id=xmlId(el); if(!id) continue;
      const lbl=xmlLabel(el,RDFS_NS)||lname(id);
      const cmt=xmlComment(el,RDFS_NS)||'';
      const k=en(id,ntype,lbl);
      if(nodes.has(k)) nodes.get(k).comment=cmt;

      for(const sc of el.getElementsByTagNameNS(RDFS_NS,'subClassOf')){
        const res=xmlRes(sc);
        if(res){ en(res,'Class',lname(res)); edges.push({source:k,target:lname(res),rel:'subClassOf',type:'subclass'}); }
      }
      for(const ep of el.getElementsByTagNameNS(OWL_NS,'equivalentClass')){
        const res=xmlRes(ep);
        if(res){ en(res,'Class',lname(res)); edges.push({source:k,target:lname(res),rel:'equivalentClass',type:'equiv'}); }
      }
      for(const sp of el.getElementsByTagNameNS(RDFS_NS,'subPropertyOf')){
        const res=xmlRes(sp);
        if(res){ en(res,ntype,lname(res)); edges.push({source:k,target:lname(res),rel:'subPropertyOf',type:'subprop'}); }
      }
      for(const d of el.getElementsByTagNameNS(RDFS_NS,'domain')){
        const res=xmlRes(d);
        if(res){ en(res,'Class',lname(res)); edges.push({source:k,target:lname(res),rel:'domain',type:'propdef'}); }
      }
      for(const r of el.getElementsByTagNameNS(RDFS_NS,'range')){
        const res=xmlRes(r);
        if(res){ en(res,'Class',lname(res)); edges.push({source:k,target:lname(res),rel:'range',type:'propdef'}); }
      }
      for(const inv of el.getElementsByTagNameNS(OWL_NS,'inverseOf')){
        const res=xmlRes(inv);
        if(res){ en(res,ntype,lname(res)); edges.push({source:k,target:lname(res),rel:'inverseOf',type:'inverse'}); }
      }
    }
  }

  for(const el of doc.getElementsByTagNameNS(RDF_NS,'Description')){
    const id=xmlId(el); if(!id) continue;
    for(const t of el.getElementsByTagNameNS(RDF_NS,'type')){
      const res=xmlRes(t); if(!res) continue;
      const tln=lname(res);
      if(typeMap[tln]){ en(id,typeMap[tln],xmlLabel(el,RDFS_NS)||lname(id)); }
    }
    for(const sc of el.getElementsByTagNameNS(RDFS_NS,'subClassOf')){
      const res=xmlRes(sc); if(!res) continue;
      en(id,'Class',lname(id)); en(res,'Class',lname(res));
      edges.push({source:lname(id),target:lname(res),rel:'subClassOf',type:'subclass'});
    }
  }
  return {nodes:[...nodes.values()],edges};
}

// ═══════════════════════════════════════════════════════════
// PARSER 3 — RDFS/XML
// ═══════════════════════════════════════════════════════════
function parseRDFSXML(text){
  const entMap={};
  text.replace(/<!ENTITY\s+(\w+)\s+"([^"]+)"/g,(_,n,v)=>{ entMap[n]=v; });
  let resolved=text.replace(/&([\w]+);/g,(_,n)=>entMap[n]||'');
  resolved=resolved.replace(/<!DOCTYPE\s[^[]*\[[^\]]*\]\s*>/s,'')
                   .replace(/<!DOCTYPE[^>]*>/g,'');
  const doc=new DOMParser().parseFromString(resolved,'text/xml');
  const nodes=new Map(), edges=[];

  function en(rawId,type,label){
    const k=rawId.replace(/^#/,'');
    if(!nodes.has(k)) nodes.set(k,{id:k,fullUri:rawId,label:label||k,type:'Unknown',comment:'',props:{}});
    const n=nodes.get(k);
    if(type&&n.type==='Unknown') n.type=type;
    if(label&&n.label===k) n.label=label;
    return k;
  }
  function glbl(el){
    const lbls=el.getElementsByTagNameNS(RDFS_NS,'label');
    let fb=null;
    for(const l of lbls){
      const lang=l.getAttributeNS(XML_NS,'lang')||l.getAttribute('xml:lang')||'';
      if(lang==='en') return l.textContent.trim();
      if(!fb) fb=l.textContent.trim();
    }
    return fb;
  }
  function gcmt(el){
    const cs=el.getElementsByTagNameNS(RDFS_NS,'comment');
    let fb=null;
    for(const c of cs){
      const lang=c.getAttributeNS(XML_NS,'lang')||c.getAttribute('xml:lang')||'';
      if(lang==='en') return c.textContent.trim();
      if(!fb) fb=c.textContent.trim();
    }
    return fb;
  }

  const seenC=new Set();
  for(const el of doc.getElementsByTagNameNS(RDFS_NS,'Class')){
    const id=xmlAttr(el,RDF_NS,'ID')||xmlAttr(el,RDF_NS,'about')||null;
    if(!id||seenC.has(id)) continue; seenC.add(id);
    const key=en(id,'Class',glbl(el)||id.replace(/^#/,''));
    nodes.get(key).comment=gcmt(el)||'';
    for(const sc of el.getElementsByTagNameNS(RDFS_NS,'subClassOf')){
      const res=xmlAttr(sc,RDF_NS,'resource');
      if(res){ const tk=en(res,'Class',res.replace(/^#/,'')); edges.push({source:key,target:tk,rel:'subClassOf',type:'subclass'}); }
    }
  }
  const seenP=new Set();
  for(const el of doc.getElementsByTagNameNS(RDF_NS,'Property')){
    const id=xmlAttr(el,RDF_NS,'ID')||xmlAttr(el,RDF_NS,'about')||null;
    if(!id||seenP.has(id)) continue; seenP.add(id);
    const key=en(id,'Property',glbl(el)||id.replace(/^#/,''));
    nodes.get(key).comment=gcmt(el)||'';
    for(const d of el.getElementsByTagNameNS(RDFS_NS,'domain')){
      const r=xmlAttr(d,RDF_NS,'resource'); if(r){const tk=en(r,'Class',r.replace(/^#/,''));edges.push({source:key,target:tk,rel:'domain',type:'propdef'});}
    }
    for(const r of el.getElementsByTagNameNS(RDFS_NS,'range')){
      const res=xmlAttr(r,RDF_NS,'resource'); if(res){const tk=en(res,'Class',res.replace(/^#/,''));edges.push({source:key,target:tk,rel:'range',type:'propdef'});}
    }
    for(const sp of el.getElementsByTagNameNS(RDFS_NS,'subPropertyOf')){
      const res=xmlAttr(sp,RDF_NS,'resource'); if(res){const tk=en(res,'Property',res.replace(/^#/,''));edges.push({source:key,target:tk,rel:'subPropertyOf',type:'subprop'});}
    }
  }
  return {nodes:[...nodes.values()],edges};
}

// ═══════════════════════════════════════════════════════════
// MASTER PARSER
// ═══════════════════════════════════════════════════════════
function parseAny(text,filename){
  filename=(filename||'').toLowerCase();
  const t=text.trim();
  if(t.startsWith('[')||t.startsWith('{')) return parseJSONLD(text);
  if(filename.endsWith('.rdfs')||
     (text.includes('<Class ')&&text.includes('rdf:Property')&&!text.includes('owl:Class')))
    return parseRDFSXML(text);
  return parseRDFXML(text);
}

function dedup(onto){
  const sig=new Set();
  onto.edges=onto.edges.filter(e=>{
    const k=`${e.source}|${e.target}|${e.rel}`;
    if(sig.has(k)) return false; sig.add(k); return true;
  });
  return onto;
}

// ═══════════════════════════════════════════════════════════
// ONTOLOGIE PAR DÉFAUT (geo USA)
// ═══════════════════════════════════════════════════════════
const DEFAULT_OWL=`<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
         xmlns:owl="http://www.w3.org/2002/07/owl#">
<owl:Class rdf:ID="City"><rdfs:label xml:lang="en">City</rdfs:label><rdfs:comment xml:lang="en">An urban settlement</rdfs:comment></owl:Class>
<owl:Class rdf:ID="Capital"><rdfs:subClassOf rdf:resource="#City"/><rdfs:label xml:lang="en">Capital</rdfs:label><rdfs:comment xml:lang="en">A city serving as state capital</rdfs:comment></owl:Class>
<owl:Class rdf:ID="State"><rdfs:label xml:lang="en">State</rdfs:label></owl:Class>
<owl:Class rdf:ID="River"><rdfs:label xml:lang="en">River</rdfs:label></owl:Class>
<owl:Class rdf:ID="Lake"><rdfs:label xml:lang="en">Lake</rdfs:label></owl:Class>
<owl:Class rdf:ID="Mountain"><rdfs:label xml:lang="en">Mountain</rdfs:label></owl:Class>
<owl:Class rdf:ID="Road"><rdfs:label xml:lang="en">Road</rdfs:label></owl:Class>
<owl:ObjectProperty rdf:ID="borders"><rdfs:domain rdf:resource="#State"/><rdfs:range rdf:resource="#State"/></owl:ObjectProperty>
<owl:ObjectProperty rdf:ID="isCityOf"><rdfs:domain rdf:resource="#City"/><rdfs:range rdf:resource="#State"/></owl:ObjectProperty>
<owl:ObjectProperty rdf:ID="isCapitalOf"><rdfs:subPropertyOf rdf:resource="#isCityOf"/><rdfs:domain rdf:resource="#Capital"/><rdfs:range rdf:resource="#State"/></owl:ObjectProperty>
<owl:ObjectProperty rdf:ID="runsThrough"><rdfs:domain rdf:resource="#River"/><rdfs:range rdf:resource="#State"/></owl:ObjectProperty>
<owl:ObjectProperty rdf:ID="isLakeOf"><rdfs:domain rdf:resource="#Lake"/><rdfs:range rdf:resource="#State"/></owl:ObjectProperty>
<owl:ObjectProperty rdf:ID="isMountainOf"><rdfs:domain rdf:resource="#Mountain"/><rdfs:range rdf:resource="#State"/></owl:ObjectProperty>
<owl:Class rdf:ID="texas"><rdfs:label xml:lang="en">Texas</rdfs:label><rdfs:subClassOf rdf:resource="#State"/></owl:Class>
<owl:Class rdf:ID="california"><rdfs:label xml:lang="en">California</rdfs:label><rdfs:subClassOf rdf:resource="#State"/></owl:Class>
<owl:Class rdf:ID="newYork"><rdfs:label xml:lang="en">New York</rdfs:label><rdfs:subClassOf rdf:resource="#State"/></owl:Class>
<owl:Class rdf:ID="florida"><rdfs:label xml:lang="en">Florida</rdfs:label><rdfs:subClassOf rdf:resource="#State"/></owl:Class>
<owl:Class rdf:ID="illinois"><rdfs:label xml:lang="en">Illinois</rdfs:label><rdfs:subClassOf rdf:resource="#State"/></owl:Class>
<owl:Class rdf:ID="ohio"><rdfs:label xml:lang="en">Ohio</rdfs:label><rdfs:subClassOf rdf:resource="#State"/></owl:Class>
<owl:Class rdf:ID="michigan"><rdfs:label xml:lang="en">Michigan</rdfs:label><rdfs:subClassOf rdf:resource="#State"/></owl:Class>
<owl:Class rdf:ID="georgia"><rdfs:label xml:lang="en">Georgia</rdfs:label><rdfs:subClassOf rdf:resource="#State"/></owl:Class>
<owl:Class rdf:ID="colorado"><rdfs:label xml:lang="en">Colorado</rdfs:label><rdfs:subClassOf rdf:resource="#State"/></owl:Class>
<owl:Class rdf:ID="washington"><rdfs:label xml:lang="en">Washington</rdfs:label><rdfs:subClassOf rdf:resource="#State"/></owl:Class>
<owl:Class rdf:ID="alaska"><rdfs:label xml:lang="en">Alaska</rdfs:label><rdfs:subClassOf rdf:resource="#State"/></owl:Class>
<owl:Class rdf:ID="atlanta"><rdfs:label xml:lang="en">Atlanta</rdfs:label><rdfs:subClassOf rdf:resource="#Capital"/></owl:Class>
<owl:Class rdf:ID="austin"><rdfs:label xml:lang="en">Austin</rdfs:label><rdfs:subClassOf rdf:resource="#Capital"/></owl:Class>
<owl:Class rdf:ID="denver"><rdfs:label xml:lang="en">Denver</rdfs:label><rdfs:subClassOf rdf:resource="#Capital"/></owl:Class>
<owl:Class rdf:ID="olympia"><rdfs:label xml:lang="en">Olympia</rdfs:label><rdfs:subClassOf rdf:resource="#Capital"/></owl:Class>
<owl:Class rdf:ID="springfield"><rdfs:label xml:lang="en">Springfield</rdfs:label><rdfs:subClassOf rdf:resource="#Capital"/></owl:Class>
<owl:Class rdf:ID="mississippiR"><rdfs:label xml:lang="en">Mississippi River</rdfs:label><rdfs:subClassOf rdf:resource="#River"/></owl:Class>
<owl:Class rdf:ID="coloradoR"><rdfs:label xml:lang="en">Colorado River</rdfs:label><rdfs:subClassOf rdf:resource="#River"/></owl:Class>
<owl:Class rdf:ID="ohioR"><rdfs:label xml:lang="en">Ohio River</rdfs:label><rdfs:subClassOf rdf:resource="#River"/></owl:Class>
<owl:Class rdf:ID="superior"><rdfs:label xml:lang="en">Lake Superior</rdfs:label><rdfs:subClassOf rdf:resource="#Lake"/></owl:Class>
<owl:Class rdf:ID="erie"><rdfs:label xml:lang="en">Lake Erie</rdfs:label><rdfs:subClassOf rdf:resource="#Lake"/></owl:Class>
<owl:Class rdf:ID="mckinley"><rdfs:label xml:lang="en">Mt McKinley</rdfs:label><rdfs:subClassOf rdf:resource="#Mountain"/></owl:Class>
<owl:Class rdf:ID="whitney"><rdfs:label xml:lang="en">Mt Whitney</rdfs:label><rdfs:subClassOf rdf:resource="#Mountain"/></owl:Class>
<owl:Class rdf:ID="rainier"><rdfs:label xml:lang="en">Mt Rainier</rdfs:label><rdfs:subClassOf rdf:resource="#Mountain"/></owl:Class>
</rdf:RDF>`;

// ═══════════════════════════════════════════════════════════
// STATE
// ═══════════════════════════════════════════════════════════
let onto=null, mode='force', depth=2;
let selNode=null, pathMode=false;
let hiN=new Set(), hiE=new Set();
let visTypes=new Set(), sim=null, bcStack=[];

const TC={
  Class:'#c4410c', ObjectProperty:'#1d4ed8', DatatypeProperty:'#0369a1',
  AnnotationProperty:'#b45309', Property:'#1d4ed8', Individual:'#7c3aed',
  State:'#15803d', Capital:'#7c3aed', City:'#b45309',
  River:'#0369a1', Mountain:'#9f1239', Lake:'#0e7490', Road:'#64748b',
  Unknown:'#94a3b8'
};
function tc(t){return TC[t]||TC.Unknown;}

// ═══════════════════════════════════════════════════════════
// INIT
// ═══════════════════════════════════════════════════════════
window.addEventListener('DOMContentLoaded',()=>{
  buildOntoGrid();
  loadOnto(DEFAULT_OWL,'geo_usa.owl');

  document.getElementById('fi').addEventListener('change',ev=>{
    const f=ev.target.files[0]; if(f){ uploadFile(f); hideDov(); } ev.target.value='';
  });

  const ca=document.getElementById('ca');
  ca.addEventListener('dragover',ev=>ev.preventDefault());
  ca.addEventListener('drop',ev=>{ ev.preventDefault(); const f=ev.dataTransfer.files[0]; if(f) uploadFile(f); });
});

function loadOnto(text,name){
  onto=dedup(parseAny(text,name));
  visTypes=new Set(onto.nodes.map(n=>n.type));
  selNode=null; bcStack=[]; hiN.clear(); hiE.clear();
  document.getElementById('fbl').textContent=(name||'?').substring(0,30);
  buildFilters(); buildLegend(); buildStats();
  renderMode();
}

// ═══════════════════════════════════════════════════════════
// FILTERS / LEGEND / STATS
// ═══════════════════════════════════════════════════════════
function buildFilters(){
  const types=[...new Set(onto.nodes.map(n=>n.type))].sort();
  const fl=document.getElementById('flist'); fl.innerHTML='';
  for(const t of types){
    const cnt=onto.nodes.filter(n=>n.type===t).length;
    const li=document.createElement('li'); li.className='fi';
    li.innerHTML=`<input type="checkbox" class="fcb" ${visTypes.has(t)?'checked':''}
      onchange="togType('${t}',this.checked)">
      <span class="fdot" style="background:${tc(t)}"></span>
      <span style="flex:1;overflow:hidden;text-overflow:ellipsis">${t}</span>
      <span style="color:var(--ink3)">${cnt}</span>`;
    fl.appendChild(li);
  }
}
function togType(t,on){ on?visTypes.add(t):visTypes.delete(t); renderMode(); }
function buildLegend(){
  const types=[...new Set(onto.nodes.filter(n=>visTypes.has(n.type)).map(n=>n.type))].sort();
  const leg=document.getElementById('leg');
  leg.innerHTML='<div class="lt">Types</div>';
  for(const t of types)
    leg.innerHTML+=`<div class="lr"><div class="ld" style="background:${tc(t)}"></div><span style="color:${tc(t)}">${t}</span></div>`;
}
function buildStats(){
  const sb=document.getElementById('sbar');
  const nc=onto.nodes.length, ec=onto.edges.length;
  const cc=onto.nodes.filter(n=>n.type==='Class').length;
  const pc=onto.nodes.filter(n=>['ObjectProperty','Property','DatatypeProperty'].includes(n.type)).length;
  sb.innerHTML=`<span><b>${nc}</b> nœuds</span>`
    +`<span><b>${ec}</b> arêtes</span>`
    +`<span><b>${cc}</b> classes</span>`
    +`<span><b>${pc}</b> props</span>`;
}

// ═══════════════════════════════════════════════════════════
// MODE SWITCHING
// ═══════════════════════════════════════════════════════════
const MNAMES={force:'FORCE DIRIGÉE',radial:'RADIAL',tree:'ARBRE',slice:'COUPE'};
function setMode(m){
  if(sim){sim.stop();sim=null;}
  mode=m;
  ['Force','Radial','Tree','Slice'].forEach(x=>
    document.getElementById('b'+x).classList.toggle('active',x.toLowerCase()===m));
  document.getElementById('mn').textContent=MNAMES[m];
  document.getElementById('bc').innerHTML='';
  renderMode();
}
function setDepth(v){ depth=v; document.getElementById('dv').textContent=v; if(mode==='radial') renderMode(); }

function renderMode(){
  if(sim){sim.stop();sim=null;}
  const svg=d3.select('#sv'); svg.selectAll('*').remove();

  const defs=svg.append('defs');
  function mkArr(id,col){
    defs.append('marker').attr('id',id).attr('viewBox','0 -4 8 8').attr('refX',20).attr('markerWidth',5).attr('markerHeight',5).attr('orient','auto')
      .append('path').attr('d','M0,-4L8,0L0,4').attr('fill',col);
  }
  mkArr('a0','rgba(26,23,20,.15)');
  mkArr('a-sub','rgba(196,65,12,.5)');
  mkArr('a-prop','rgba(29,78,216,.5)');
  mkArr('a-hi','#c4410c');

  const g=svg.append('g');
  svg.call(d3.zoom().scaleExtent([0.02,12]).on('zoom',ev=>g.attr('transform',ev.transform)));

  const fn=filtNodes();
  document.getElementById('nc').textContent=fn.length;
  buildLegend();

  if(mode==='force')       renderForce(svg,g,fn);
  else if(mode==='radial') renderRadial(svg,g,fn);
  else if(mode==='tree')   renderTree(svg,g,fn);
  else if(mode==='slice')  renderSlice(svg,g);
}

function filtNodes(){ return onto.nodes.filter(n=>visTypes.has(n.type)); }
function filtEdges(ns){ const ids=new Set(ns.map(n=>n.id)); return onto.edges.filter(e=>ids.has(e.source)&&ids.has(e.target)); }

// ═══════════════════════════════════════════════════════════
// FORCE
// ═══════════════════════════════════════════════════════════
function renderForce(svg,g,fn){
  const fe=filtEdges(fn).map(e=>({...e}));
  const fc=fn.map(n=>({...n}));
  const W=svg.node().clientWidth, H=svg.node().clientHeight;

  function eCol(e){
    const hk=e.source+'>'+e.target;
    if(hiE.has(hk)||hiE.has(e.target+'>'+e.source)) return '#c4410c';
    const m={subclass:'rgba(196,65,12,.2)',subprop:'rgba(196,65,12,.14)',propdef:'rgba(29,78,216,.2)',restriction:'rgba(180,83,9,.18)',equiv:'rgba(21,128,61,.2)',inverse:'rgba(124,58,237,.2)',instance:'rgba(3,105,161,.18)'};
    return m[e.type]||'rgba(26,23,20,.08)';
  }
  function eMark(e){
    const hk=e.source+'>'+e.target;
    if(hiE.has(hk)) return 'url(#a-hi)';
    if(e.type==='subclass'||e.type==='subprop') return 'url(#a-sub)';
    if(e.type==='propdef') return 'url(#a-prop)';
    return 'url(#a0)';
  }
  function eDim(e){ return hiE.size>0&&!hiE.has(e.source+'>'+e.target)&&!hiE.has(e.target+'>'+e.source); }

  const lSel=g.append('g').selectAll('line').data(fe).enter().append('line')
    .attr('class',e=>'link'+(eDim(e)?' dim':'')+(hiE.has(e.source+'>'+e.target)||hiE.has(e.target+'>'+e.source)?' hi':''))
    .attr('stroke',eCol).attr('stroke-width',e=>(hiE.has(e.source+'>'+e.target)||hiE.has(e.target+'>'+e.source))?2.5:1)
    .attr('marker-end',eMark);

  const nSel=g.append('g').selectAll('.node').data(fc).enter().append('g')
    .attr('class',d=>'node'+(hiN.size>0&&!hiN.has(d.id)?' dim':'')+(hiN.has(d.id)?' hi':''))
    .call(d3.drag()
      .on('start',(ev,d)=>{ if(!ev.active) sim.alphaTarget(.3).restart(); d.fx=d.x; d.fy=d.y; })
      .on('drag',(ev,d)=>{ d.fx=ev.x; d.fy=ev.y; })
      .on('end',(ev,d)=>{ if(!ev.active) sim.alphaTarget(0); d.fx=null; d.fy=null; }))
    .on('click',(ev,d)=>{ selNode=d; showInfo(d); })
    .on('mouseover',(ev,d)=>showTip(ev,d))
    .on('mouseout',hideTip);

  nSel.each(function(d){ drawShape(d3.select(this),d); });

  sim=d3.forceSimulation(fc)
    .force('link',d3.forceLink(fe).id(d=>d.id).distance(d=>d.type==='subclass'?50:90).strength(.4))
    .force('charge',d3.forceManyBody().strength(-220))
    .force('center',d3.forceCenter(W/2,H/2))
    .force('collide',d3.forceCollide(22))
    .on('tick',()=>{
      lSel.attr('x1',d=>d.source.x).attr('y1',d=>d.source.y).attr('x2',d=>d.target.x).attr('y2',d=>d.target.y);
      nSel.attr('transform',d=>`translate(${d.x},${d.y})`);
    });
}

// ═══════════════════════════════════════════════════════════
// RADIAL
// ═══════════════════════════════════════════════════════════
function renderRadial(svg,g,fn){
  const W=svg.node().clientWidth,H=svg.node().clientHeight;
  const cx=W/2,cy=H/2;
  const root=selNode||fn.find(n=>n.type==='Class')||fn[0];
  if(!root) return;
  updBC(root);

  const {levelMap}=bfsLevels(root.id,depth,fn);
  const maxR=Math.min(W,H)/2-30;
  for(let i=1;i<=depth;i++)
    g.append('circle').attr('class','radring').attr('cx',cx).attr('cy',cy).attr('r',i*maxR/depth);

  const byLvl={};
  for(const [id,lvl] of levelMap) (byLvl[lvl]||(byLvl[lvl]=[])).push(id);
  const pos=new Map([[root.id,{x:cx,y:cy}]]);
  for(let lvl=1;lvl<=depth;lvl++){
    const ids=byLvl[lvl]||[], r=lvl*maxR/depth;
    ids.forEach((id,i)=>{ const a=(2*Math.PI*i/ids.length)-Math.PI/2; pos.set(id,{x:cx+r*Math.cos(a),y:cy+r*Math.sin(a)}); });
  }

  const eMap={subclass:'rgba(196,65,12,.25)',subprop:'rgba(196,65,12,.18)',propdef:'rgba(29,78,216,.2)',restriction:'rgba(180,83,9,.18)',instance:'rgba(3,105,161,.15)'};
  for(const e of filtEdges(fn)){
    const sp=pos.get(e.source),tp=pos.get(e.target); if(!sp||!tp) continue;
    g.append('line').attr('stroke',eMap[e.type]||'rgba(26,23,20,.08)').attr('stroke-width',1)
      .attr('x1',sp.x).attr('y1',sp.y).attr('x2',tp.x).attr('y2',tp.y);
  }
  for(const [id,lvl] of levelMap){
    const nd=onto.nodes.find(n=>n.id===id);
    if(!nd||!visTypes.has(nd.type)) continue;
    const p=pos.get(id); if(!p) continue;
    const col=tc(nd.type), r0=lvl===0?20:Math.max(7,16-lvl*2);
    const nG=g.append('g').attr('class','node').attr('transform',`translate(${p.x},${p.y})`)
      .on('click',()=>{ selNode=nd; bcStack.push(nd); renderMode(); showInfo(nd); })
      .on('mouseover',ev=>showTip(ev,nd)).on('mouseout',hideTip);
    nG.append('circle').attr('r',r0).attr('fill',col+'18').attr('stroke',col)
      .attr('stroke-width',nd.id===root.id?3:1.5);
    nG.append('text').attr('dy','0.35em')
      .style('font-size',lvl===0?'10px':'8px')
      .style('fill',nd.id===root.id?col:TC.Unknown)
      .text(nd.label.substring(0,lvl===0?14:10));
  }
}

// ═══════════════════════════════════════════════════════════
// TREE
// ═══════════════════════════════════════════════════════════
function renderTree(svg,g,fn){
  const W=svg.node().clientWidth,H=svg.node().clientHeight;
  const fnSet=new Set(fn.map(n=>n.id));
  const childSrc=new Set(onto.edges.filter(e=>e.type==='subclass'||e.type==='subprop').map(e=>e.source));
  const roots=fn.filter(n=>!childSrc.has(n.id));

  function buildTree(nid,visited,d){
    if(d>8||visited.has(nid)) return null;
    visited.add(nid);
    const nd=onto.nodes.find(n=>n.id===nid); if(!nd||!fnSet.has(nid)) return null;
    const ch=onto.edges.filter(e=>(e.type==='subclass'||e.type==='subprop')&&e.target===nid&&fnSet.has(e.source))
      .map(e=>buildTree(e.source,new Set(visited),d+1)).filter(Boolean);
    return {id:nid,label:nd.label,_nd:nd,children:ch};
  }
  let tRoots=roots.slice(0,25).map(r=>buildTree(r.id,new Set(),0)).filter(Boolean);
  if(!tRoots.length) tRoots=fn.slice(0,30).map(n=>({id:n.id,label:n.label,_nd:n,children:[]}));

  const rootDat={id:'__top__',label:'TOP',children:tRoots};
  const h=d3.hierarchy(rootDat);
  d3.tree().size([Math.max(W-100,800),Math.max(H-120,500)])(h);
  g.attr('transform','translate(50,55)');

  g.append('g').selectAll('path').data(h.links()).enter().append('path')
    .attr('class','link').attr('stroke','rgba(26,23,20,.1)').attr('stroke-width',1)
    .attr('d',d3.linkVertical().x(d=>d.x).y(d=>d.y));

  g.append('g').selectAll('.node').data(h.descendants()).enter().append('g')
    .attr('class','node').attr('transform',d=>`translate(${d.x},${d.y})`)
    .on('click',(ev,d)=>{ if(d.data._nd){showInfo(d.data._nd);selNode=d.data._nd;} })
    .on('mouseover',(ev,d)=>{ if(d.data._nd) showTip(ev,d.data._nd); }).on('mouseout',hideTip)
    .each(function(d){
      const el=d3.select(this);
      if(d.data.id==='__top__'){
        el.append('rect').attr('x',-18).attr('y',-9).attr('width',36).attr('height',18).attr('rx',4).attr('fill','rgba(26,23,20,.04)').attr('stroke','rgba(26,23,20,.2)').attr('stroke-width',1);
        el.append('text').attr('dy','0.35em').style('font-size','9px').style('fill','#aaa').text('TOP'); return;
      }
      const nd=d.data._nd; if(!nd) return;
      const col=tc(nd.type);
      if(d.children&&d.children.length){
        const w=Math.min(90,nd.label.length*6.2+14);
        el.append('rect').attr('x',-w/2).attr('y',-9).attr('width',w).attr('height',18).attr('rx',4).attr('fill',col+'14').attr('stroke',col).attr('stroke-width',1.5);
        el.append('text').attr('dy','0.35em').style('font-size','8.5px').style('fill',col).text(nd.label.substring(0,13));
      } else {
        el.append('circle').attr('r',5).attr('fill',col+'30').attr('stroke',col).attr('stroke-width',1.2);
        el.append('text').attr('dx',9).attr('dy','0.35em').style('font-size','7.5px').style('fill','#666').text(nd.label.substring(0,15));
      }
    });
}

// ═══════════════════════════════════════════════════════════
// COUPE / SUNBURST
// ═══════════════════════════════════════════════════════════
function renderSlice(svg,g){
  const W=svg.node().clientWidth,H=svg.node().clientHeight;
  const cx=W/2,cy=H/2, radius=Math.min(W,H)/2-12;
  const types=[...new Set(onto.nodes.filter(n=>visTypes.has(n.type)).map(n=>n.type))].sort();
  const root={name:'TOP',children:types.map(t=>({
    name:t, children:onto.nodes.filter(n=>n.type===t&&visTypes.has(n.type)).map(m=>({name:m.label,_nd:m,value:1}))
  }))};
  const hier=d3.hierarchy(root).sum(d=>d.value||0).sort((a,b)=>b.value-a.value);
  d3.partition().size([2*Math.PI,radius])(hier);
  const arc=d3.arc().startAngle(d=>d.x0).endAngle(d=>d.x1).innerRadius(d=>d.y0).outerRadius(d=>d.y1);
  const gA=g.append('g').attr('transform',`translate(${cx},${cy})`);

  gA.selectAll('path').data(hier.descendants().filter(d=>d.depth>0)).enter().append('path')
    .attr('d',arc)
    .attr('fill',d=>tc(d.depth===1?d.data.name:(d.parent?.data?.name||'Unknown'))+(d.depth===1?'55':'22'))
    .attr('stroke',d=>tc(d.depth===1?d.data.name:(d.parent?.data?.name||'Unknown')))
    .attr('stroke-width',.4).style('cursor','pointer')
    .on('click',(ev,d)=>{ if(d.data._nd){showInfo(d.data._nd);selNode=d.data._nd;} })
    .on('mouseover',function(ev,d){ d3.select(this).attr('opacity',.72); if(d.data._nd) showTip(ev,d.data._nd); else showTipRaw(ev,d.data.name,d.value+' éléments'); })
    .on('mouseout',function(){ d3.select(this).attr('opacity',1); hideTip(); });

  gA.selectAll('text.ct').data(hier.descendants().filter(d=>d.depth===1&&(d.x1-d.x0)>0.1)).enter()
    .append('text').attr('class','ct')
    .attr('transform',d=>{ const a=(d.x0+d.x1)/2,r=(d.y0+d.y1)/2; return `translate(${r*Math.sin(a)},${-r*Math.cos(a)}) rotate(${a*180/Math.PI-90})`; })
    .attr('text-anchor','middle').attr('dominant-baseline','central')
    .style('font','700 9px Fraunces,serif').style('fill',d=>tc(d.data.name)).style('pointer-events','none')
    .text(d=>d.data.name);
  gA.append('text').attr('text-anchor','middle').attr('dy','0.35em')
    .style('font','900 14px Fraunces,serif').style('fill','var(--acc)').text('TOP');
}

// ═══════════════════════════════════════════════════════════
// NODE SHAPE
// ═══════════════════════════════════════════════════════════
function drawShape(el,d){
  const col=tc(d.type), hi=hiN.has(d.id), sw=hi?3.5:1.5;
  if(d.type==='Class')
    el.append('circle').attr('r',12).attr('fill',col+'18').attr('stroke',col).attr('stroke-width',sw);
  else if(d.type==='ObjectProperty'||d.type==='Property')
    el.append('polygon').attr('points','0,-11 11,0 0,11 -11,0').attr('fill',col+'18').attr('stroke',col).attr('stroke-width',sw);
  else if(d.type==='DatatypeProperty')
    el.append('rect').attr('x',-9).attr('y',-9).attr('width',18).attr('height',18).attr('rx',3).attr('fill',col+'18').attr('stroke',col).attr('stroke-width',sw);
  else if(d.type==='AnnotationProperty')
    el.append('ellipse').attr('rx',11).attr('ry',7).attr('fill',col+'18').attr('stroke',col).attr('stroke-width',sw);
  else
    el.append('circle').attr('r',9).attr('fill',col+'18').attr('stroke',col).attr('stroke-width',sw);
  el.append('text').attr('dy','0.35em').style('fill',hi?col:'#444').text(d.label.substring(0,12));
}

// ═══════════════════════════════════════════════════════════
// INFO PANEL
// ═══════════════════════════════════════════════════════════
function showInfo(d){
  if(!d) return;
  const col=tc(d.type);
  const outE=onto.edges.filter(e=>e.source===d.id);
  const inE=onto.edges.filter(e=>e.target===d.id);
  let h=`<div class="iname">${e(d.label)}</div>`;
  h+=`<span class="ibadge" style="background:${col}18;color:${col};border:1.5px solid ${col}33">${d.type}</span>`;
  if(d.comment) h+=`<p class="icmt">${e(d.comment.substring(0,240))}${d.comment.length>240?'…':''}</p>`;
  h+=`<div class="irow"><span class="ikey">ID</span><span class="ival">${e(d.id)}</span></div>`;
  if(d.fullUri&&d.fullUri!==d.id)
    h+=`<div class="irow"><span class="ikey">URI</span><span class="ival" style="font-size:.59rem">${e(d.fullUri.substring(0,70))}${d.fullUri.length>70?'…':''}</span></div>`;
  if(d.props) for(const[k,v] of Object.entries(d.props))
    h+=`<div class="irow"><span class="ikey">${e(k)}</span><span class="ival">${e(String(v))}</span></div>`;
  if(outE.length){
    const grp={};
    for(const ed of outE)(grp[ed.rel]||(grp[ed.rel]=[])).push(ed.target);
    h+=`<div class="irow"><span class="ikey">Relations (${outE.length})</span><span class="ival">`;
    for(const[rel,tgts] of Object.entries(grp)){
      h+=`<div class="irgrp"><span class="irn">${e(rel)}</span>`;
      for(const t of tgts.slice(0,8)){
        const tn=onto.nodes.find(n=>n.id===t);
        h+=`<a class="ilink" onclick="jumpTo('${ea(t)}')">${e(tn?tn.label:t)}</a>`;
      }
      if(tgts.length>8) h+=`<span style="color:var(--ink3);font-size:.59rem">+${tgts.length-8}…</span>`;
      h+=`</div>`;
    }
    h+=`</span></div>`;
  }
  if(inE.length){
    const srcs=[...new Set(inE.map(ed=>ed.source))].slice(0,8);
    h+=`<div class="irow"><span class="ikey">Référencé par (${inE.length})</span><span class="ival">`;
    for(const s of srcs){ const sn=onto.nodes.find(n=>n.id===s); h+=`<a class="ilink" onclick="jumpTo('${ea(s)}')">${e(sn?sn.label:s)}</a>`; }
    if(inE.length>8) h+=`<span style="color:var(--ink3);font-size:.59rem">+${inE.length-8}…</span>`;
    h+=`</span></div>`;
  }
  h+=`<div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:10px">
    <button class="btn" onclick="goRadial('${ea(d.id)}')">◎ Radial</button>
    <button class="btn" onclick="setPathFrom('${ea(d.id)}','${ea(d.label)}')">⇝ Depuis ici</button>
  </div>`;
  document.getElementById('ibox').innerHTML=h;
}

function jumpTo(id){ const n=onto.nodes.find(x=>x.id===id); if(!n) return; selNode=n; showInfo(n); if(mode==='radial') renderMode(); }
function goRadial(id){ const n=onto.nodes.find(x=>x.id===id); if(!n) return; selNode=n; bcStack=[n]; setMode('radial'); }
function setPathFrom(id,label){ pathMode=true; document.getElementById('bPath').classList.add('active'); document.getElementById('pp').style.display='block'; document.getElementById('pfrom').value=label; }

// ═══════════════════════════════════════════════════════════
// BFS
// ═══════════════════════════════════════════════════════════
function bfsAdj(nodeList){
  const ids=new Set(nodeList.map(n=>n.id)), adj=new Map();
  for(const ed of onto.edges){
    if(!ids.has(ed.source)||!ids.has(ed.target)) continue;
    if(!adj.has(ed.source)) adj.set(ed.source,[]);
    if(!adj.has(ed.target)) adj.set(ed.target,[]);
    adj.get(ed.source).push({id:ed.target,edge:ed});
    adj.get(ed.target).push({id:ed.source,edge:ed});
  }
  return adj;
}
function bfsLevels(startId,maxD,nodeList){
  const adj=bfsAdj(nodeList),lvlMap=new Map([[startId,0]]),q=[[startId,0]];
  while(q.length){ const[id,lvl]=q.shift(); if(lvl>=maxD) continue; for(const{id:nb}of(adj.get(id)||[])) if(!lvlMap.has(nb)){lvlMap.set(nb,lvl+1);q.push([nb,lvl+1]);} }
  return{levelMap:lvlMap};
}
function bfsPath(fromId,toId){
  const adj=bfsAdj(onto.nodes),prev=new Map(),vis=new Set([fromId]),q=[fromId];
  while(q.length){ const cur=q.shift(); if(cur===toId) break; for(const{id:nb}of(adj.get(cur)||[])) if(!vis.has(nb)){vis.add(nb);prev.set(nb,cur);q.push(nb);} }
  if(!prev.has(toId)) return null;
  const path=[toId]; let cur=toId; while(cur!==fromId){cur=prev.get(cur);path.unshift(cur);} return path;
}

// ═══════════════════════════════════════════════════════════
// PATH FINDER
// ═══════════════════════════════════════════════════════════
function togPath(){
  pathMode=!pathMode;
  document.getElementById('bPath').classList.toggle('active',pathMode);
  document.getElementById('pp').style.display=pathMode?'block':'none';
  if(!pathMode) clearPath();
}
function findPath(){
  const fv=document.getElementById('pfrom').value.toLowerCase().trim();
  const tv=document.getElementById('pto').value.toLowerCase().trim();
  const fn=onto.nodes.find(n=>n.id.toLowerCase()===fv||n.label.toLowerCase().includes(fv));
  const tn=onto.nodes.find(n=>n.id.toLowerCase()===tv||n.label.toLowerCase().includes(tv));
  const res=document.getElementById('pr');
  if(!fn||!tn){res.textContent='⚠ Concepts introuvables';return;}
  const path=bfsPath(fn.id,tn.id);
  if(!path){res.textContent='⚠ Aucun chemin';hiN.clear();hiE.clear();renderMode();return;}
  hiN.clear();hiE.clear();
  path.forEach(id=>hiN.add(id));
  for(let i=0;i<path.length-1;i++){hiE.add(path[i]+'>'+path[i+1]);hiE.add(path[i+1]+'>'+path[i]);}
  const lbls=path.map(id=>{const n=onto.nodes.find(x=>x.id===id);return `<span class="phi">${e(n?n.label:id)}</span>`;});
  res.innerHTML=`${path.length-1} liens :<br>${lbls.join(' → ')}`;
  renderMode();
}
function clearPath(){hiN.clear();hiE.clear();document.getElementById('pfrom').value='';document.getElementById('pto').value='';document.getElementById('pr').textContent='';renderMode();}

// ═══════════════════════════════════════════════════════════
// SEARCH
// ═══════════════════════════════════════════════════════════
function doSearch(v){
  if(!v) return;
  const lv=v.toLowerCase();
  const n=onto.nodes.find(x=>x.label.toLowerCase().includes(lv)||x.id.toLowerCase().includes(lv));
  if(n){selNode=n;showInfo(n);}
}

// ═══════════════════════════════════════════════════════════
// TOOLTIP
// ═══════════════════════════════════════════════════════════
function showTip(ev,d){
  const t=document.getElementById('tip'),col=tc(d.type);
  t.innerHTML=`<strong style="color:${col}">${e(d.label)}</strong><br><span style="color:var(--ink3)">${d.type}</span>${d.comment?`<br><em style="color:var(--ink3);font-size:.59rem">${e(d.comment.substring(0,90))}${d.comment.length>90?'…':''}</em>`:''}`;
  t.style.opacity=1;
  const ca=document.getElementById('ca'),rect=ca.getBoundingClientRect();
  t.style.left=Math.min(ev.clientX-rect.left+12,rect.width-230)+'px';
  t.style.top=Math.max(ev.clientY-rect.top-10,0)+'px';
}
function showTipRaw(ev,title,sub){
  const t=document.getElementById('tip');
  t.innerHTML=`<strong>${e(title)}</strong><br><span style="color:var(--ink3)">${e(sub)}</span>`;
  t.style.opacity=1;
  const ca=document.getElementById('ca'),rect=ca.getBoundingClientRect();
  t.style.left=Math.min(ev.clientX-rect.left+12,rect.width-230)+'px';
  t.style.top=Math.max(ev.clientY-rect.top-10,0)+'px';
}
function hideTip(){document.getElementById('tip').style.opacity=0;}

// ═══════════════════════════════════════════════════════════
// BREADCRUMB
// ═══════════════════════════════════════════════════════════
function updBC(cur){
  if(!bcStack.length||bcStack[bcStack.length-1].id!==cur.id) bcStack.push(cur);
  const bc=document.getElementById('bc'); bc.innerHTML='';
  bcStack.forEach((n,i)=>{
    if(i>0){const s=document.createElement('span');s.className='csep';s.textContent='›';bc.appendChild(s);}
    const c=document.createElement('span'); c.className='crumb'+(i===bcStack.length-1?' cur':'');
    c.textContent=n.label.substring(0,12);
    c.onclick=()=>{bcStack=bcStack.slice(0,i+1);selNode=n;renderMode();};
    bc.appendChild(c);
  });
}

// ═══════════════════════════════════════════════════════════
// FILE / EXPORT / RESET
// ═══════════════════════════════════════════════════════════
function showDov(){document.getElementById('dov').classList.add('on');}
function hideDov(){document.getElementById('dov').classList.remove('on');}
function doExport(){
  const data={nodes:onto.nodes,edges:onto.edges.map(ed=>({source:ed.source,target:ed.target,rel:ed.rel,type:ed.type}))};
  const blob=new Blob([JSON.stringify(data,null,2)],{type:'application/json'});
  const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download='ontology.json'; a.click();
}
function doReset(){
  selNode=null; bcStack=[]; hiN.clear(); hiE.clear();
  if(sim){sim.stop();sim=null;}
  document.getElementById('ibox').innerHTML='<div class="empty-state"><div class="empty-icon">⬡</div>Cliquez sur un nœud<br>pour ses détails</div>';
  renderMode();
}
</script>
</body>
</html>
