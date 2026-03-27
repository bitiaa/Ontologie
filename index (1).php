<?php
/**
 * OntoViz — OWL / RDFS Visualizer
 * Version PHP
 */

// Configuration
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB
define('ALLOWED_EXTENSIONS', ['owl', 'rdf', 'rdfs', 'xml', 'json', 'jsonld']);
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// Ontologies préchargées disponibles sur le serveur
define('PRELOADED_DIR', __DIR__ . '/');
$PRELOADED_ONTOLOGIES = [
    'geo_usa'   => ['file' => null,                          'label' => 'geo_usa.owl — Géographie USA (défaut)'],
    'bckm'      => ['file' => 'bckmJSON.owl',                'label' => 'bckmJSON.owl — BCKM (JSON-LD)'],
    'human'     => ['file' => 'human_2007_09_11.rdfs',       'label' => 'human_2007_09_11.rdfs — Humans (RDFS)'],
    'wildlife'  => ['file' => 'AfricanWildlifeOntology1.owl','label' => 'AfricanWildlifeOntology1.owl — African Wildlife (OWL)'],
];

// Créer le dossier uploads s'il n'existe pas
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// ─── Chargement d'une ontologie préchargée (AJAX GET) ───────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['preload'])) {
    header('Content-Type: application/json');
    $key = $_GET['preload'];
    if (!isset($PRELOADED_ONTOLOGIES[$key]) || $PRELOADED_ONTOLOGIES[$key]['file'] === null) {
        echo json_encode(['success' => false, 'error' => 'Ontologie introuvable']);
        exit;
    }
    $path = PRELOADED_DIR . $PRELOADED_ONTOLOGIES[$key]['file'];
    if (!file_exists($path)) {
        echo json_encode(['success' => false, 'error' => 'Fichier manquant sur le serveur : ' . basename($path)]);
        exit;
    }
    $content = file_get_contents($path);
    echo json_encode([
        'success'  => true,
        'filename' => basename($path),
        'content'  => $content,
        'size'     => strlen($content),
    ]);
    exit;
}

// ─── Traitement de l'upload de fichier (AJAX) ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['ontology'])) {
    header('Content-Type: application/json');

    $file = $_FILES['ontology'];
    $error = null;

    // Vérification de la taille
    if ($file['size'] > MAX_FILE_SIZE) {
        $error = 'Fichier trop grand (max 10 Mo)';
    }

    // Vérification de l'extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        $error = 'Extension non autorisée. Formats acceptés : ' . implode(', ', ALLOWED_EXTENSIONS);
    }

    if ($error) {
        echo json_encode(['success' => false, 'error' => $error]);
        exit;
    }

    // Lire le contenu du fichier
    $content = file_get_contents($file['tmp_name']);
    if ($content === false) {
        echo json_encode(['success' => false, 'error' => 'Impossible de lire le fichier']);
        exit;
    }

    echo json_encode([
        'success'  => true,
        'filename' => htmlspecialchars($file['name']),
        'content'  => $content,
        'size'     => $file['size'],
    ]);
    exit;
}

// ─── Export JSON (AJAX) ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export') {
    header('Content-Type: application/json');
    $data = $_POST['data'] ?? '{}';
    // Retourne simplement les données pour téléchargement côté client
    echo $data;
    exit;
}

// ─── Page principale ────────────────────────────────────────
$pageTitle = 'OntoViz — OWL / RDFS Visualizer';
// Passer la liste des ontologies au JS (sans la clé 'file' pour la sécu)
$ontoMenuJson = json_encode(array_map(fn($k, $v) => ['key' => $k, 'label' => $v['label']], array_keys($PRELOADED_ONTOLOGIES), $PRELOADED_ONTOLOGIES));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/d3/7.8.5/d3.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;800&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#090c12; --surf:#111420; --surf2:#181d2c; --bdr:#232840;
  --acc:#00e5ff; --ora:#ff6b35; --grn:#a8ff3e; --pur:#c77dff;
  --txt:#dde3f0; --dim:#5a6180; --panel:272px;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Space Mono',monospace;background:var(--bg);color:var(--txt);height:100vh;overflow:hidden;display:flex;flex-direction:column}

/* ── header ── */
header{display:flex;align-items:center;gap:7px;padding:7px 14px;background:var(--surf);border-bottom:1px solid var(--bdr);flex-shrink:0;flex-wrap:wrap}
.logo{font-family:'Syne',sans-serif;font-weight:800;font-size:1.1rem;color:var(--acc);letter-spacing:-1px;white-space:nowrap}
.logo span{color:var(--dim);font-weight:400}
.tbar{display:flex;gap:5px;align-items:center;flex-wrap:wrap;flex:1}
.btn{background:var(--surf2);border:1px solid var(--bdr);color:var(--txt);padding:4px 10px;border-radius:4px;cursor:pointer;font:400 .67rem 'Space Mono',monospace;transition:all .13s;white-space:nowrap}
.btn:hover{border-color:var(--acc);color:var(--acc)}
.btn.active{background:rgba(0,229,255,.1);border-color:var(--acc);color:var(--acc)}
.btn.warn:hover{border-color:var(--ora);color:var(--ora)}
.sep{width:1px;height:19px;background:var(--bdr);flex-shrink:0}
input[type=text]{background:var(--surf2);border:1px solid var(--bdr);color:var(--txt);padding:4px 8px;border-radius:4px;font:400 .67rem 'Space Mono',monospace}
input[type=text]:focus{outline:none;border-color:var(--acc)}
input[type=range]{width:65px;accent-color:var(--acc);cursor:pointer}
.fbl{font-size:.62rem;color:var(--grn);border:1px solid rgba(168,255,62,.35);background:rgba(168,255,62,.08);padding:2px 7px;border-radius:3px;max-width:190px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sbar{display:flex;gap:9px;font-size:.59rem;color:var(--dim)}
.sbar b{color:var(--grn)}

/* ── layout ── */
.main{display:flex;flex:1;overflow:hidden}

/* ── left panel ── */
.panel{width:var(--panel);background:var(--surf);border-right:1px solid var(--bdr);display:flex;flex-direction:column;overflow:hidden;flex-shrink:0}
.psec{padding:9px 11px;border-bottom:1px solid var(--bdr)}
.ptit{font-family:'Syne',sans-serif;font-size:.57rem;font-weight:600;color:var(--dim);text-transform:uppercase;letter-spacing:2px;margin-bottom:7px}
.flist{list-style:none;max-height:175px;overflow-y:auto}
.fi{display:flex;align-items:center;gap:6px;font-size:.64rem;padding:2px 4px;border-radius:2px;cursor:pointer}
.fi:hover{background:var(--surf2)}
.fdot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.fcb{accent-color:var(--acc);cursor:pointer}
.ibox{flex:1;overflow-y:auto;padding:10px}
.iname{font-family:'Syne',sans-serif;font-size:.88rem;font-weight:800;color:var(--acc);word-break:break-all;line-height:1.2;margin-bottom:4px}
.ibadge{display:inline-block;padding:2px 7px;border-radius:20px;font-size:.59rem;margin-bottom:6px}
.icmt{font-size:.61rem;font-style:italic;color:var(--dim);line-height:1.5;margin-bottom:5px;padding:3px 6px;background:var(--surf2);border-radius:3px;border-left:2px solid var(--bdr)}
.irow{font-size:.64rem;margin-bottom:3px;padding-bottom:3px;border-bottom:1px solid var(--bdr)}
.ikey{color:var(--dim);font-size:.59rem;display:block;margin-bottom:1px}
.ival{color:var(--txt);word-break:break-all;line-height:1.5}
.ilink{color:var(--acc);cursor:pointer;text-decoration:underline;display:block;line-height:1.7;font-size:.63rem}
.irgrp{margin-bottom:4px}
.irn{display:block;color:var(--ora);font-size:.61rem;margin-bottom:1px}

/* ── canvas ── */
.carea{flex:1;position:relative;overflow:hidden}
svg.msv{width:100%;height:100%;background:var(--bg)}
.link{fill:none}
.node{cursor:pointer}
.node text{font:400 8px 'Space Mono',monospace;fill:var(--txt);pointer-events:none;text-anchor:middle;dominant-baseline:central}
.node.hi circle,.node.hi ellipse,.node.hi rect,.node.hi polygon{stroke-width:3.5!important}
.node.dim circle,.node.dim ellipse,.node.dim rect,.node.dim polygon{opacity:.1}
.node.dim text{opacity:.1}
.link.hi{stroke-width:3!important;opacity:1!important}
.link.dim{opacity:.04!important}
.radring{fill:none;stroke:var(--bdr);stroke-width:.4}

/* ── overlays ── */
.mbadge{position:absolute;top:8px;right:8px;background:rgba(17,20,32,.9);border:1px solid var(--bdr);border-radius:4px;padding:3px 8px;font-size:.61rem;color:var(--dim);pointer-events:none;backdrop-filter:blur(4px)}
.mbadge b{color:var(--acc)}
#leg{position:absolute;bottom:8px;left:8px;background:rgba(17,20,32,.9);border:1px solid var(--bdr);border-radius:5px;padding:7px 9px;font-size:.59rem;pointer-events:none;backdrop-filter:blur(4px)}
.lt{color:var(--dim);font-size:.55rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px}
.lr{display:flex;align-items:center;gap:5px;margin-bottom:2px}
.ld{width:7px;height:7px;border-radius:50%;flex-shrink:0}
#tip{position:absolute;background:var(--surf2);border:1px solid var(--bdr);border-radius:5px;padding:6px 10px;font-size:.64rem;pointer-events:none;opacity:0;transition:opacity .1s;max-width:210px;z-index:200;line-height:1.5}
#bc{position:absolute;top:8px;left:8px;display:flex;gap:3px;align-items:center;flex-wrap:wrap;max-width:55%}
.crumb{background:var(--surf2);border:1px solid var(--bdr);border-radius:3px;padding:1px 6px;font-size:.59rem;cursor:pointer;color:var(--dim)}
.crumb.cur{color:var(--acc);border-color:var(--acc)}
.csep{color:var(--dim);font-size:.62rem}

/* ── path panel ── */
.pi{display:flex;flex-direction:column;gap:4px}
.pi input{width:100%;font-size:.63rem}
#pr{font-size:.62rem;color:var(--dim);margin-top:4px;line-height:1.6}
#pr .phi{color:var(--acc)}

/* ── drop overlay ── */
#dov{display:none;position:fixed;inset:0;background:rgba(9,12,18,.93);z-index:500;justify-content:center;align-items:center}
#dov.on{display:flex}
.dbox{background:var(--surf);border:2px dashed var(--acc);border-radius:12px;padding:34px;text-align:center;max-width:370px}
.dbox h2{font-family:'Syne',sans-serif;color:var(--acc);margin-bottom:10px;font-size:.95rem}
.dbox p{color:var(--dim);font-size:.7rem;margin-bottom:12px;line-height:1.6}
.ftags{display:flex;gap:5px;justify-content:center;flex-wrap:wrap;margin-bottom:12px}
.ftag{font-size:.59rem;padding:2px 7px;border-radius:3px}

/* ── loading overlay ── */
#lov{display:none;position:fixed;inset:0;background:rgba(9,12,18,.75);z-index:600;justify-content:center;align-items:center}
#lov.on{display:flex}
.lbox{background:var(--surf);border:1px solid var(--bdr);border-radius:8px;padding:24px 32px;text-align:center;font-family:'Syne',sans-serif;color:var(--acc)}
.lbox p{color:var(--dim);font-size:.7rem;margin-top:6px}

/* ── menu ontologies préchargées ── */
.onto-sep{display:flex;align-items:center;gap:8px;margin:12px 0;color:var(--dim);font-size:.62rem}
.onto-sep::before,.onto-sep::after{content:'';flex:1;height:1px;background:var(--bdr)}
.onto-list{display:flex;flex-direction:column;gap:5px;margin-bottom:12px;max-height:170px;overflow-y:auto}
.onto-item{display:flex;align-items:center;gap:8px;background:var(--surf2);border:1px solid var(--bdr);border-radius:5px;padding:6px 10px;cursor:pointer;transition:all .13s;text-align:left}
.onto-item:hover{border-color:var(--acc);background:rgba(0,229,255,.06)}
.onto-item .oi-icon{font-size:.95rem;flex-shrink:0}
.onto-item .oi-info{flex:1;overflow:hidden}
.onto-item .oi-name{font-size:.65rem;color:var(--acc);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.onto-item .oi-sub{font-size:.57rem;color:var(--dim)}

::-webkit-scrollbar{width:4px}
::-webkit-scrollbar-track{background:var(--surf)}
::-webkit-scrollbar-thumb{background:var(--bdr);border-radius:2px}
</style>
</head>
<body>

<!-- Loading overlay -->
<div id="lov">
  <div class="lbox">
    <div style="font-size:1.5rem;margin-bottom:6px">⬡</div>
    Chargement…
    <p>Traitement du fichier en cours</p>
  </div>
</div>

<!-- Drop overlay -->
<div id="dov">
  <div class="dbox" style="max-width:430px;width:95vw">
    <h2>📂 Charger une ontologie</h2>

    <!-- Ontologies préchargées (générées par PHP) -->
    <div style="text-align:left;margin-bottom:4px">
      <div class="ptit" style="margin-bottom:6px">Ontologies disponibles</div>
      <div class="onto-list" id="ontoList"></div>
    </div>

    <div class="onto-sep">ou importer un fichier local</div>

    <div class="ftags">
      <span class="ftag" style="background:rgba(0,229,255,.1);color:var(--acc);border:1px solid rgba(0,229,255,.4)">JSON-LD .owl</span>
      <span class="ftag" style="background:rgba(168,255,62,.1);color:var(--grn);border:1px solid rgba(168,255,62,.4)">RDF/XML .owl</span>
      <span class="ftag" style="background:rgba(255,107,53,.1);color:var(--ora);border:1px solid rgba(255,107,53,.4)">RDFS .rdfs</span>
    </div>
    <input type="file" id="fi" accept=".owl,.rdf,.rdfs,.xml,.json,.jsonld" style="display:none">
    <button class="btn" onclick="document.getElementById('fi').click()">Choisir un fichier local</button>
    <br><br>
    <button class="btn warn" onclick="hideDov()">Annuler</button>
  </div>
</div>

<header>
  <div class="logo">Onto<span>Viz</span></div>
  <div class="tbar">
    <button class="btn active" id="bForce"  onclick="setMode('force')">⬡ Force</button>
    <button class="btn"        id="bRadial" onclick="setMode('radial')">◎ Radial</button>
    <button class="btn"        id="bTree"   onclick="setMode('tree')">⊞ Arbre</button>
    <button class="btn"        id="bSlice"  onclick="setMode('slice')">⊙ Coupe</button>
    <div class="sep"></div>
    <input type="text" id="sb" placeholder="Chercher…" oninput="doSearch(this.value)" style="width:125px">
    <div class="sep"></div>
    <span style="font-size:.64rem;color:var(--dim)">Prof.</span>
    <input type="range" id="dr" min="1" max="6" value="2" oninput="setDepth(+this.value)">
    <span id="dv" style="font-size:.66rem;min-width:10px">2</span>
    <div class="sep"></div>
    <button class="btn" id="bPath" onclick="togPath()">⇝ Chemin</button>
    <div class="sep"></div>
    <button class="btn" onclick="showDov()">📂 Charger</button>
    <button class="btn" onclick="doExport()">⤓ JSON</button>
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
        <button class="btn" onclick="findPath()">→ Trouver</button>
        <button class="btn warn" onclick="clearPath()">✕ Effacer</button>
        <div id="pr"></div>
      </div>
    </div>
    <div class="ibox" id="ibox">
      <div style="color:var(--dim);font-size:.68rem;text-align:center;margin-top:50px;line-height:2.3">
        <div style="font-size:2rem;margin-bottom:8px">⬡</div>
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
// UPLOAD VIA PHP (AJAX)
// ═══════════════════════════════════════════════════════════

// Ontologies préchargées injectées par PHP
const PRELOADED_ONTOLOGIES = <?= $ontoMenuJson ?>;

// Icônes par type de fichier
function ontoIcon(label){
  if(label.includes('JSON-LD')||label.includes('json')) return '🟦';
  if(label.includes('rdfs')||label.includes('RDFS')) return '🟩';
  if(label.includes('owl')||label.includes('OWL')) return '🟧';
  return '📄';
}

// Construire la liste des ontologies dans le modal
function buildOntoList(){
  const list = document.getElementById('ontoList');
  list.innerHTML = '';
  for(const onto of PRELOADED_ONTOLOGIES){
    const parts = onto.label.split(' — ');
    const name = parts[0] || onto.label;
    const sub  = parts.slice(1).join(' — ') || '';
    const item = document.createElement('button');
    item.className = 'onto-item btn';
    item.style.cssText = 'width:100%;border:1px solid var(--bdr)';
    item.innerHTML = `<span class="oi-icon">${ontoIcon(onto.label)}</span>
      <span class="oi-info">
        <span class="oi-name">${name}</span>
        ${sub ? `<span class="oi-sub">${sub}</span>` : ''}
      </span>`;
    item.onclick = () => { hideDov(); loadPreloaded(onto.key, name); };
    list.appendChild(item);
  }
}

// Charger une ontologie préchargée depuis le serveur PHP
function loadPreloaded(key, displayName){
  if(key === 'geo_usa'){
    loadOnto(DEFAULT_OWL, 'geo_usa.owl');
    return;
  }
  document.getElementById('lov').classList.add('on');
  fetch(`index.php?preload=${encodeURIComponent(key)}`)
    .then(r => r.json())
    .then(data => {
      document.getElementById('lov').classList.remove('on');
      if(!data.success){ alert('Erreur serveur : ' + data.error); return; }
      try{ loadOnto(data.content, data.filename); }
      catch(err){ alert('Erreur de parsing :\n' + err.message); console.error(err); }
    })
    .catch(err => {
      document.getElementById('lov').classList.remove('on');
      alert('Erreur réseau : ' + err.message);
    });
}

function uploadFileToServer(file) {
  const formData = new FormData();
  formData.append('ontology', file);
  document.getElementById('lov').classList.add('on');
  fetch('index.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      document.getElementById('lov').classList.remove('on');
      if (!data.success) { alert('Erreur serveur : ' + data.error); return; }
      try { loadOnto(data.content, data.filename); }
      catch(err) { alert('Erreur de parsing :\n' + err.message); console.error(err); }
    })
    .catch(err => {
      document.getElementById('lov').classList.remove('on');
      alert('Erreur réseau : ' + err.message);
    });
}

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
function xmlLabel(el, nsLabel){
  const lblEls = el.getElementsByTagNameNS(nsLabel,'label');
  let fallback=null;
  for(const l of lblEls){
    const lang=l.getAttributeNS(XML_NS,'lang')||l.getAttribute('xml:lang')||'';
    if(lang==='en') return l.textContent.trim();
    if(!fallback) fallback=l.textContent.trim();
  }
  return fallback;
}
function xmlComment(el, nsLabel){
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

    const lbl = pickLabel(item[RDFS_NS+'label'])
             || pickLabel(item[BCKM+'NCI_label'])
             || lname(id);
    const cmt = pickLabel(item[RDFS_NS+'comment'])
             || pickLabel(item[BCKM+'NCI_DEFINITION'])
             || '';

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
        edges.push({source:lname(id),target:r.target,rel:r.prop+'(∃)',type:'restriction'});
      } else if(tid&&!tid.startsWith('_:')){
        en(tid,'Class',lname(tid));
        edges.push({source:lname(id),target:lname(tid),rel:'subClassOf',type:'subclass'});
      }
    }
    for(const sp of (item[RDFS_NS+'subPropertyOf']||[])){
      const tid=(sp['@id']||'');
      if(tid&&!tid.startsWith('_:')){
        en(tid,'ObjectProperty',lname(tid));
        edges.push({source:lname(id),target:lname(tid),rel:'subPropertyOf',type:'subprop'});
      }
    }
    for(const d of (item[RDFS_NS+'domain']||[])){
      const tid=(d['@id']||'');
      if(tid&&!tid.startsWith('_:')){en(tid,'Class',lname(tid));edges.push({source:lname(id),target:lname(tid),rel:'domain',type:'propdef'});}
    }
    for(const r of (item[RDFS_NS+'range']||[])){
      const tid=(r['@id']||'');
      if(tid&&!tid.startsWith('_:')){en(tid,'Class',lname(tid));edges.push({source:lname(id),target:lname(tid),rel:'range',type:'propdef'});}
    }
    for(const inv of (item[OWL_NS+'inverseOf']||[])){
      const tid=(inv['@id']||'');
      if(tid&&!tid.startsWith('_:')){en(tid,'ObjectProperty',lname(tid));edges.push({source:lname(id),target:lname(tid),rel:'inverseOf',type:'inverse'});}
    }
  }
  return {nodes:[...nodes.values()],edges};
}

// ═══════════════════════════════════════════════════════════
// PARSER 2 — RDF/XML
// ═══════════════════════════════════════════════════════════
function parseRDFXML(text){
  const entMap={};
  text.replace(/<!ENTITY\s+(\w+)\s+"([^"]+)"/g,(_,n,v)=>{ entMap[n]=v; });
  let resolved=text.replace(/&([\w]+);/g,(_,n)=>entMap[n]||'');
  resolved=resolved.replace(/<!DOCTYPE\s[^[]*\[[^\]]*\]\s*>/s,'')
                   .replace(/<!DOCTYPE[^>]*>/g,'');
  const doc=new DOMParser().parseFromString(resolved,'application/xml');
  const perr=doc.querySelector('parsererror');
  if(perr){
    const doc2=new DOMParser().parseFromString(resolved,'text/xml');
    return _parseOWLDoc(doc2);
  }
  return _parseOWLDoc(doc);
}

function _parseOWLDoc(doc){
  const nodes=new Map(), edges=[];

  function en(id,type,label){
    if(!id) return;
    const k=lname(id);
    if(!nodes.has(k)) nodes.set(k,{id:k,fullUri:id,label:label||k,type:'Unknown',comment:'',props:{}});
    const n=nodes.get(k);
    if(type&&n.type==='Unknown') n.type=type;
    if(label&&n.label===k) n.label=label;
  }
  function glbl(el){ return xmlLabel(el,RDFS_NS)||null; }
  function gcmt(el){ return xmlComment(el,RDFS_NS)||null; }

  for(const el of doc.getElementsByTagNameNS(OWL_NS,'Class')){
    const id=xmlId(el); if(!id) continue;
    en(id,'Class',glbl(el)||lname(id));
    const nd=nodes.get(lname(id)); if(nd) nd.comment=gcmt(el)||'';
    for(const sc of el.getElementsByTagNameNS(RDFS_NS,'subClassOf')){
      const res=xmlRes(sc);
      if(res){ en(res,'Class',lname(res)); edges.push({source:lname(id),target:lname(res),rel:'subClassOf',type:'subclass'}); }
      else {
        const onP=sc.getElementsByTagNameNS(OWL_NS,'onProperty')[0];
        const svf=sc.getElementsByTagNameNS(OWL_NS,'someValuesFrom')[0]
               ||sc.getElementsByTagNameNS(OWL_NS,'allValuesFrom')[0];
        if(onP&&svf){
          const pr=xmlRes(onP), tr=xmlRes(svf);
          if(pr&&tr){ en(tr,'Class',lname(tr)); edges.push({source:lname(id),target:lname(tr),rel:lname(pr)+'(∃)',type:'restriction'}); }
        }
      }
    }
    for(const ec of el.getElementsByTagNameNS(OWL_NS,'equivalentClass')){
      const res=xmlRes(ec); if(res){ en(res,'Class',lname(res)); edges.push({source:lname(id),target:lname(res),rel:'equivalentClass',type:'equiv'}); }
    }
  }
  for(const el of doc.getElementsByTagNameNS(OWL_NS,'ObjectProperty')){
    const id=xmlId(el); if(!id) continue;
    en(id,'ObjectProperty',glbl(el)||lname(id));
    const nd=nodes.get(lname(id)); if(nd) nd.comment=gcmt(el)||'';
    for(const sp of el.getElementsByTagNameNS(RDFS_NS,'subPropertyOf')){ const r=xmlRes(sp); if(r){en(r,'ObjectProperty',lname(r));edges.push({source:lname(id),target:lname(r),rel:'subPropertyOf',type:'subprop'});} }
    for(const d of el.getElementsByTagNameNS(RDFS_NS,'domain')){ const r=xmlRes(d); if(r){en(r,'Class',lname(r));edges.push({source:lname(id),target:lname(r),rel:'domain',type:'propdef'});} }
    for(const r of el.getElementsByTagNameNS(RDFS_NS,'range')){ const res=xmlRes(r); if(res){en(res,'Class',lname(res));edges.push({source:lname(id),target:lname(res),rel:'range',type:'propdef'});} }
    for(const inv of el.getElementsByTagNameNS(OWL_NS,'inverseOf')){ const res=xmlRes(inv); if(res){en(res,'ObjectProperty',lname(res));edges.push({source:lname(id),target:lname(res),rel:'inverseOf',type:'inverse'});} }
  }
  for(const el of doc.getElementsByTagNameNS(OWL_NS,'DatatypeProperty')){
    const id=xmlId(el); if(!id) continue;
    en(id,'DatatypeProperty',glbl(el)||lname(id));
    for(const d of el.getElementsByTagNameNS(RDFS_NS,'domain')){ const r=xmlRes(d); if(r){en(r,'Class',lname(r));edges.push({source:lname(id),target:lname(r),rel:'domain',type:'propdef'});} }
    for(const r of el.getElementsByTagNameNS(RDFS_NS,'range')){ const res=xmlRes(r); if(res){en(res,'Class',lname(res));edges.push({source:lname(id),target:lname(res),rel:'range',type:'propdef'});} }
  }
  for(const el of doc.getElementsByTagNameNS(OWL_NS,'AnnotationProperty')){
    const id=xmlId(el); if(id) en(id,'AnnotationProperty',glbl(el)||lname(id));
  }
  for(const el of doc.getElementsByTagNameNS(OWL_NS,'NamedIndividual')){
    const id=xmlId(el); if(!id) continue;
    en(id,'Individual',glbl(el)||lname(id));
    for(const child of el.children){
      const r=xmlRes(child);
      if(r){ en(r,'Class',lname(r)); edges.push({source:lname(id),target:lname(r),rel:lname(child.localName||''),type:'instance'}); }
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

// Couleurs par type
const TC={
  Class:'#00e5ff', ObjectProperty:'#ff6b35', DatatypeProperty:'#ffa552',
  AnnotationProperty:'#ffcc00', Property:'#ff6b35', Individual:'#c77dff',
  State:'#a8ff3e', Capital:'#c77dff', City:'#ffd166',
  River:'#06d6a0', Mountain:'#ef476f', Lake:'#118ab2', Road:'#adb5bd',
  Unknown:'#777'
};
function tc(t){return TC[t]||TC.Unknown;}

// ═══════════════════════════════════════════════════════════
// INIT
// ═══════════════════════════════════════════════════════════
window.addEventListener('DOMContentLoaded',()=>{
  buildOntoList();
  loadOnto(DEFAULT_OWL,'geo_usa.owl');

  // Input file → upload via PHP
  document.getElementById('fi').addEventListener('change',ev=>{
    const f=ev.target.files[0];
    if(f){ uploadFileToServer(f); hideDov(); }
    ev.target.value='';
  });

  // Drag & drop sur le canvas → upload via PHP
  const ca=document.getElementById('ca');
  ca.addEventListener('dragover',ev=>ev.preventDefault());
  ca.addEventListener('drop',ev=>{
    ev.preventDefault();
    const f=ev.dataTransfer.files[0];
    if(f) uploadFileToServer(f);
  });
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
// FILTRES / LÉGENDE / STATS
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
      <span style="color:var(--dim)">${cnt}</span>`;
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
  sb.innerHTML=`<span class="sstat"><b>${nc}</b> nœuds</span>`
    +`<span class="sstat"><b>${ec}</b> arêtes</span>`
    +`<span class="sstat"><b>${cc}</b> classes</span>`
    +`<span class="sstat"><b>${pc}</b> props</span>`;
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
  mkArr('a0','rgba(255,255,255,.12)');
  mkArr('a-sub','rgba(0,229,255,.5)');
  mkArr('a-prop','rgba(255,107,53,.5)');
  mkArr('a-hi','#00e5ff');

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
    if(hiE.has(hk)||hiE.has(e.target+'>'+e.source)) return '#00e5ff';
    const m={subclass:'rgba(0,229,255,.22)',subprop:'rgba(0,229,255,.16)',propdef:'rgba(255,107,53,.22)',restriction:'rgba(255,209,102,.2)',equiv:'rgba(168,255,62,.2)',inverse:'rgba(199,125,255,.2)',instance:'rgba(6,214,160,.18)'};
    return m[e.type]||'rgba(255,255,255,.08)';
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

  const eMap={subclass:'rgba(0,229,255,.25)',subprop:'rgba(0,229,255,.18)',propdef:'rgba(255,107,53,.2)',restriction:'rgba(255,209,102,.18)',instance:'rgba(6,214,160,.15)'};
  for(const e of filtEdges(fn)){
    const sp=pos.get(e.source),tp=pos.get(e.target); if(!sp||!tp) continue;
    g.append('line').attr('stroke',eMap[e.type]||'rgba(255,255,255,.08)').attr('stroke-width',1)
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
    nG.append('circle').attr('r',r0).attr('fill',col+'22').attr('stroke',col)
      .attr('stroke-width',nd.id===root.id?3:1.5);
    nG.append('text').attr('dy','0.35em')
      .style('font-size',lvl===0?'10px':'8px')
      .style('fill',nd.id===root.id?col:'#bbb')
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
    .attr('class','link').attr('stroke','rgba(255,255,255,.1)').attr('stroke-width',1)
    .attr('d',d3.linkVertical().x(d=>d.x).y(d=>d.y));

  g.append('g').selectAll('.node').data(h.descendants()).enter().append('g')
    .attr('class','node').attr('transform',d=>`translate(${d.x},${d.y})`)
    .on('click',(ev,d)=>{ if(d.data._nd){showInfo(d.data._nd);selNode=d.data._nd;} })
    .on('mouseover',(ev,d)=>{ if(d.data._nd) showTip(ev,d.data._nd); }).on('mouseout',hideTip)
    .each(function(d){
      const el=d3.select(this);
      if(d.data.id==='__top__'){
        el.append('rect').attr('x',-18).attr('y',-9).attr('width',36).attr('height',18).attr('rx',3).attr('fill','rgba(255,255,255,.05)').attr('stroke','rgba(255,255,255,.25)').attr('stroke-width',1);
        el.append('text').attr('dy','0.35em').style('font-size','9px').style('fill','#555').text('TOP'); return;
      }
      const nd=d.data._nd; if(!nd) return;
      const col=tc(nd.type);
      if(d.children&&d.children.length){
        const w=Math.min(90,nd.label.length*6.2+14);
        el.append('rect').attr('x',-w/2).attr('y',-9).attr('width',w).attr('height',18).attr('rx',3).attr('fill',col+'22').attr('stroke',col).attr('stroke-width',1.5);
        el.append('text').attr('dy','0.35em').style('font-size','8.5px').style('fill',col).text(nd.label.substring(0,13));
      } else {
        el.append('circle').attr('r',5).attr('fill',col+'44').attr('stroke',col).attr('stroke-width',1.2);
        el.append('text').attr('dx',9).attr('dy','0.35em').style('font-size','7.5px').style('fill','#bbb').text(nd.label.substring(0,15));
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
    .attr('fill',d=>tc(d.depth===1?d.data.name:(d.parent?.data?.name||'Unknown'))+(d.depth===1?'99':'44'))
    .attr('stroke',d=>tc(d.depth===1?d.data.name:(d.parent?.data?.name||'Unknown')))
    .attr('stroke-width',.3).style('cursor','pointer')
    .on('click',(ev,d)=>{ if(d.data._nd){showInfo(d.data._nd);selNode=d.data._nd;} })
    .on('mouseover',function(ev,d){ d3.select(this).attr('opacity',.72); if(d.data._nd) showTip(ev,d.data._nd); else showTipRaw(ev,d.data.name,d.value+' éléments'); })
    .on('mouseout',function(){ d3.select(this).attr('opacity',1); hideTip(); });

  gA.selectAll('text.ct').data(hier.descendants().filter(d=>d.depth===1&&(d.x1-d.x0)>0.1)).enter()
    .append('text').attr('class','ct')
    .attr('transform',d=>{ const a=(d.x0+d.x1)/2,r=(d.y0+d.y1)/2; return `translate(${r*Math.sin(a)},${-r*Math.cos(a)}) rotate(${a*180/Math.PI-90})`; })
    .attr('text-anchor','middle').attr('dominant-baseline','central')
    .style('font','700 9px Syne,sans-serif').style('fill',d=>tc(d.data.name)).style('pointer-events','none')
    .text(d=>d.data.name);
  gA.append('text').attr('text-anchor','middle').attr('dy','0.35em')
    .style('font','800 14px Syne,sans-serif').style('fill','var(--acc)').text('TOP');
}

// ═══════════════════════════════════════════════════════════
// NODE SHAPE
// ═══════════════════════════════════════════════════════════
function drawShape(el,d){
  const col=tc(d.type), hi=hiN.has(d.id), sw=hi?3.5:1.5;
  if(d.type==='Class')
    el.append('circle').attr('r',12).attr('fill',col+'22').attr('stroke',col).attr('stroke-width',sw);
  else if(d.type==='ObjectProperty'||d.type==='Property')
    el.append('polygon').attr('points','0,-11 11,0 0,11 -11,0').attr('fill',col+'22').attr('stroke',col).attr('stroke-width',sw);
  else if(d.type==='DatatypeProperty')
    el.append('rect').attr('x',-9).attr('y',-9).attr('width',18).attr('height',18).attr('rx',2).attr('fill',col+'22').attr('stroke',col).attr('stroke-width',sw);
  else if(d.type==='AnnotationProperty')
    el.append('ellipse').attr('rx',11).attr('ry',7).attr('fill',col+'22').attr('stroke',col).attr('stroke-width',sw);
  else
    el.append('circle').attr('r',9).attr('fill',col+'22').attr('stroke',col).attr('stroke-width',sw);
  el.append('text').attr('dy','0.35em').style('fill',hi?col:'#ccc').text(d.label.substring(0,12));
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
  h+=`<span class="ibadge" style="background:${col}22;color:${col};border:1px solid ${col}44">${d.type}</span>`;
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
      if(tgts.length>8) h+=`<span style="color:var(--dim);font-size:.59rem">+${tgts.length-8}…</span>`;
      h+=`</div>`;
    }
    h+=`</span></div>`;
  }
  if(inE.length){
    const srcs=[...new Set(inE.map(ed=>ed.source))].slice(0,8);
    h+=`<div class="irow"><span class="ikey">Référencé par (${inE.length})</span><span class="ival">`;
    for(const s of srcs){ const sn=onto.nodes.find(n=>n.id===s); h+=`<a class="ilink" onclick="jumpTo('${ea(s)}')">${e(sn?sn.label:s)}</a>`; }
    if(inE.length>8) h+=`<span style="color:var(--dim);font-size:.59rem">+${inE.length-8}…</span>`;
    h+=`</span></div>`;
  }
  h+=`<div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:8px">
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
  t.innerHTML=`<strong style="color:${col}">${e(d.label)}</strong><br><span style="color:var(--dim)">${d.type}</span>${d.comment?`<br><em style="color:var(--dim);font-size:.59rem">${e(d.comment.substring(0,90))}${d.comment.length>90?'…':''}</em>`:''}`;
  t.style.opacity=1;
  const ca=document.getElementById('ca'),rect=ca.getBoundingClientRect();
  t.style.left=Math.min(ev.clientX-rect.left+12,rect.width-230)+'px';
  t.style.top=Math.max(ev.clientY-rect.top-10,0)+'px';
}
function showTipRaw(ev,title,sub){
  const t=document.getElementById('tip');
  t.innerHTML=`<strong>${e(title)}</strong><br><span style="color:var(--dim)">${e(sub)}</span>`;
  t.style.opacity=1;
  const ca=document.getElementById('ca'),rect=ca.getBoundingClientRect();
  t.style.left=Math.min(ev.clientX-rect.left+12,rect.width-230)+'px';
  t.style.top=Math.max(ev.clientY-rect.top-10)+'px';
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
// FICHIER / EXPORT / RESET
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
  document.getElementById('ibox').innerHTML='<div style="color:var(--dim);font-size:.68rem;text-align:center;margin-top:50px;line-height:2.3"><div style="font-size:2rem;margin-bottom:8px">⬡</div>Cliquez sur un nœud<br>pour ses détails</div>';
  renderMode();
}
</script>
</body>
</html>
