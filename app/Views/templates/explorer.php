<!-- ══ SIDEBAR ═══════════════════════════════════════════ -->
<div id="sb">

  <!-- Chargement -->
  <div class="sec">
    <div class="st"><?= $i18n['load_file'] ?></div>
    <div class="upz" id="upz">
      <input type="file" id="fin" accept=".owl,.rdf,.rdfs,.xml,.json">
      <div class="ui">📂</div>
      <div class="ut"><b><?= $i18n['choose_file'] ?></b><br>OWL · RDF · RDFS · JSON-LD<br><small>glisser-déposer ou cliquer</small></div>
    </div>
    <div id="flist">
      <?php foreach ($files as $f): ?>
      <div class="fi <?= $f===$currentFile?'act':'' ?>"
           data-file="<?= htmlspecialchars($f, ENT_QUOTES) ?>"
           onclick="selFile(this.dataset.file)">
        <span class="fd"></span><?= htmlspecialchars($f) ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Recherche -->
  <div class="sec">
    <div class="st"><?= $i18n['search_ph'] ?></div>
    <input class="inp" type="text" id="si" placeholder="<?= htmlspecialchars($i18n['search_ph']) ?>">
  </div>

  <!-- Filtres -->
  <div class="sec">
    <div class="st"><?= $i18n['filters'] ?></div>
    <div class="r2">
      <button class="smb" id="btn-all"><?= $i18n['filter_all'] ?></button>
      <button class="smb" id="btn-none"><?= $i18n['filter_none'] ?></button>
    </div>
    <div class="cr" id="chips"></div>
  </div>

  <!-- Affichage -->
  <div class="sec">
    <div class="st"><?= $i18n['relations'] ?></div>
    <div class="tr-row">
      <span><?= $i18n['show_labels'] ?></span>
      <div class="sw2" id="sw-el"></div>
    </div>
  </div>

  <!-- Chemin A→B -->
  <div class="sec">
    <div class="st"><?= $i18n['node_from'] ?> → <?= $i18n['node_to'] ?></div>
    <select class="inp" id="rel-a"></select>
    <select class="inp" id="rel-b"></select>
    <button class="go" onclick="findPath()"><?= $i18n['find_path'] ?></button>
  </div>

  <!-- Chaîne -->
  <div class="sec">
    <div class="st"><?= $i18n['view_chain'] ?></div>
    <select class="inp" id="ch-s"></select>
    <select class="inp" id="ch-r"></select>
    <div class="tr-row" style="margin-bottom:5px">
      <span><?= $i18n['chain_depth'] ?></span>
      <input type="number" class="inp" id="ch-d" value="6" min="1" max="20"
             style="width:52px;text-align:right;padding:3px 6px">
    </div>
    <button class="go alt" onclick="buildChain()"><?= $i18n['show_chain'] ?></button>
  </div>

  <!-- Export -->
  <div class="sec">
    <div class="st"><?= $i18n['export'] ?></div>
    <div class="exr">
      <button class="exb" onclick="doExport('json')">⬇ JSON</button>
      <button class="exb" onclick="doExport('rdf')">⬇ RDF/XML</button>
      <button class="exb" onclick="doExport('csv')">⬇ CSV</button>
    </div>
  </div>

  <!-- Plugin -->
  <div class="sec">
    <div class="st"><?= $i18n['plugin_add'] ?></div>
    <p class="pi">
      Créez <code>app/Plugins/MonPlugin.php</code><br>
      implémentant <code>PluginInterface</code>,<br>
      puis déclarez dans <code>config/plugins.php</code>.
    </p>
  </div>

</div><!-- /sb -->

<!-- ══ MAIN ═════════════════════════════════════════════ -->
<div id="main">

  <!-- Barre de stats -->
  <div id="sbar">
    <span><?= $i18n['classes'] ?><b class="sv" id="sc">—</b></span>
    <span><?= $i18n['properties'] ?><b class="sv" id="sp">—</b></span>
    <span><?= $i18n['edges'] ?><b class="sv" id="se">—</b></span>
    <div id="spin"></div>
    <span id="fbg"><?= htmlspecialchars($currentFile ?? $i18n['no_file']) ?></span>
  </div>

  <!-- Zone de vues -->
  <div id="va">

    <!-- Vue Graphe -->
    <div class="view on" id="graph-view">
      <svg id="gsv"></svg>
      <div class="zc">
        <button class="zb" id="z-in"  title="Zoom +">+</button>
        <button class="zb" id="z-rst" title="<?= htmlspecialchars($i18n['zoom_reset']) ?>">⌖</button>
        <button class="zb" id="z-out" title="Zoom −">−</button>
      </div>
    </div>

    <!-- Vue Hiérarchie -->
    <div class="view" id="hierarchy-view">
      <div class="emp">
        <div class="ei">🌲</div>
        <p><?= htmlspecialchars($i18n['no_file']) ?></p>
      </div>
    </div>

    <!-- Vue Radiale -->
    <div class="view" id="radial-view">
      <svg id="rsv" style="width:100%;height:100%"></svg>
    </div>

    <!-- Vue Chaîne -->
    <div class="view" id="chain-view">
      <div class="chw" id="ch-ct">
        <p style="color:var(--td);font-size:.63rem">
          Utilisez «&nbsp;<?= $i18n['find_path'] ?>&nbsp;» ou «&nbsp;<?= $i18n['show_chain'] ?>&nbsp;».
        </p>
      </div>
    </div>

    <!-- Vue Code -->
    <div class="view" id="code-view">
      <div style="overflow:auto;height:100%;padding:16px">
        <pre class="cb" id="code-blk"><?= htmlspecialchars($currentFile ? '' : $i18n['no_file']) ?></pre>
      </div>
    </div>

    <!-- Tooltip -->
    <div id="tip"></div>

  </div><!-- /va -->
</div><!-- /main -->
