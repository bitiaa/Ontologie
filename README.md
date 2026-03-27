# Ontology Explorer — PHP 8 MVC

Visualiseur d'ontologies OWL / RDF / RDFS / JSON-LD hébergé sur serveur PHP 8.

---

## 📁 Architecture MVC

```
ontology_explorer/
├── public/
│   ├── index.php           ← Front controller (point d'entrée unique)
│   └── .htaccess           ← Réécriture Apache
├── app/
│   ├── Core/
│   │   ├── Router.php      ← Routeur HTTP léger
│   │   ├── Controller.php  ← Contrôleur de base
│   │   └── PluginInterface.php ← Interface plugin
│   ├── Controllers/
│   │   └── OntologyController.php ← Toutes les routes
│   ├── Models/
│   │   └── OntologyParser.php ← Parser universel OWL/RDF/JSON-LD
│   ├── Plugins/
│   │   └── ExamplePlugin.php ← Plugin exemple
│   └── Views/
│       ├── layouts/
│       │   ├── main.php    ← Layout principal (shell HTML + JS)
│       │   └── help.php    ← Layout page d'aide
│       └── templates/
│           ├── explorer.php ← Vue principale (sidebar + vues)
│           └── help.php    ← Documentation
├── config/
│   ├── bootstrap.php       ← Autoloader PSR-4 + session
│   └── plugins.php         ← Enregistrement des plugins
├── lang/
│   ├── fr.php              ← Traductions françaises
│   └── en.php              ← Traductions anglaises
└── uploads/                ← Fichiers ontologie (créé automatiquement)
```

---

## 🚀 Installation

### Prérequis
- PHP ≥ 8.0
- Extension `dom` (libxml) activée
- Apache avec `mod_rewrite`

### Déploiement Apache

```apache
<VirtualHost *:80>
    ServerName ontology.local
    DocumentRoot /var/www/ontology_explorer/public
    <Directory /var/www/ontology_explorer/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Pointez le `DocumentRoot` vers le dossier **`public/`**, pas la racine du projet.

### Déploiement NGINX

```nginx
server {
    listen 80;
    root /var/www/ontology_explorer/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## 🗺 Routes HTTP

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/` | Page principale (explorateur) |
| GET | `/help` | Aide en ligne |
| POST | `/upload` | **Charger** une ontologie sur le serveur |
| GET | `/api/graph?file=X` | Données graphe (nœuds + arêtes) |
| GET | `/api/hierarchy?file=X` | Arbre hiérarchique subClassOf |
| GET | `/api/radial?file=X` | Données vue radiale |
| GET | `/api/path?from=A&to=B&file=X` | Chemin BFS entre deux concepts |
| GET | `/api/chain?start=A&relation=R&depth=N&file=X` | Chaîne de relations |
| GET | `/api/export?format=json\|rdf\|csv&file=X` | **Exporter** (téléchargement client) |
| GET | `/lang/fr` ou `/lang/en` | Changer la langue |

> **Charger** (upload) = envoyer un fichier sur le serveur pour l'analyser.  
> **Exporter** = télécharger une copie locale de l'ontologie parsée.

---

## 📂 Formats supportés

| Extension | Format |
|-----------|--------|
| `.owl` | OWL/XML (RDF/XML) ou JSON-LD |
| `.rdf` | RDF/XML |
| `.rdfs` | RDFS |
| `.xml` | RDF/XML |
| `.json` | JSON-LD |

---

## 🌐 Changer la langue

Cliquez sur **FR** ou **EN** dans l'en-tête. La langue est mémorisée en session PHP.

Pour ajouter une langue (ex. espagnol) :

```bash
cp lang/fr.php lang/es.php
# Traduire les valeurs dans lang/es.php
```

Puis ajouter dans le layout :
```html
<a href="/lang/es" class="hb">ES</a>
```

---

## 🔌 Ajouter un plugin

1. Créez `app/Plugins/MonPlugin.php` :

```php
<?php
namespace App\Plugins;
use App\Core\PluginInterface;

class MonPlugin implements PluginInterface {
    public function id(): string    { return 'mon-plugin'; }
    public function label(): string { return 'Mon Plugin'; }
    public function routes(): array { return [['GET', '/plugin/mon', 'handle']]; }

    public function handle(array $params): void {
        header('Content-Type: application/json');
        echo json_encode(['hello' => 'world']);
    }
    public function sidebarHtml(): string { return ''; }
}
```

2. Activez dans `config/plugins.php` :

```php
return [
    new \App\Plugins\MonPlugin(),
];
```

---

## 🖥 Vues disponibles

| Vue | Description |
|-----|-------------|
| **Graphe** | Force-directed D3.js. Nœuds draggables, zoom/pan, flèches directionnelles, noms de relations activables. |
| **Hiérarchie** | Arbre collapsible basé sur `rdfs:subClassOf`. |
| **Radial** | Vue en éventail radial D3 (arbre concentrique), zoomable. |
| **Chaîne** | Chemin BFS entre deux nœuds, ou chaîne par type de relation. Les noms de relations s'affichent sur chaque étape. |
| **Code** | JSON colorisé du graphe parsé. |

---

## ⎇ Dépôt Git

```bash
git clone https://github.com/your-org/ontology-explorer.git
cd ontology-explorer
# Configurer le vhost Apache/Nginx vers public/
```

---

## 📄 Licence

MIT — libre d'utilisation, modification et distribution.
