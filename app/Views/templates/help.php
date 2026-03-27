<div style="padding:40px;max-width:860px;margin:0 auto;overflow:auto;height:calc(100vh - 52px)">
<a href="/" class="hdr-btn" style="display:inline-flex;margin-bottom:28px"><?= $i18n['help_back'] ?></a>

<h1 style="font-family:var(--font-disp);font-size:1.6rem;font-weight:700;color:var(--accent);margin-bottom:8px"><?= $i18n['help_title'] ?></h1>
<p style="color:var(--text-dim);font-size:.65rem;margin-bottom:32px;text-transform:uppercase;letter-spacing:2px">Ontology Explorer — Documentation</p>

<style>
.h-section { margin-bottom:32px; }
.h-section h2 { font-family:var(--font-disp); font-size:.9rem; font-weight:600; color:var(--accent2); margin-bottom:10px; padding-bottom:6px; border-bottom:1px solid var(--border); }
.h-section p, .h-section li { font-size:.68rem; color:var(--text); line-height:1.8; }
.h-section ul { padding-left:18px; margin-top:6px; }
.h-section li { margin-bottom:4px; }
.h-section code { color:var(--accent); background:var(--bg3); padding:1px 5px; border-radius:3px; font-size:.62rem; }
.h-table { width:100%; border-collapse:collapse; font-size:.63rem; margin-top:10px; }
.h-table th { text-align:left; padding:6px 10px; background:var(--bg3); color:var(--accent2); font-family:var(--font-disp); font-weight:600; border-bottom:1px solid var(--border2); }
.h-table td { padding:6px 10px; border-bottom:1px solid var(--border); vertical-align:top; }
.h-table td:first-child { color:var(--accent); white-space:nowrap; }
</style>

<div class="h-section">
  <h2>1. Charger une ontologie</h2>
  <p>Glissez-déposez un fichier OWL / RDF / RDFS / JSON-LD dans la zone de dépôt du panneau gauche, ou cliquez dessus pour parcourir votre disque. Le fichier est envoyé sur le serveur (dossier <code>uploads/</code>) et parsé automatiquement.</p>
  <p>Pour changer d'ontologie, cliquez simplement sur un autre fichier dans la liste.</p>
</div>

<div class="h-section">
  <h2>2. Vues disponibles</h2>
  <table class="h-table">
    <tr><th>Onglet</th><th>Description</th></tr>
    <tr><td>Graphe</td><td>Force-directed D3.js. Nœuds = classes, arêtes = relations. Glissez les nœuds, zoomez avec la molette. Les flèches indiquent la direction de la relation. Les noms sur les traits sont activables via le toggle « <?= $i18n['show_labels'] ?> ».</td></tr>
    <tr><td>Hiérarchie</td><td>Arbre expandable basé sur <code>rdfs:subClassOf</code>. Cliquez sur un nœud pour développer/réduire ses enfants.</td></tr>
    <tr><td>Radial</td><td>Vue en coupe radiale (« sunburst » / arbre radial D3). Montre la structure de l'ontologie depuis la racine. Zoomable et pannable.</td></tr>
    <tr><td>Chaîne</td><td>Affiche un chemin entre deux concepts (BFS) ou une chaîne de relations depuis un nœud de départ. Les noms de relations s'affichent sur les arètes.</td></tr>
    <tr><td>Code</td><td>Représentation JSON colorisée du graphe parsé (classes + relations).</td></tr>
  </table>
</div>

<div class="h-section">
  <h2>3. Filtres</h2>
  <p>Les chips colorées dans le panneau filtrent les nœuds par groupe (classe, propriété…). <strong>Tout</strong> / <strong>Aucun</strong> permettent une sélection rapide. La barre de recherche filtre par nom ou ID en temps réel.</p>
</div>

<div class="h-section">
  <h2>4. Relations entre nœuds</h2>
  <p>Sélectionnez deux concepts dans les deux listes déroulantes « <?= $i18n['node_from'] ?> » et « <?= $i18n['node_to'] ?> », puis cliquez <strong><?= $i18n['find_path'] ?></strong>. L'application calcule le chemin le plus court (BFS bidirectionnel) et l'affiche dans la vue Chaîne avec les noms des relations sur chaque étape.</p>
</div>

<div class="h-section">
  <h2>5. Chaîne de relations</h2>
  <p>Choisissez un nœud de départ, une relation (ex. <code>subClassOf</code>, <code>eats</code>…) et une profondeur maximale. L'application remonte la chaîne de nœuds connectés par cette relation.</p>
</div>

<div class="h-section">
  <h2>6. Export (sauvegarde côté client)</h2>
  <p>Les boutons <strong>⬇ JSON</strong>, <strong>⬇ RDF/XML</strong> et <strong>⬇ CSV</strong> téléchargent l'ontologie courante (filtrée ou complète) directement dans votre navigateur. Il s'agit d'un <em>export</em> (copie locale), pas d'un déchargement de l'ontologie du serveur.</p>
</div>

<div class="h-section">
  <h2>7. Changer la langue</h2>
  <p>Cliquez sur <strong>FR</strong> ou <strong>EN</strong> dans la barre d'en-tête. La langue est mémorisée en session PHP. Aucune modification du code requise. Pour ajouter une nouvelle langue, créez <code>lang/XX.php</code> en copiant <code>lang/fr.php</code>.</p>
</div>

<div class="h-section">
  <h2>8. Ajouter une fonctionnalité (plugin)</h2>
  <p>L'application est structurée en MVC PHP 8. Pour ajouter une fonctionnalité :</p>
  <ul>
    <li>Créez <code>app/Plugins/MonPlugin.php</code> qui implémente <code>App\Core\PluginInterface</code>.</li>
    <li>Déclarez la route dans <code>config/plugins.php</code> (tableau de routes supplémentaires).</li>
    <li>Ajoutez un onglet ou un bouton dans le template <code>app/Views/templates/explorer.php</code>.</li>
  </ul>
</div>

<div class="h-section">
  <h2>9. Architecture MVC</h2>
  <table class="h-table">
    <tr><th>Chemin</th><th>Rôle</th></tr>
    <tr><td><code>public/index.php</code></td><td>Point d'entrée unique (front controller)</td></tr>
    <tr><td><code>app/Core/Router.php</code></td><td>Routeur HTTP léger</td></tr>
    <tr><td><code>app/Controllers/</code></td><td>Contrôleurs (logique métier)</td></tr>
    <tr><td><code>app/Models/</code></td><td>Modèles (parseur d'ontologies)</td></tr>
    <tr><td><code>app/Views/</code></td><td>Templates PHP (layouts + vues)</td></tr>
    <tr><td><code>lang/</code></td><td>Fichiers de traduction</td></tr>
    <tr><td><code>uploads/</code></td><td>Fichiers ontologie déposés</td></tr>
  </table>
</div>

<div class="h-section">
  <h2>10. Dépôt Git</h2>
  <p>Le code source est versionné sur GitHub : <a href="https://github.com/your-org/ontology-explorer" style="color:var(--accent2)" target="_blank">github.com/your-org/ontology-explorer</a>.</p>
  <p>Pour contribuer : forkez le dépôt, créez une branche, et soumettez une Pull Request.</p>
</div>

</div>
