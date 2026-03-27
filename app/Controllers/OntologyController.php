<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Models\OntologyParser;

class OntologyController extends Controller
{
    // ── Main page ─────────────────────────────────────────────
    public function index(): void
    {
        $files       = $this->listUploads();
        $currentFile = $_SESSION['current_file'] ?? ($files[0] ?? null);
        $lang        = $_SESSION['lang'] ?? 'fr';
        $this->render('explorer', compact('files','currentFile','lang'));
    }

    // ── Help page ─────────────────────────────────────────────
    public function help(): void
    {
        $lang = $_SESSION['lang'] ?? 'fr';
        $this->render('help', compact('lang'), 'help');
    }

    // ── File upload ───────────────────────────────────────────
    public function upload(): void
    {
        if (!isset($_FILES['ontology']) || $_FILES['ontology']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['error' => 'Upload failed'], 400);
            return;
        }
        $file    = $_FILES['ontology'];
        $name    = basename($file['name']);
        $ext     = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = ['owl','rdf','rdfs','xml','json'];
        if (!in_array($ext, $allowed, true)) {
            $this->json(['error' => "Extension .$ext non supportée"], 400);
            return;
        }
        $dest = UPLOAD_PATH . '/' . $name;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $_SESSION['current_file'] = $name;
            $this->json(['success' => true, 'filename' => $name]);
        } else {
            $this->json(['error' => 'Could not save file'], 500);
        }
    }

    // ── API: raw ontology data ────────────────────────────────
    public function apiGet(): void
    {
        try {
            $data = $this->parse();
            $this->json($data);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── API: graph nodes + edges ──────────────────────────────
    public function apiGraph(): void
    {
        try {
            $data  = $this->parse();
            $nodes = array_map(fn($c) => [
                'id'      => $c['id'],
                'label'   => $c['label'],
                'comment' => $c['comment'],
                'color'   => $c['color'],
                'group'   => 'class',
            ], $data['classes']);

            $this->json(['nodes' => $nodes, 'edges' => $data['edges'], 'stats' => $data['stats']]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── API: hierarchy tree (subClassOf) ──────────────────────
    public function apiHierarchy(): void
    {
        try {
            $data    = $this->parse();
            $classes = $data['classes'];
            $edges   = array_filter($data['edges'], fn($e) => $e['relation'] === 'subClassOf');

            // Build children map
            $children = [];
            $hasParent = [];
            foreach ($edges as $e) {
                $children[$e['target']][] = $e['source'];
                $hasParent[$e['source']]  = true;
            }

            // Index classes by id
            $byId = [];
            foreach ($classes as $c) { $byId[$c['id']] = $c; }

            // Recursive tree builder
            $build = function(string $id) use (&$build, $children, $byId): array {
                $node = $byId[$id] ?? ['id'=>$id,'label'=>$id,'color'=>'#888','comment'=>''];
                $kids = [];
                foreach ($children[$id] ?? [] as $childId) {
                    $kids[] = $build($childId);
                }
                return ['id'=>$node['id'],'label'=>$node['label'],'color'=>$node['color'],'comment'=>$node['comment'],'children'=>$kids];
            };

            // Roots = classes without a parent
            $roots = [];
            foreach ($classes as $c) {
                if (!isset($hasParent[$c['id']])) {
                    $roots[] = $build($c['id']);
                }
            }

            $this->json(['tree' => $roots]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── API: radial layout data ───────────────────────────────
    public function apiRadial(): void
    {
        try {
            $data    = $this->parse();
            // Same as hierarchy but we expose ALL edges for radial view
            $this->json([
                'nodes' => $data['classes'],
                'edges' => $data['edges'],
                'stats' => $data['stats'],
            ]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── API: shortest path BFS ────────────────────────────────
    public function apiPath(): void
    {
        $from = $_GET['from'] ?? '';
        $to   = $_GET['to']   ?? '';
        try {
            $data = $this->parse();
            $path = $this->bfs($data['edges'], $from, $to);
            if (!$path) {
                $this->json(['path' => null, 'message' => 'No path found']);
                return;
            }
            // Enrich with labels
            $byId = [];
            foreach ($data['classes'] as $c) { $byId[$c['id']] = $c; }
            $result = [];
            for ($i = 0; $i < count($path); $i++) {
                $id   = $path[$i];
                $node = $byId[$id] ?? ['id'=>$id,'label'=>$id,'color'=>'#888'];
                $rel  = null;
                if ($i > 0) {
                    $rel = $this->findRelation($data['edges'], $path[$i-1], $id);
                }
                $result[] = ['node' => $node, 'relation' => $rel];
            }
            $this->json(['path' => $result]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── API: relation chain ───────────────────────────────────
    public function apiChain(): void
    {
        $start    = $_GET['start']    ?? '';
        $relation = $_GET['relation'] ?? '';
        $depth    = min((int)($_GET['depth'] ?? 5), 20);
        try {
            $data  = $this->parse();
            $byId  = [];
            foreach ($data['classes'] as $c) { $byId[$c['id']] = $c; }

            // Build adjacency filtered by relation
            $adj = [];
            foreach ($data['edges'] as $e) {
                if ($relation === '' || $e['relation'] === $relation) {
                    $adj[$e['source']][] = $e['target'];
                }
            }

            $chain   = [$start];
            $visited = [$start => true];
            $current = $start;
            for ($i = 0; $i < $depth; $i++) {
                $next = null;
                foreach ($adj[$current] ?? [] as $t) {
                    if (!isset($visited[$t])) { $next = $t; break; }
                }
                if (!$next) break;
                $chain[]         = $next;
                $visited[$next]  = true;
                $current         = $next;
            }

            $result = array_map(fn($id) => $byId[$id] ?? ['id'=>$id,'label'=>$id,'color'=>'#888'], $chain);
            $this->json(['chain' => $result]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── API: export ontology ──────────────────────────────────
    public function apiExport(): void
    {
        $format = $_GET['format'] ?? 'json';
        try {
            $data = $this->parse();
            if ($format === 'json') {
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="ontology_export.json"');
                echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            } elseif ($format === 'rdf') {
                header('Content-Type: application/rdf+xml');
                header('Content-Disposition: attachment; filename="ontology_export.rdf"');
                echo $this->toRdfXml($data);
            } elseif ($format === 'csv') {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="ontology_edges.csv"');
                echo "source,target,relation\n";
                foreach ($data['edges'] as $e) {
                    echo "{$e['source']},{$e['target']},{$e['relation']}\n";
                }
            }
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Language switch ───────────────────────────────────────
    public function setLang(string $code): void
    {
        $allowed = ['fr','en'];
        if (in_array($code, $allowed, true)) {
            $_SESSION['lang'] = $code;
        }
        $ref = $_SERVER['HTTP_REFERER'] ?? '/';
        $this->redirect($ref);
    }

    // ──────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────
    private function parse(): array
    {
        $file = $_GET['file'] ?? $_SESSION['current_file'] ?? null;
        if (!$file) throw new \RuntimeException('Aucun fichier chargé');
        $path   = UPLOAD_PATH . '/' . basename($file);
        $lang   = $_SESSION['lang'] ?? 'fr';
        $parser = (new OntologyParser())->setLang($lang);
        return $parser->parseFile($path);
    }

    private function listUploads(): array
    {
        if (!is_dir(UPLOAD_PATH)) return [];
        $files = [];
        foreach (glob(UPLOAD_PATH . '/*.{owl,rdf,rdfs,xml,json}', GLOB_BRACE) as $f) {
            $files[] = basename($f);
        }
        return $files;
    }

    private function bfs(array $edges, string $from, string $to): ?array
    {
        $adj = [];
        foreach ($edges as $e) {
            $adj[$e['source']][] = $e['target'];
            $adj[$e['target']][] = $e['source']; // bidirectional
        }
        $queue   = [[$from]];
        $visited = [$from => true];
        while (!empty($queue)) {
            $path = array_shift($queue);
            $node = end($path);
            if ($node === $to) return $path;
            foreach ($adj[$node] ?? [] as $nb) {
                if (!isset($visited[$nb])) {
                    $visited[$nb] = true;
                    $queue[]      = [...$path, $nb];
                }
            }
        }
        return null;
    }

    private function findRelation(array $edges, string $from, string $to): ?string
    {
        foreach ($edges as $e) {
            if (($e['source'] === $from && $e['target'] === $to) ||
                ($e['source'] === $to   && $e['target'] === $from)) {
                return $e['label'] ?? $e['relation'];
            }
        }
        return null;
    }

    private function toRdfXml(array $data): string
    {
        $xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<rdf:RDF xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\"\n";
        $xml .= "         xmlns:rdfs=\"http://www.w3.org/2000/01/rdf-schema#\"\n";
        $xml .= "         xmlns:owl=\"http://www.w3.org/2002/07/owl#\">\n\n";
        foreach ($data['classes'] as $c) {
            $id  = htmlspecialchars($c['id'], ENT_QUOTES);
            $lbl = htmlspecialchars($c['label'], ENT_XML1);
            $xml .= "  <owl:Class rdf:ID=\"{$id}\">\n";
            $xml .= "    <rdfs:label>{$lbl}</rdfs:label>\n";
            if ($c['comment']) {
                $cmt = htmlspecialchars($c['comment'], ENT_XML1);
                $xml .= "    <rdfs:comment>{$cmt}</rdfs:comment>\n";
            }
            foreach ($c['parents'] as $p) {
                $pid = htmlspecialchars($p, ENT_QUOTES);
                $xml .= "    <rdfs:subClassOf rdf:resource=\"#{$pid}\"/>\n";
            }
            $xml .= "  </owl:Class>\n";
        }
        $xml .= "</rdf:RDF>\n";
        return $xml;
    }
}
