<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($i18n['app_title']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:ital,wght@0,300;0,400;0,500;1,400&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/d3/7.8.5/d3.min.js"></script>
<style>
/* ═══ TOKENS ═══════════════════════════════════════════════ */
:root {
  --bg:#07090f;--bg2:#0c1018;--bg3:#111622;--bg4:#18202e;
  --ac:#b8ff00;--ac2:#00f5d4;--ac3:#ff3f5e;
  --tx:#bfcfe8;--td:#3d4f68;--br:#161e2e;--br2:#263248;
  --mn:'DM Mono',monospace;--dp:'Syne','DM Mono',sans-serif;
  --r:7px;--sw:262px
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%;overflow:hidden}
body{font-family:var(--mn);background:var(--bg);color:var(--tx);font-size:12px;line-height:1.5}
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-track{background:var(--bg)}
::-webkit-scrollbar-thumb{background:var(--br2);border-radius:2px}

/* HEADER */
#hdr{height:50px;background:var(--bg2);border-bottom:1px solid var(--br);display:flex;align-items:center;padding:0 16px;gap:14px;position:relative;z-index:30;flex-shrink:0}
.logo{font-family:var(--dp);font-weight:800;font-size:.95rem;color:var(--ac);letter-spacing:-.3px;display:flex;align-items:center;gap:9px;white-space:nowrap}
.logo-hex{width:20px;height:20px;background:var(--ac);clip-path:polygon(50% 0%,100% 25%,100% 75%,50% 100%,0% 75%,0% 25%);flex-shrink:0}
.logo-sub{font-size:.55rem;color:var(--td);text-transform:uppercase;letter-spacing:2px;border-left:1px solid var(--br2);padding-left:10px}
#tabs{display:flex;gap:1px;margin-left:10px}
.tb{background:transparent;border:none;border-bottom:2px solid transparent;color:var(--td);font-family:var(--mn);font-size:.58rem;font-weight:500;padding:6px 12px;cursor:pointer;text-transform:uppercase;letter-spacing:1.5px;transition:color .18s,border-color .18s;white-space:nowrap}
.tb:hover{color:var(--tx)}.tb.on{color:var(--ac);border-bottom-color:var(--ac)}
.hdr-r{margin-left:auto;display:flex;align-items:center;gap:6px}
.hb{background:var(--bg3);border:1px solid var(--br2);border-radius:var(--r);color:var(--td);font-family:var(--mn);font-size:.57rem;padding:4px 9px;cursor:pointer;text-transform:uppercase;letter-spacing:1px;transition:all .18s;white-space:nowrap;text-decoration:none;display:inline-flex;align-items:center;gap:4px}
.hb:hover{border-color:var(--ac);color:var(--ac)}.hb.act{border-color:var(--ac2);color:var(--ac2)}

/* APP SHELL */
#app{display:flex;height:calc(100vh - 50px);overflow:hidden}

/* SIDEBAR */
#sb{width:var(--sw);min-width:var(--sw);background:var(--bg2);border-right:1px solid var(--br);display:flex;flex-direction:column;overflow-y:auto;overflow-x:hidden}
.sec{padding:12px 14px;border-bottom:1px solid var(--br)}
.st{font-family:var(--dp);font-size:.55rem;text-transform:uppercase;letter-spacing:2px;color:var(--td);margin-bottom:9px}

/* upload */
.upz{border:1.5px dashed var(--br2);border-radius:var(--r);padding:12px;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;position:relative;overflow:hidden}
.upz:hover,.upz.ov{border-color:var(--ac);background:rgba(184,255,0,.04)}
.upz input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.ui{font-size:1.4rem;margin-bottom:5px}.ut{font-size:.58rem;color:var(--td);line-height:1.6}.ut b{color:var(--ac)}
#flist{margin-top:8px;display:flex;flex-direction:column;gap:3px}
.fi{display:flex;align-items:center;gap:6px;padding:5px 8px;border-radius:5px;border:1px solid var(--br);font-size:.58rem;cursor:pointer;transition:all .15s;color:var(--td);background:var(--bg3)}
.fi:hover{border-color:var(--br2);color:var(--tx)}.fi.act{border-color:var(--ac);color:var(--ac);background:rgba(184,255,0,.05)}
.fd{width:5px;height:5px;border-radius:50%;background:var(--ac2);flex-shrink:0}

/* inputs */
.inp{width:100%;background:var(--bg);border:1px solid var(--br);border-radius:var(--r);padding:6px 9px;color:var(--tx);font-family:var(--mn);font-size:.62rem;outline:none;transition:border-color .2s}
.inp:focus{border-color:var(--ac)}.inp::placeholder{color:var(--td)}
select.inp{cursor:pointer;-webkit-appearance:none;margin-bottom:5px}
select.inp option{background:var(--bg2)}

/* chips */
.cr{display:flex;flex-wrap:wrap;gap:4px}
.cp{display:inline-flex;align-items:center;gap:3px;padding:3px 7px;border-radius:20px;border:1px solid var(--br2);font-size:.54rem;cursor:pointer;transition:all .18s;font-family:var(--mn);color:var(--td);background:var(--bg3);user-select:none}
.cp.on{background:rgba(0,0,0,.2)}.cpd{width:5px;height:5px;border-radius:50%;flex-shrink:0}
.r2{display:flex;gap:4px;margin-bottom:7px}
.smb{flex:1;background:var(--bg);border:1px solid var(--br);border-radius:5px;padding:4px 0;color:var(--td);font-family:var(--mn);font-size:.54rem;cursor:pointer;text-transform:uppercase;letter-spacing:1px;transition:all .18s}
.smb:hover{border-color:var(--ac2);color:var(--ac2)}

/* toggle */
.tr-row{display:flex;align-items:center;justify-content:space-between;padding:4px 0;font-size:.6rem;color:var(--td)}
.sw2{width:28px;height:15px;background:var(--bg);border:1px solid var(--br2);border-radius:10px;position:relative;cursor:pointer;transition:background .2s,border-color .2s;flex-shrink:0}
.sw2.on{background:var(--ac);border-color:var(--ac)}
.sw2::after{content:'';position:absolute;width:9px;height:9px;background:#fff;border-radius:50%;top:2px;left:2px;transition:transform .2s}
.sw2.on::after{transform:translateX(13px)}

/* buttons */
.go{width:100%;background:var(--ac);border:none;border-radius:var(--r);padding:7px;color:var(--bg);font-family:var(--mn);font-size:.6rem;font-weight:500;cursor:pointer;text-transform:uppercase;letter-spacing:1px;transition:opacity .18s;margin-top:3px}
.go:hover{opacity:.85}.go.alt{background:transparent;border:1px solid var(--ac2);color:var(--ac2)}.go.alt:hover{background:rgba(0,245,212,.08);opacity:1}
.exr{display:flex;gap:3px;flex-wrap:wrap}
.exb{flex:1;min-width:55px;background:var(--bg);border:1px solid var(--br2);border-radius:var(--r);padding:5px 3px;color:var(--td);font-family:var(--mn);font-size:.54rem;cursor:pointer;text-align:center;text-transform:uppercase;letter-spacing:1px;transition:all .18s}
.exb:hover{border-color:var(--ac);color:var(--ac)}
.pi{font-size:.57rem;color:var(--td);line-height:1.7}.pi code{color:var(--ac2);background:var(--bg3);padding:1px 4px;border-radius:3px}

/* MAIN */
#main{flex:1;display:flex;flex-direction:column;overflow:hidden;position:relative}
#sbar{display:flex;align-items:center;gap:12px;padding:4px 14px;background:var(--bg2);border-bottom:1px solid var(--br);font-size:.54rem;color:var(--td);text-transform:uppercase;letter-spacing:1px;flex-shrink:0}
.sv{color:var(--ac2);font-weight:500;margin-left:3px}
#fbg{margin-left:auto;background:var(--bg3);border:1px solid var(--br2);border-radius:4px;padding:2px 7px;font-size:.54rem;color:var(--ac)}
#spin{width:12px;height:12px;border:2px solid var(--br2);border-top-color:var(--ac);border-radius:50%;animation:sp .6s linear infinite;display:none}
@keyframes sp{to{transform:rotate(360deg)}}
#va{flex:1;position:relative;overflow:hidden}
.view{display:none;position:absolute;inset:0}.view.on{display:flex}

/* graph */
#graph-view{flex-direction:column}
#gsv{flex:1;width:100%;cursor:grab}#gsv:active{cursor:grabbing}
.lnk{stroke-opacity:.35}.lnk.hi{stroke-opacity:1;stroke-width:2px}
.nd circle{cursor:pointer;transition:filter .15s}
.nd circle:hover{filter:brightness(1.4) drop-shadow(0 0 5px currentColor)}
.nd text{pointer-events:none}
.el{font-size:8px;fill:var(--td);pointer-events:none;font-family:'DM Mono',monospace}

/* hierarchy */
#hierarchy-view{overflow:auto;padding:18px}
.htr{list-style:none}.htn{list-style:none;margin:1px 0}
.htl{display:inline-flex;align-items:center;gap:5px;padding:4px 10px 4px 5px;border-radius:5px;font-size:.62rem;cursor:pointer;transition:background .13s;white-space:nowrap}
.htl:hover{background:var(--bg3)}.htt{color:var(--td);font-size:.68rem;width:13px;text-align:center;flex-shrink:0}
.htd{width:7px;height:7px;border-radius:50%;flex-shrink:0}.htn2{font-weight:500}
.htc{color:var(--td);font-size:.55rem;margin-left:4px;overflow:hidden;text-overflow:ellipsis;max-width:380px}
.hch{padding-left:18px;border-left:1px solid var(--br);margin-left:12px}.hch.cl{display:none}

/* radial */
#radial-view{flex-direction:column}
#rsv{flex:1;width:100%}

/* chain */
#chain-view{flex-direction:column;align-items:center;justify-content:center;padding:30px 20px;overflow:auto}
.chw{display:flex;align-items:flex-start;flex-wrap:wrap;justify-content:center;gap:0;max-width:1200px}
.chs{display:flex;flex-direction:column;align-items:center}
.chn{background:var(--bg2);border:1.5px solid var(--br2);border-radius:10px;padding:11px 16px;text-align:center;min-width:105px;animation:fu .28s ease}
.cnt{font-size:.47rem;text-transform:uppercase;letter-spacing:1.5px;color:var(--td);margin-bottom:3px}
.cnn{font-family:var(--dp);font-size:.82rem;font-weight:700}
.cha{color:var(--ac);font-size:1.3rem;padding:0 4px;align-self:center}
.chr{font-size:.5rem;color:var(--ac2);text-transform:uppercase;letter-spacing:1px;margin-bottom:3px}
@keyframes fu{from{opacity:0;transform:translateY(7px)}to{opacity:1;transform:translateY(0)}}

/* code */
#code-view{overflow:auto;padding:18px}
pre.cb{background:var(--bg2);border:1px solid var(--br);border-radius:9px;padding:16px;font-family:var(--mn);font-size:.6rem;line-height:1.8;color:var(--tx);white-space:pre;overflow:auto;min-height:200px}
.ck{color:var(--ac)}.cs{color:var(--ac2)}.cv{color:var(--ac3)}

/* tooltip */
#tip{position:absolute;background:var(--bg2);border:1px solid var(--br2);border-radius:8px;padding:9px 13px;font-size:.6rem;pointer-events:none;opacity:0;transition:opacity .13s;z-index:200;max-width:230px;box-shadow:0 4px 24px rgba(0,0,0,.6)}
#tip.on{opacity:1}
.tth{font-family:var(--dp);font-weight:700;color:var(--ac);margin-bottom:5px;font-size:.7rem}
.ttr{display:flex;justify-content:space-between;gap:10px;padding:2px 0;border-top:1px solid var(--br)}
.ttk{color:var(--td)}.ttv{color:var(--tx);font-weight:500;text-align:right;max-width:140px;overflow:hidden;text-overflow:ellipsis}

/* zoom */
.zc{position:absolute;bottom:14px;right:14px;display:flex;flex-direction:column;gap:3px;z-index:20}
.zb{width:28px;height:28px;background:var(--bg2);border:1px solid var(--br2);border-radius:6px;color:var(--tx);font-size:.9rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .18s}
.zb:hover{border-color:var(--ac);color:var(--ac)}

/* empty */
.emp{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:10px;color:var(--td)}
.emp .ei{font-size:2.5rem;opacity:.25}.emp p{font-size:.66rem}

/* toast */
#toast{position:fixed;bottom:20px;left:50%;transform:translateX(-50%) translateY(36px);background:var(--bg3);border:1px solid var(--br2);border-radius:7px;padding:8px 18px;font-size:.63rem;color:var(--tx);z-index:999;pointer-events:none;opacity:0;transition:opacity .22s,transform .22s}
#toast.on{opacity:1;transform:translateX(-50%) translateY(0)}
#toast.ok{border-color:var(--ac);color:var(--ac)}#toast.err{border-color:var(--ac3);color:var(--ac3)}
</style>
</head>
<body>

<div id="hdr">
  <div class="logo"><span class="logo-hex"></span><?= htmlspecialchars($i18n['app_title']) ?></div>
  <span class="logo-sub"><?= htmlspecialchars($i18n['app_subtitle']) ?></span>
  <nav id="tabs">
    <button class="tb on"  data-v="graph"    ><?= $i18n['view_graph'] ?></button>
    <button class="tb"     data-v="hierarchy"><?= $i18n['view_hierarchy'] ?></button>
    <button class="tb"     data-v="radial"   ><?= $i18n['view_radial'] ?></button>
    <button class="tb"     data-v="chain"    ><?= $i18n['view_chain'] ?></button>
    <button class="tb"     data-v="code"     ><?= $i18n['view_code'] ?></button>
  </nav>
  <div class="hdr-r">
    <a href="/lang/fr" class="hb <?= $lang==='fr'?'act':'' ?>">FR</a>
    <a href="/lang/en" class="hb <?= $lang==='en'?'act':'' ?>">EN</a>
    <a href="/help" class="hb" target="_blank">? Aide</a>
    <a href="https://github.com/your-org/ontology-explorer" class="hb" target="_blank" rel="noopener">⎇ Git</a>
  </div>
</div>

<div id="app">
<?php require APP_PATH . '/Views/templates/' . $template . '.php'; ?>
</div>

<div id="toast"></div>

<script>
/* ════════════════════════════════════════════════════════════
   STATE
════════════════════════════════════════════════════════════ */
const I18N      = <?= json_encode($i18n, JSON_UNESCAPED_UNICODE) ?>;
const INIT_FILE = <?= json_encode($currentFile ?? null) ?>;
let gData       = null;
let curV        = 'graph';
let showEL      = false;
let actGrps     = new Set();
let searchQ     = '';
let gSim, gLinks, gNodes, gEL, gZoom;

/* ════ Toast ════════════════════════════════════════════════ */
function toast(msg, type='ok') {
  const t = document.getElementById('toast');
  t.textContent = msg; t.className = 'on ' + type;
  clearTimeout(t._t); t._t = setTimeout(() => t.className='', 2800);
}

/* ════ Spinner ══════════════════════════════════════════════ */
function spin(on) { document.getElementById('spin').style.display = on?'block':'none'; }

/* ════ Tabs ═════════════════════════════════════════════════ */
document.querySelectorAll('.tb').forEach(b => b.addEventListener('click', () => {
  document.querySelectorAll('.tb').forEach(x => x.classList.remove('on'));
  b.classList.add('on'); curV = b.dataset.v;
  document.querySelectorAll('.view').forEach(v => v.classList.remove('on'));
  document.getElementById(curV+'-view').classList.add('on');
  renderV();
}));

/* ════ File selection ═══════════════════════════════════════ */
function selFile(name) {
  document.querySelectorAll('.fi').forEach(e => e.classList.toggle('act', e.dataset.file===name));
  loadOnto(name);
}

/* ════ Load ontology ════════════════════════════════════════ */
async function loadOnto(name) {
  spin(true);
  try {
    const r = await fetch('/api/graph?file='+enc(name));
    const d = await r.json();
    if (d.error) { toast(d.error,'err'); spin(false); return; }
    gData = d;
    actGrps = new Set(d.nodes.map(n => n.group||'class'));
    buildChips(); updateStats(d.stats);
    document.getElementById('fbg').textContent = name;
    popSels(); renderV();
    toast(I18N.loaded+': '+name);
  } catch(e) { toast(e.message,'err'); }
  spin(false);
}

/* ════ Render dispatcher ════════════════════════════════════ */
function renderV() {
  if (!gData) return;
  if (curV==='graph')     renderGraph();
  if (curV==='hierarchy') renderHier();
  if (curV==='radial')    renderRadial();
  if (curV==='code')      renderCode();
}

/* ════ Stats ════════════════════════════════════════════════ */
function updateStats(s) {
  if (!s) return;
  document.getElementById('sc').textContent = s.classCount    || 0;
  document.getElementById('sp').textContent = s.propertyCount || 0;
  document.getElementById('se').textContent = s.edgeCount     || 0;
}

/* ════ Chips ════════════════════════════════════════════════ */
function buildChips() {
  if (!gData) return;
  const grps = [...new Set(gData.nodes.map(n=>n.group||'class'))];
  document.getElementById('chips').innerHTML = grps.map(g => {
    const on  = actGrps.has(g);
    const col = gData.nodes.find(n=>(n.group||'class')===g)?.color||'#888';
    return `<span class="cp ${on?'on':''}" data-g="${g}"
      style="${on?`color:${col};border-color:${col}`:''}"
      onclick="togGrp('${g}','${col}')">
      <span class="cpd" style="background:${col}"></span>${g}</span>`;
  }).join('');
}
function togGrp(g) {
  actGrps.has(g) ? actGrps.delete(g) : actGrps.add(g);
  buildChips(); if (curV==='graph') renderGraph();
}
document.getElementById('btn-all').addEventListener('click', () => {
  if (!gData) return;
  actGrps = new Set(gData.nodes.map(n=>n.group||'class'));
  buildChips(); renderGraph();
});
document.getElementById('btn-none').addEventListener('click', () => {
  actGrps.clear(); buildChips(); renderGraph();
});

/* ════ Search ═══════════════════════════════════════════════ */
document.getElementById('si').addEventListener('input', function() {
  searchQ = this.value.toLowerCase();
  if (curV==='graph') renderGraph();
});

/* ════ Edge labels toggle ═══════════════════════════════════ */
document.getElementById('sw-el').addEventListener('click', function() {
  showEL = !showEL; this.classList.toggle('on', showEL);
  if (curV==='graph')  renderGraph();
  if (curV==='radial') renderRadial();
});

/* ════ Filtered helpers ══════════════════════════════════════ */
function fNodes() {
  if (!gData) return [];
  return gData.nodes.filter(n =>
    actGrps.has(n.group||'class') &&
    (!searchQ || n.label.toLowerCase().includes(searchQ) || n.id.toLowerCase().includes(searchQ))
  );
}
function fEdges(ids) {
  if (!gData) return [];
  const s = new Set(ids);
  return gData.edges.filter(e => s.has(e.source?.id??e.source) && s.has(e.target?.id??e.target));
}

/* ════════════════════════════════════════════════════════════
   GRAPH  (D3 force-directed)
════════════════════════════════════════════════════════════ */
function renderGraph() {
  const svg = d3.select('#gsv');
  svg.selectAll('*').remove();
  const el = document.getElementById('gsv');
  const W  = el.clientWidth  || 900;
  const H  = el.clientHeight || 580;
  svg.attr('viewBox', `0 0 ${W} ${H}`);

  const nodes   = fNodes().map(d=>({...d}));
  const nodeIds = new Set(nodes.map(n=>n.id));
  const rawE    = fEdges([...nodeIds]);

  if (!nodes.length) {
    svg.append('text').attr('x',W/2).attr('y',H/2).attr('text-anchor','middle')
      .attr('fill','var(--td)').attr('font-size',13).text(I18N.no_file);
    return;
  }

  const edges = rawE.map(e=>({
    source: e.source?.id??e.source, target: e.target?.id??e.target,
    relation: e.relation, label: e.label||e.relation
  }));

  const root  = svg.append('g');
  gZoom = d3.zoom().scaleExtent([.05,10]).on('zoom', ev=>root.attr('transform',ev.transform));
  svg.call(gZoom);

  // Arrow markers
  const defs = svg.append('defs');
  [...new Set(edges.map(e=>e.relation))].forEach(rel => {
    const col = rCol(rel), mid = 'a'+rel.replace(/[^a-z0-9]/gi,'');
    defs.append('marker').attr('id',mid).attr('viewBox','0 -4 8 8')
      .attr('refX',20).attr('refY',0).attr('markerWidth',6).attr('markerHeight',6).attr('orient','auto')
      .append('path').attr('d','M0,-4L8,0L0,4').attr('fill',col).attr('opacity',.75);
  });

  // Links
  gLinks = root.append('g').selectAll('line').data(edges).enter().append('line')
    .attr('class','lnk').attr('stroke',d=>rCol(d.relation)).attr('stroke-width',1.2)
    .attr('stroke-opacity',.35).attr('marker-end',d=>`url(#a${d.relation.replace(/[^a-z0-9]/gi,'')})`);

  // Edge labels
  gEL = root.append('g').selectAll('text').data(edges).enter().append('text')
    .attr('class','el').attr('text-anchor','middle').attr('opacity',showEL?.9:0).text(d=>d.label);

  // Nodes
  gNodes = root.append('g').selectAll('g').data(nodes,d=>d.id).enter().append('g').attr('class','nd')
    .call(d3.drag()
      .on('start',(e,d)=>{ if(!e.active) gSim.alphaTarget(.3).restart(); d.fx=d.x; d.fy=d.y; })
      .on('drag', (e,d)=>{ d.fx=e.x; d.fy=e.y; })
      .on('end',  (e,d)=>{ if(!e.active) gSim.alphaTarget(0); d.fx=null; d.fy=null; })
    );

  gNodes.append('circle').attr('r',9).attr('fill',d=>d.color||'#888')
    .attr('stroke','#07090f').attr('stroke-width',1.5)
    .on('mouseover',(ev,d)=>{ tipShow(ev,d); hlNode(d); })
    .on('mousemove', tipMove).on('mouseout',()=>{ tipHide(); hlReset(); });

  gNodes.append('text').attr('dy',-13).attr('text-anchor','middle')
    .attr('fill','var(--tx)').attr('font-size',8).attr('font-family','DM Mono,monospace')
    .text(d=>d.label.length>20?d.label.slice(0,18)+'…':d.label);

  gSim = d3.forceSimulation(nodes)
    .force('link',  d3.forceLink(edges).id(d=>d.id).distance(85))
    .force('charge',d3.forceManyBody().strength(-220))
    .force('center',d3.forceCenter(W/2,H/2))
    .force('coll',  d3.forceCollide(22))
    .on('tick',()=>{
      gLinks.attr('x1',d=>d.source.x).attr('y1',d=>d.source.y)
            .attr('x2',d=>d.target.x).attr('y2',d=>d.target.y);
      gEL.attr('x',d=>(d.source.x+d.target.x)/2).attr('y',d=>(d.source.y+d.target.y)/2-4);
      gNodes.attr('transform',d=>`translate(${d.x},${d.y})`);
    });
}

document.getElementById('z-in').onclick  = ()=>d3.select('#gsv').transition().call(gZoom?.scaleBy,1.45);
document.getElementById('z-out').onclick = ()=>d3.select('#gsv').transition().call(gZoom?.scaleBy,.7);
document.getElementById('z-rst').onclick = ()=>d3.select('#gsv').transition().call(gZoom?.transform,d3.zoomIdentity);

/* ════════════════════════════════════════════════════════════
   HIERARCHY  (collapsible subClassOf tree)
════════════════════════════════════════════════════════════ */
async function renderHier() {
  const f = actFile(); if (!f) return;
  const r = await fetch('/api/hierarchy?file='+enc(f));
  const d = await r.json();
  if (d.error) { toast(d.error,'err'); return; }

  function nd(n) {
    const k = n.children?.length>0;
    return `<li class="htn">
      <div class="htl" onclick="togNd(this)">
        <span class="htt">${k?'▸':'·'}</span>
        <span class="htd" style="background:${n.color||'#888'}"></span>
        <span class="htn2" style="color:${n.color||'var(--tx)'}">${esc(n.label)}</span>
        ${n.comment?`<span class="htc" title="${esc(n.comment)}">${esc(n.comment.slice(0,55))}${n.comment.length>55?'…':''}</span>`:''}
      </div>
      ${k?`<ul class="hch cl">${n.children.map(nd).join('')}</ul>`:''}
    </li>`;
  }
  document.getElementById('hierarchy-view').innerHTML =
    `<ul class="htr">${d.tree.map(nd).join('')}</ul>`;
}

function togNd(el) {
  const ul = el.nextElementSibling; if (!ul) return;
  const op = ul.classList.toggle('cl');
  el.querySelector('.htt').textContent = !op?'▾':'▸';
}

/* ════════════════════════════════════════════════════════════
   RADIAL  (D3 radial tree)
════════════════════════════════════════════════════════════ */
async function renderRadial() {
  const f = actFile(); if (!f) return;
  const r = await fetch('/api/hierarchy?file='+enc(f));
  const d = await r.json();
  if (d.error) { toast(d.error,'err'); return; }
  if (!d.tree?.length) { toast('Aucune hiérarchie trouvée','err'); return; }

  const svg = d3.select('#rsv'); svg.selectAll('*').remove();
  const el  = document.getElementById('rsv');
  const W   = el.clientWidth||900, H = el.clientHeight||580;
  const R   = Math.min(W,H)/2 - 70;
  svg.attr('viewBox',`${-W/2} ${-H/2} ${W} ${H}`);

  const root = d3.hierarchy({ id:'__r',label:'',color:'',children:d.tree });
  d3.tree().size([2*Math.PI, R]).separation((a,b)=>(a.parent===b.parent?1:2)/a.depth)(root);

  const zg   = svg.append('g');
  const zoom = d3.zoom().scaleExtent([.05,10]).on('zoom',ev=>zg.attr('transform',ev.transform));
  svg.call(zoom);

  zg.append('g').attr('fill','none').attr('stroke','var(--br2)').attr('stroke-opacity',.55).attr('stroke-width',1)
    .selectAll('path').data(root.links().filter(l=>l.source.data.id!=='__r'))
    .enter().append('path').attr('d',d3.linkRadial().angle(d=>d.x).radius(d=>d.y));

  const node = zg.append('g').selectAll('g')
    .data(root.descendants().filter(d=>d.data.id!=='__r'))
    .enter().append('g')
    .attr('transform',d=>`rotate(${d.x*180/Math.PI-90}) translate(${d.y},0)`);

  node.append('circle').attr('r',5).attr('fill',d=>d.data.color||'#888')
    .attr('stroke','#07090f').attr('stroke-width',1.5)
    .on('mouseover',(ev,d)=>tipShowR(ev,d.data.label,d.data.comment||''))
    .on('mousemove',tipMove).on('mouseout',tipHide);

  node.append('text').attr('dy','0.31em')
    .attr('x',d=>d.x<Math.PI===!d.children?9:-9)
    .attr('text-anchor',d=>d.x<Math.PI===!d.children?'start':'end')
    .attr('transform',d=>d.x>=Math.PI?'rotate(180)':null)
    .text(d=>d.data.label).attr('fill','var(--tx)').attr('font-size',8)
    .attr('font-family','DM Mono,monospace').attr('pointer-events','none');
}

/* ════════════════════════════════════════════════════════════
   CODE VIEW
════════════════════════════════════════════════════════════ */
function renderCode() {
  if (!gData) return;
  const raw = JSON.stringify(gData, null, 2);
  document.getElementById('code-blk').innerHTML = raw
    .replace(/(\"[\w@:#\/-]+\")\s*:/g,'<span class="ck">$1</span>:')
    .replace(/:\s*(\"([^\"]*)\")/g,': <span class="cs">$1</span>')
    .replace(/:\s*(\d+(?:\.\d+)?)/g,': <span class="cv">$1</span>');
}

/* ════════════════════════════════════════════════════════════
   PATH  (BFS server-side)
════════════════════════════════════════════════════════════ */
async function findPath() {
  const a=document.getElementById('rel-a').value;
  const b=document.getElementById('rel-b').value;
  const f=actFile();
  if (!a||!b||a===b||!f) return;
  spin(true);
  const r=await fetch(`/api/path?from=${enc(a)}&to=${enc(b)}&file=${enc(f)}`);
  const d=await r.json(); spin(false);
  if (d.error||!d.path) { toast(I18N.no_path,'err'); return; }
  renderChain(d.path.map(p=>({...p.node,chainRel:p.relation})));
  switchTo('chain');
}

/* ════════════════════════════════════════════════════════════
   CHAIN
════════════════════════════════════════════════════════════ */
async function buildChain() {
  const s=document.getElementById('ch-s').value;
  const rel=document.getElementById('ch-r').value;
  const dep=document.getElementById('ch-d').value;
  const f=actFile();
  if (!s||!f) return;
  spin(true);
  const r=await fetch(`/api/chain?start=${enc(s)}&relation=${enc(rel)}&depth=${dep}&file=${enc(f)}`);
  const d=await r.json(); spin(false);
  if (d.error) { toast(d.error,'err'); return; }
  renderChain(d.chain); switchTo('chain');
}

function renderChain(chain) {
  if (!chain?.length) {
    document.getElementById('ch-ct').innerHTML=`<p style="color:var(--td);font-size:.63rem">${I18N.no_path}</p>`;
    return;
  }
  document.getElementById('ch-ct').innerHTML = chain.map((n,i)=>`
    ${i>0?'<div class="cha">→</div>':''}
    <div class="chs">
      ${n.chainRel?`<div class="chr">${esc(n.chainRel)}</div>`:''}
      <div class="chn" style="border-color:${n.color||'#444'};box-shadow:0 0 12px ${n.color||'#444'}33">
        <div class="cnt">class</div>
        <div class="cnn" style="color:${n.color||'var(--tx)'}">${esc(n.label||n.id)}</div>
      </div>
    </div>`).join('');
}

/* ════════════════════════════════════════════════════════════
   SELECTS
════════════════════════════════════════════════════════════ */
function popSels() {
  if (!gData) return;
  const sorted=[...gData.nodes].sort((a,b)=>a.label.localeCompare(b.label));
  const opts=sorted.map(n=>`<option value="${escA(n.id)}">${esc(n.label)}</option>`).join('');
  ['rel-a','rel-b','ch-s'].forEach(id=>{ const el=document.getElementById(id); if(el) el.innerHTML=opts; });
  const rb=document.getElementById('rel-b'); if(rb&&rb.options.length>1) rb.selectedIndex=1;
  const rels=[...new Set(gData.edges.map(e=>e.relation))].sort();
  const cr=document.getElementById('ch-r');
  if(cr) cr.innerHTML=`<option value="">— ${I18N.relations} —</option>`+rels.map(r=>`<option value="${escA(r)}">${esc(r)}</option>`).join('');
}

/* ════════════════════════════════════════════════════════════
   UPLOAD
════════════════════════════════════════════════════════════ */
const upzEl=document.getElementById('upz');
const finEl=document.getElementById('fin');
upzEl.addEventListener('dragover',e=>{ e.preventDefault(); upzEl.classList.add('ov'); });
upzEl.addEventListener('dragleave',()=>upzEl.classList.remove('ov'));
upzEl.addEventListener('drop',e=>{ e.preventDefault(); upzEl.classList.remove('ov'); uploadF(e.dataTransfer.files[0]); });
finEl.addEventListener('change',()=>uploadF(finEl.files[0]));

async function uploadF(file) {
  if (!file) return;
  const fd=new FormData(); fd.append('ontology',file);
  try {
    const r=await fetch('/upload',{method:'POST',body:fd});
    const d=await r.json();
    if (d.error) { toast(d.error,'err'); return; }
    addToList(d.filename); selFile(d.filename);
  } catch(e) { toast(I18N.upload_error,'err'); }
}

function addToList(name) {
  const list=document.getElementById('flist');
  list.querySelectorAll('.fi').forEach(el=>{ if(el.dataset.file===name) el.remove(); });
  const el=document.createElement('div');
  el.className='fi'; el.dataset.file=name;
  el.innerHTML=`<span class="fd"></span>${esc(name)}`;
  el.onclick=()=>selFile(name);
  list.prepend(el);
}

/* ════════════════════════════════════════════════════════════
   TOOLTIP
════════════════════════════════════════════════════════════ */
function tipShow(ev,d) {
  const t=document.getElementById('tip');
  t.innerHTML=`<div class="tth">${esc(d.label)}</div>`+
    `<div class="ttr"><span class="ttk">ID</span><span class="ttv">${esc(d.id)}</span></div>`+
    (d.comment?`<div class="ttr"><span class="ttk">Commentaire</span><span class="ttv">${esc(d.comment.slice(0,90))}</span></div>`:'')+
    `<div class="ttr"><span class="ttk">Couleur</span><span class="ttv"><span style="color:${d.color}">■</span> ${d.color}</span></div>`;
  t.classList.add('on'); tipMove(ev);
}
function tipShowR(ev,label,comment) {
  const t=document.getElementById('tip');
  t.innerHTML=`<div class="tth">${esc(label)}</div>`+
    (comment?`<div class="ttr"><span class="ttk">Commentaire</span><span class="ttv">${esc(comment.slice(0,110))}</span></div>`:'');
  t.classList.add('on'); tipMove(ev);
}
function tipMove(ev) {
  const t=document.getElementById('tip');
  const m=document.getElementById('main').getBoundingClientRect();
  t.style.left=(ev.clientX-m.left+14)+'px'; t.style.top=(ev.clientY-m.top-10)+'px';
}
function tipHide() { document.getElementById('tip').classList.remove('on'); }

/* ════════════════════════════════════════════════════════════
   HIGHLIGHT
════════════════════════════════════════════════════════════ */
function hlNode(d) {
  if (!gLinks||!gNodes||!gData) return;
  const linked=new Set(gData.edges
    .filter(e=>(e.source?.id??e.source)===d.id||(e.target?.id??e.target)===d.id)
    .flatMap(e=>[e.source?.id??e.source,e.target?.id??e.target]));
  gNodes.selectAll('circle').attr('opacity',n=>linked.has(n.id)?1:.1);
  gLinks.attr('stroke-opacity',l=>((l.source?.id??l.source)===d.id||(l.target?.id??l.target)===d.id)?1:.04);
}
function hlReset() {
  gNodes?.selectAll('circle').attr('opacity',1);
  gLinks?.attr('stroke-opacity',.35);
}

/* ════════════════════════════════════════════════════════════
   EXPORT
════════════════════════════════════════════════════════════ */
function doExport(fmt) {
  const f=actFile(); if(!f){ toast(I18N.no_file,'err'); return; }
  window.location.href=`/api/export?format=${fmt}&file=${enc(f)}`;
}

/* ════════════════════════════════════════════════════════════
   UTILS
════════════════════════════════════════════════════════════ */
function actFile() { return document.querySelector('.fi.act')?.dataset.file||INIT_FILE; }
function switchTo(v) {
  curV=v;
  document.querySelectorAll('.tb').forEach(b=>b.classList.toggle('on',b.dataset.v===v));
  document.querySelectorAll('.view').forEach(el=>el.classList.toggle('on',el.id===v+'-view'));
}
const _pal=['#5a9fff','#b07aff','#ff9f40','#4bc07b','#f76c82','#34d399','#fb923c','#60a5fa','#f472b6','#00ffe0'];
function rCol(rel){ const h=[...(rel||'x')].reduce((a,c)=>a+c.charCodeAt(0),0); return _pal[h%_pal.length]; }
function esc(s)  { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function escA(s) { return String(s||'').replace(/"/g,'&quot;'); }
function enc(s)  { return encodeURIComponent(s); }

/* ════════════════════════════════════════════════════════════
   INIT
════════════════════════════════════════════════════════════ */
window.addEventListener('DOMContentLoaded', () => {
  if (INIT_FILE) loadOnto(INIT_FILE);
  window.addEventListener('resize', () => {
    if (curV==='graph')  renderGraph();
    if (curV==='radial') renderRadial();
  });
});
</script>
</body>
</html>
