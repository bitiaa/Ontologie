<?php
declare(strict_types=1);
namespace App\Core;

abstract class Controller
{
    protected function render(string $template, array $data = [], string $layout = 'main'): void
    {
        extract($data);
        $lang = $_SESSION['lang'] ?? 'fr';
        $i18n = require ROOT_PATH . '/lang/' . $lang . '.php';
        $layoutFile = APP_PATH . '/Views/layouts/' . $layout . '.php';
        require $layoutFile;
    }

    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}
