<?php
declare(strict_types=1);
namespace App\Core;

class Router
{
    private array $routes = [];

    public function __construct()
    {
        // ── GET routes ────────────────────────────────────────
        $this->get('/',                    'OntologyController@index');
        $this->get('/help',                'OntologyController@help');
        $this->get('/api/ontology',        'OntologyController@apiGet');
        $this->get('/api/graph',           'OntologyController@apiGraph');
        $this->get('/api/hierarchy',       'OntologyController@apiHierarchy');
        $this->get('/api/radial',          'OntologyController@apiRadial');
        $this->get('/api/path',            'OntologyController@apiPath');
        $this->get('/api/chain',           'OntologyController@apiChain');
        $this->get('/api/export',          'OntologyController@apiExport');
        $this->get('/lang/{code}',         'OntologyController@setLang');

        // ── POST routes ───────────────────────────────────────
        $this->post('/upload',             'OntologyController@upload');
    }

    private function get(string $path, string $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    private function post(string $path, string $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $uri, string $method): void
    {
        // Strip query string
        $path = parse_url($uri, PHP_URL_PATH);
        $path = rtrim($path, '/') ?: '/';

        $routes = $this->routes[$method] ?? [];

        // Direct match
        if (isset($routes[$path])) {
            $this->call($routes[$path], []);
            return;
        }

        // Param match  e.g. /lang/{code}
        foreach ($routes as $pattern => $handler) {
            $regex = preg_replace('/\{(\w+)\}/', '([^/]+)', $pattern);
            $regex = '#^' . $regex . '$#';
            if (preg_match($regex, $path, $m)) {
                array_shift($m);
                $this->call($handler, $m);
                return;
            }
        }

        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    }

    private function call(string $handler, array $params): void
    {
        [$class, $method] = explode('@', $handler);
        $fqcn = "App\\Controllers\\{$class}";
        $ctrl = new $fqcn();
        $ctrl->$method(...$params);
    }
}
