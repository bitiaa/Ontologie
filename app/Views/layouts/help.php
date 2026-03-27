<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($i18n['help_title']) ?> — <?= htmlspecialchars($i18n['app_title']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@300;400;500&family=Clash+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --bg:#080c14; --bg2:#0d1320; --bg3:#141b2d; --bg4:#1a2238;
  --accent:#c8ff00; --accent2:#00ffe0; --accent3:#ff4d6d;
  --text:#c8d4e8; --text-dim:#4a5568; --border:#1e2a40; --border2:#2d3f5e;
  --font-mono:'DM Mono',monospace; --font-disp:'Clash Display','DM Mono',sans-serif;
  --radius:8px;
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
body{font-family:var(--font-mono);background:var(--bg);color:var(--text);min-height:100vh;}
#header{height:52px;background:var(--bg2);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 18px;gap:16px;}
.logo-mark{font-family:var(--font-disp);font-weight:700;font-size:1rem;color:var(--accent);display:flex;align-items:center;gap:8px;}
.logo-mark::before{content:'';width:22px;height:22px;background:var(--accent);clip-path:polygon(50% 0%,100% 38%,82% 100%,18% 100%,0% 38%);flex-shrink:0;}
.hdr-btn{background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius);color:var(--text-dim);font-family:var(--font-mono);font-size:.6rem;padding:5px 10px;cursor:pointer;text-transform:uppercase;letter-spacing:1px;transition:all .2s;text-decoration:none;display:inline-flex;align-items:center;gap:5px;}
.hdr-btn:hover{border-color:var(--accent);color:var(--accent);}
.hdr-tools{margin-left:auto;display:flex;align-items:center;gap:8px;}
::-webkit-scrollbar{width:4px;} ::-webkit-scrollbar-track{background:var(--bg);} ::-webkit-scrollbar-thumb{background:var(--border2);border-radius:2px;}
</style>
</head>
<body>
<div id="header">
  <div class="logo-mark"><?= htmlspecialchars($i18n['app_title']) ?></div>
  <div class="hdr-tools">
    <a href="/lang/fr" class="hdr-btn <?= ($lang==='fr'?'lang-active':'') ?>"><?= $i18n['lang_fr'] ?></a>
    <a href="/lang/en" class="hdr-btn <?= ($lang==='en'?'lang-active':'') ?>"><?= $i18n['lang_en'] ?></a>
    <a href="/" class="hdr-btn">← <?= $i18n['help_back'] ?></a>
  </div>
</div>
<?php require APP_PATH . '/Views/templates/' . $template . '.php'; ?>
</body>
</html>
