<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH',  ROOT_PATH . '/app');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');

require ROOT_PATH . '/config/bootstrap.php';

$router = new \App\Core\Router();
$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
