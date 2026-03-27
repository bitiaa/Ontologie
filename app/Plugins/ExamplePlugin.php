<?php
declare(strict_types=1);
namespace App\Plugins;

use App\Core\PluginInterface;

/**
 * ExamplePlugin — démo du système de plugins.
 *
 * Activez ce plugin en décommentant son entrée dans config/plugins.php.
 * Accédez à sa route via GET /plugin/example
 */
class ExamplePlugin implements PluginInterface
{
    public function id(): string
    {
        return 'example';
    }

    public function label(): string
    {
        return 'Example Plugin';
    }

    public function routes(): array
    {
        return [
            ['GET', '/plugin/example', 'handle'],
        ];
    }

    public function handle(array $params): void
    {
        header('Content-Type: application/json');
        echo json_encode([
            'plugin'  => $this->id(),
            'message' => 'Hello from ExamplePlugin !',
            'params'  => $params,
            'time'    => date('c'),
        ]);
    }

    public function sidebarHtml(): string
    {
        return '<div class="s-section"><div class="s-title">Example Plugin</div>'
             . '<button class="go-btn alt" onclick="window.open(\'/plugin/example\',\'_blank\')">Run</button></div>';
    }
}
