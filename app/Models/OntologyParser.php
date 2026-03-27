<?php
declare(strict_types=1);
namespace App\Models;

/**
 * Universal ontology parser.
 * Supports: RDF/XML (.owl, .rdf, .rdfs), JSON-LD (.json, .owl with JSON content)
 *
 * Returns a normalised graph:
 *   classes   : [ id => [ id, label, comment, parents[], color ] ]
 *   properties: [ id => [ id, label, domain, range, type ] ]
 *   edges     : [ [ source, target, relation, label ] ]
 */
class OntologyParser
{
    private array $classes    = [];
    private array $properties = [];
    private array $edges      = [];
    private string $baseUri   = '';
    private string $lang      = 'en';

    // Colour palette (cycled for classes)
    private const PALETTE = [
        '#e8ff5a','#5affe8','#ff5a8a','#5a9fff','#b07aff',
        '#5ac8ff','#ffaa5a','#ff9f40','#4bc07b','#f76c82',
        '#a78bfa','#34d399','#fb923c','#60a5fa','#f472b6',
    ];

    public function parseFile(string $filepath): array
    {
        if (!file_exists($filepath)) {
            throw new \RuntimeException("File not found: $filepath");
        }
        $content = file_get_contents($filepath);
        // Detect format
        $trimmed = ltrim($content);
        if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
            return $this->parseJsonLD($content);
        }
        return $this->parseRdfXml($content);
    }

    // ──────────────────────────────────────────────────────────
    // RDF/XML parser
    // ──────────────────────────────────────────────────────────
    private function parseRdfXml(string $xml): array
    {
        libxml_use_internal_errors(true);

        // Resolve DOCTYPE entities before parsing
        $xml = $this->resolveEntities($xml);

        $doc = new \DOMDocument();
        $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOWARNING);

        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('rdf',  'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
        $xpath->registerNamespace('rdfs', 'http://www.w3.org/2000/01/rdf-schema#');
        $xpath->registerNamespace('owl',  'http://www.w3.org/2002/07/owl#');

        // Detect base URI
        $onto = $xpath->query('//owl:Ontology/@rdf:about');
        if ($onto && $onto->length > 0) {
            $this->baseUri = $onto->item(0)->nodeValue;
        }

        // ── 1. Classes ──────────────────────────────────────
        $classNodes = $xpath->query('//owl:Class[@rdf:about or @rdf:ID] | //rdfs:Class[@rdf:about or @rdf:ID] | //*[self::rdfs:Class or self::owl:Class][@rdf:ID]');
        foreach ($classNodes as $node) {
            $id = $this->getId($node);
            if (!$id || str_starts_with($id, '_:')) continue;
            $label   = $this->getLabel($xpath, $node, $id);
            $comment = $this->getComment($xpath, $node);
            $this->classes[$id] = [
                'id'      => $id,
                'label'   => $label,
                'comment' => $comment,
                'parents' => [],
                'color'   => '',
            ];
        }

        // RDFS-style classes (Class element directly)
        $rdfsClasses = $xpath->query('//*[local-name()="Class"][@rdf:ID]');
        foreach ($rdfsClasses as $node) {
            $id = $node->getAttribute('rdf:ID');
            if (!$id) continue;
            $label   = $this->getLabel($xpath, $node, $id);
            $comment = $this->getComment($xpath, $node);
            if (!isset($this->classes[$id])) {
                $this->classes[$id] = [
                    'id'      => $id,
                    'label'   => $label,
                    'comment' => $comment,
                    'parents' => [],
                    'color'   => '',
                ];
            }
        }

        // ── 2. Object Properties ────────────────────────────
        $propNodes = $xpath->query('//owl:ObjectProperty[@rdf:about or @rdf:ID] | //owl:DatatypeProperty[@rdf:about or @rdf:ID]');
        foreach ($propNodes as $node) {
            $id    = $this->getId($node);
            if (!$id) continue;
            $label = $this->getLabel($xpath, $node, $id);
            $domain = $this->getAttr($xpath, $node, 'rdfs:domain');
            $range  = $this->getAttr($xpath, $node, 'rdfs:range');
            $type   = ($node->localName === 'DatatypeProperty') ? 'datatype' : 'object';
            $this->properties[$id] = compact('id','label','domain','range','type');
        }

        // ── 3. subClassOf edges ─────────────────────────────
        $subOf = $xpath->query('//rdfs:subClassOf');
        foreach ($subOf as $node) {
            $parent  = $node->getAttribute('rdf:resource');
            $child   = $this->getId($node->parentNode);
            if (!$child || !$parent) continue;
            $parentId = $this->localId($parent);
            $childId  = $this->localId($child);
            if ($parentId && $childId && $parentId !== $childId) {
                $this->edges[] = ['source'=>$childId,'target'=>$parentId,'relation'=>'subClassOf','label'=>'subClassOf'];
                if (isset($this->classes[$childId])) {
                    $this->classes[$childId]['parents'][] = $parentId;
                }
            }
        }

        // ── 4. Object property edges (domain/range) ─────────
        foreach ($this->properties as $prop) {
            if ($prop['domain'] && $prop['range']) {
                $d = $this->localId($prop['domain']);
                $r = $this->localId($prop['range']);
                if ($d && $r) {
                    $this->edges[] = ['source'=>$d,'target'=>$r,'relation'=>$prop['id'],'label'=>$prop['label']];
                }
            }
        }

        // ── 5. Generic rdf:resource relations ───────────────
        $allElements = $xpath->query('//*[@rdf:resource]');
        foreach ($allElements as $el) {
            $localName = $el->localName;
            if (in_array($localName, ['subClassOf','type','about','domain','range','inverseOf'], true)) continue;
            $resource = $el->getAttribute('rdf:resource');
            $subject  = $this->getId($el->parentNode);
            if (!$subject || !$resource) continue;
            $sId = $this->localId($subject);
            $tId = $this->localId($resource);
            if ($sId && $tId && $sId !== $tId) {
                $this->edges[] = ['source'=>$sId,'target'=>$tId,'relation'=>$localName,'label'=>$localName];
            }
        }

        // Deduplicate edges
        $this->dedupeEdges();

        // Assign colours
        $this->assignColors();

        return $this->buildResult();
    }

    // ──────────────────────────────────────────────────────────
    // JSON-LD parser
    // ──────────────────────────────────────────────────────────
    private function parseJsonLD(string $json): array
    {
        $data = json_decode($json, true);
        if (!is_array($data)) throw new \RuntimeException('Invalid JSON-LD');

        // Flatten if wrapped in array
        $items = isset($data[0]) ? $data : [$data];

        $OWL_CLASS   = 'http://www.w3.org/2002/07/owl#Class';
        $OWL_OBJPROP = 'http://www.w3.org/2002/07/owl#ObjectProperty';
        $OWL_DTPROP  = 'http://www.w3.org/2002/07/owl#DatatypeProperty';
        $RDFS_SUBCLS = 'http://www.w3.org/2000/01/rdf-schema#subClassOf';
        $RDFS_LABEL  = 'http://www.w3.org/2000/01/rdf-schema#label';
        $RDFS_COMM   = 'http://www.w3.org/2000/01/rdf-schema#comment';
        $RDFS_DOMAIN = 'http://www.w3.org/2000/01/rdf-schema#domain';
        $RDFS_RANGE  = 'http://www.w3.org/2000/01/rdf-schema#range';

        foreach ($items as $item) {
            if (!isset($item['@id'])) continue;
            $id    = $this->localId($item['@id']);
            if (!$id || str_starts_with($id, 'genid')) continue;
            $types = (array)($item['@type'] ?? []);

            // Extract label
            $label = $id;
            if (isset($item[$RDFS_LABEL])) {
                foreach ((array)$item[$RDFS_LABEL] as $lv) {
                    if (isset($lv['@value'])) { $label = $lv['@value']; break; }
                }
            }
            $comment = '';
            if (isset($item[$RDFS_COMM])) {
                foreach ((array)$item[$RDFS_COMM] as $cv) {
                    if (isset($cv['@value'])) { $comment = $cv['@value']; break; }
                }
            }

            if (in_array($OWL_CLASS, $types) || in_array('http://www.w3.org/2000/01/rdf-schema#Class', $types)) {
                $this->classes[$id] = ['id'=>$id,'label'=>$label,'comment'=>$comment,'parents'=>[],'color'=>''];
                // subClassOf
                if (isset($item[$RDFS_SUBCLS])) {
                    foreach ((array)$item[$RDFS_SUBCLS] as $sc) {
                        $parentId = $this->localId($sc['@id'] ?? '');
                        if ($parentId && $parentId !== $id) {
                            $this->edges[] = ['source'=>$id,'target'=>$parentId,'relation'=>'subClassOf','label'=>'subClassOf'];
                            $this->classes[$id]['parents'][] = $parentId;
                        }
                    }
                }
            }

            if (in_array($OWL_OBJPROP, $types) || in_array($OWL_DTPROP, $types)) {
                $domain = isset($item[$RDFS_DOMAIN][0]['@id']) ? $this->localId($item[$RDFS_DOMAIN][0]['@id']) : '';
                $range  = isset($item[$RDFS_RANGE][0]['@id'])  ? $this->localId($item[$RDFS_RANGE][0]['@id'])  : '';
                $ptype  = in_array($OWL_DTPROP, $types) ? 'datatype' : 'object';
                $this->properties[$id] = compact('id','label','domain','range') + ['type'=>$ptype];
                if ($domain && $range) {
                    $this->edges[] = ['source'=>$domain,'target'=>$range,'relation'=>$id,'label'=>$label];
                }
            }
        }

        $this->dedupeEdges();
        $this->assignColors();
        return $this->buildResult();
    }

    // ──────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────
    private function resolveEntities(string $xml): string
    {
        // Extract and pre-resolve DOCTYPE entities so DOMDocument doesn't choke
        if (preg_match('/<!DOCTYPE[^>]*\[(.*?)\]>/s', $xml, $m)) {
            $entityBlock = $m[1];
            $entityMap   = [];
            preg_match_all('/<!ENTITY\s+(\w+)\s+"([^"]+)"\s*>/', $entityBlock, $ents, PREG_SET_ORDER);
            foreach ($ents as $ent) {
                $entityMap['&' . $ent[1] . ';'] = $ent[2];
            }
            // Remove DOCTYPE (causes issues with external entities)
            $xml = preg_replace('/<!DOCTYPE[^>]*\[.*?\]>/s', '', $xml);
            // Replace entity references in content
            foreach ($entityMap as $ref => $val) {
                $xml = str_replace($ref, $val, $xml);
            }
        }
        return $xml;
    }

    private function getId(\DOMNode $node): string
    {
        if (!($node instanceof \DOMElement)) return '';
        return $node->getAttribute('rdf:about') ?: $node->getAttribute('rdf:ID') ?: '';
    }

    private function localId(string $uri): string
    {
        if (!$uri) return '';
        // #fragment
        if (str_contains($uri, '#')) return substr($uri, strrpos($uri, '#') + 1);
        // last path segment
        return basename($uri);
    }

    private function getLabel(\DOMXPath $xpath, \DOMElement $node, string $fallback): string
    {
        $labels = $xpath->query('rdfs:label', $node);
        if ($labels && $labels->length > 0) {
            // Prefer current language
            foreach ($labels as $l) {
                if ($l->getAttribute('xml:lang') === $this->lang) return trim($l->nodeValue);
            }
            return trim($labels->item(0)->nodeValue);
        }
        return $this->localId($fallback) ?: $fallback;
    }

    private function getComment(\DOMXPath $xpath, \DOMElement $node): string
    {
        $comments = $xpath->query('rdfs:comment', $node);
        if ($comments && $comments->length > 0) {
            foreach ($comments as $c) {
                if ($c->getAttribute('xml:lang') === $this->lang) return trim($c->nodeValue);
            }
            return trim($comments->item(0)->nodeValue);
        }
        return '';
    }

    private function getAttr(\DOMXPath $xpath, \DOMElement $node, string $qname): string
    {
        $nodes = $xpath->query($qname, $node);
        if ($nodes && $nodes->length > 0) {
            return $nodes->item(0)->getAttribute('rdf:resource') ?: $nodes->item(0)->nodeValue;
        }
        return '';
    }

    private function dedupeEdges(): void
    {
        $seen = [];
        $deduped = [];
        foreach ($this->edges as $e) {
            $key = $e['source'] . '||' . $e['target'] . '||' . $e['relation'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $deduped[] = $e;
            }
        }
        $this->edges = $deduped;
    }

    private function assignColors(): void
    {
        $i = 0;
        foreach ($this->classes as &$cls) {
            $cls['color'] = self::PALETTE[$i % count(self::PALETTE)];
            $i++;
        }
    }

    private function buildResult(): array
    {
        return [
            'classes'    => array_values($this->classes),
            'properties' => array_values($this->properties),
            'edges'      => $this->edges,
            'stats'      => [
                'classCount'    => count($this->classes),
                'propertyCount' => count($this->properties),
                'edgeCount'     => count($this->edges),
            ],
        ];
    }

    public function setLang(string $lang): self
    {
        $this->lang = $lang;
        return $this;
    }
}
