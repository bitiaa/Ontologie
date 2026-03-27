<?php
declare(strict_types=1);

// ── Simple PSR-4-like autoloader ──────────────────────────────
spl_autoload_register(function (string $class): void {
    $map = [
        'App\\Core\\'        => APP_PATH . '/Core/',
        'App\\Controllers\\' => APP_PATH . '/Controllers/',
        'App\\Models\\'      => APP_PATH . '/Models/',
        'App\\Plugins\\'     => APP_PATH . '/Plugins/',
    ];
    foreach ($map as $prefix => $base) {
        if (str_starts_with($class, $prefix)) {
            $file = $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (file_exists($file)) { require $file; return; }
        }
    }
});

// ── Session ───────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Default language ─────────────────────────────────────────
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'fr';
}

// ── Upload directory ─────────────────────────────────────────
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}

// ── Load plugins ─────────────────────────────────────────
$GLOBALS['_plugins'] = require ROOT_PATH . '/config/plugins.php';
