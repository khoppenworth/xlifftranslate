<?php
/**
 * Simple XLIFF 1.2 helper lib (works for many 2.0 files as well in a basic way)
 * - parse_xliff(string $xml): array with meta + units
 * - build_xliff(string $originalXml, array $targetsById, string|null $targetLang): string
 */

function parse_xliff(string $xml): array {
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    if (!$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOENT | LIBXML_NOWARNING)) {
        throw new RuntimeException("Invalid XML/XLIFF file.");
    }

    $xp = new DOMXPath($dom);
    $xp->registerNamespace('x', 'urn:oasis:names:tc:xliff:document:1.2');
    $xp->registerNamespace('x2', 'urn:oasis:names:tc:xliff:document:2.0');

    // Try 1.2 first
    $fileNode = $xp->query('//x:xliff/x:file')->item(0);
    $is12 = true;
    if (!$fileNode) {
        // Try 2.0
        $fileNode = $xp->query('//x2:xliff/x2:file')->item(0);
        $is12 = false;
    }
    if (!$fileNode) {
        throw new RuntimeException("Could not find <file> element in XLIFF.");
    }

    $sourceLang = $fileNode->getAttribute($is12 ? 'source-language' : 'srcLang');
    $targetLang = $fileNode->getAttribute($is12 ? 'target-language' : 'trgLang');

    $units = [];
    if ($is12) {
        $nodes = $xp->query('//x:trans-unit');
        foreach ($nodes as $node) {
            /** @var DOMElement $node */
            $id = $node->getAttribute('id') ?: $node->getAttribute('resname');
            $source = '';
            $target = '';
            $sNode = null; $tNode = null;
            foreach ($node->childNodes as $n) {
                if ($n instanceof DOMElement && $n->tagName === 'source') { $sNode = $n; }
                if ($n instanceof DOMElement && $n->tagName === 'target') { $tNode = $n; }
            }
            if ($sNode) $source = inner_xml($sNode);
            if ($tNode) $target = inner_xml($tNode);
            $units[] = ['id' => $id, 'source' => $source, 'target' => $target];
        }
    } else {
        // 2.0 basic support: unit/segment/source|target
        $nodes = $xp->query('//x2:unit');
        foreach ($nodes as $unit) {
            /** @var DOMElement $unit */
            $id = $unit->getAttribute('id');
            $seg = null;
            foreach ($unit->getElementsByTagName('segment') as $segNode) { $seg = $segNode; break; }
            if (!$seg) continue;
            $source = ''; $target = '';
            foreach ($seg->childNodes as $n) {
                if ($n instanceof DOMElement && $n->localName === 'source') $source = inner_xml($n);
                if ($n instanceof DOMElement && $n->localName === 'target') $target = inner_xml($n);
            }
            $units[] = ['id' => $id, 'source' => $source, 'target' => $target];
        }
    }

    return [
        'is12' => $is12,
        'sourceLang' => $sourceLang,
        'targetLang' => $targetLang,
        'units' => $units,
        'xml' => $dom->saveXML(),
    ];
}

function inner_xml(DOMElement $element): string {
    $s = '';
    foreach ($element->childNodes as $child) {
        $s .= $element->ownerDocument->saveXML($child);
    }
    return $s ?: '';
}

function build_xliff(string $originalXml, array $targetsById, ?string $targetLang = null): string {
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    if (!$dom->loadXML($originalXml, LIBXML_NONET | LIBXML_NOENT | LIBXML_NOWARNING)) {
        throw new RuntimeException("Invalid XML/XLIFF source while building.");
    }
    $xp = new DOMXPath($dom);
    $xp->registerNamespace('x', 'urn:oasis:names:tc:xliff:document:1.2');
    $xp->registerNamespace('x2', 'urn:oasis:names:tc:xliff:document:2.0');

    $fileNode = $xp->query('//x:xliff/x:file')->item(0);
    $is12 = true;
    if (!$fileNode) { $fileNode = $xp->query('//x2:xliff/x2:file')->item(0); $is12 = false; }
    if (!$fileNode) throw new RuntimeException("No <file> in XLIFF.");

    if ($targetLang) {
        if ($is12) $fileNode->setAttribute('target-language', $targetLang);
        else $fileNode->setAttribute('trgLang', $targetLang);
    }

    if ($is12) {
        $nodes = $xp->query('//x:trans-unit');
        foreach ($nodes as $node) {
            /** @var DOMElement $node */
            $id = $node->getAttribute('id') ?: $node->getAttribute('resname');
            if (!array_key_exists($id, $targetsById)) continue;
            $targetText = $targetsById[$id];

            // find/create <target>
            $targetEl = null;
            foreach ($node->childNodes as $n) {
                if ($n instanceof DOMElement && $n->tagName === 'target') { $targetEl = $n; break; }
            }
            if (!$targetEl) {
                $targetEl = $dom->createElement('target');
                // Insert target after source if exists
                $inserted = false;
                foreach ($node->childNodes as $n) {
                    if ($n instanceof DOMElement && $n->tagName === 'source') {
                        if ($n->nextSibling) $node->insertBefore($targetEl, $n->nextSibling);
                        else $node->appendChild($targetEl);
                        $inserted = true;
                        break;
                    }
                }
                if (!$inserted) $node->appendChild($targetEl);
            }
            // Replace children with parsed fragment from targetText (supports inline tags)
            while ($targetEl->firstChild) $targetEl->removeChild($targetEl->firstChild);
            if (strlen(trim($targetText)) > 0) {
                $frag = $dom->createDocumentFragment();
                $ok = $frag->appendXML($targetText);
                if ($ok) $targetEl->appendChild($frag);
                else $targetEl->appendChild($dom->createTextNode($targetText));
            }
        }
    } else {
        // 2.0 basic support
        $units = $xp->query('//x2:unit');
        foreach ($units as $unit) {
            $id = $unit->getAttribute('id');
            if (!array_key_exists($id, $targetsById)) continue;
            $targetText = $targetsById[$id];
            $segment = $unit->getElementsByTagName('segment')->item(0);
            if (!$segment) continue;
            $targetEl = null;
            foreach ($segment->childNodes as $n) {
                if ($n instanceof DOMElement && $n->localName === 'target') { $targetEl = $n; break; }
            }
            if (!$targetEl) {
                $targetEl = $dom->createElementNS('urn:oasis:names:tc:xliff:document:2.0', 'target');
                $segment->appendChild($targetEl);
            }
            while ($targetEl->firstChild) $targetEl->removeChild($targetEl->firstChild);
            if (strlen(trim($targetText)) > 0) {
                $frag = $dom->createDocumentFragment();
                $ok = $frag->appendXML($targetText);
                if ($ok) $targetEl->appendChild($frag);
                else $targetEl->appendChild($dom->createTextNode($targetText));
            }
        }
    }

    return $dom->saveXML();
}
