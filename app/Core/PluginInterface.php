<?php
declare(strict_types=1);
namespace App\Core;

/**
 * PluginInterface
 *
 * Pour ajouter une fonctionnalité à l'explorateur d'ontologies :
 *
 *   1. Créez app/Plugins/MonPlugin.php qui implémente cette interface
 *   2. Déclarez le plugin dans config/plugins.php
 *   3. Ajoutez si besoin une route dans config/plugins.php
 *
 * Exemple minimal :
 *
 *   class MonPlugin implements PluginInterface {
 *     public function id(): string      { return 'mon-plugin'; }
 *     public function label(): string   { return 'Mon Plugin'; }
 *     public function routes(): array   { return [['GET', '/plugin/mon-plugin', 'handle']]; }
 *     public function handle(array $params): void {
 *       header('Content-Type: application/json');
 *       echo json_encode(['result' => 'hello from plugin']);
 *     }
 *     public function sidebarHtml(): string { return '<button onclick="callPlugin()">Plugin</button>'; }
 *   }
 */
interface PluginInterface
{
    /** Identifiant unique du plugin (slug) */
    public function id(): string;

    /** Label affiché dans l'interface */
    public function label(): string;

    /**
     * Routes HTTP supplémentaires.
     * Retourne un tableau de [ method, path, methodName ]
     * @return array<array{0:string,1:string,2:string}>
     */
    public function routes(): array;

    /**
     * Gestionnaire de la route principale du plugin.
     * @param array<string> $params Paramètres URL
     */
    public function handle(array $params): void;

    /**
     * HTML optionnel à injecter dans la sidebar.
     * Retourner '' pour ne rien afficher.
     */
    public function sidebarHtml(): string;
}
