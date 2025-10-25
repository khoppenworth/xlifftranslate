<?php
function _check_xml_ext(): void {
    if (!class_exists('DOMDocument')) {
        throw new RuntimeException("PHP DOM extension not found. Install php-xml and restart your web server.");
    }
}
function parse_xliff(string $xml): array {
    _check_xml_ext();
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
    $fileNode = $xp->query('//x:xliff/x:file')->item(0);
    $is12 = true;
    if (!$fileNode) { $fileNode = $xp->query('//x2:xliff/x2:file')->item(0); $is12 = false; }
    if (!$fileNode) throw new RuntimeException("Could not find <file> in XLIFF.");
    $sourceLang = $fileNode->getAttribute($is12 ? 'source-language' : 'srcLang');
    $targetLang = $fileNode->getAttribute($is12 ? 'target-language' : 'trgLang');
    $units = [];
    if ($is12) {
        foreach ($xp->query('//x:trans-unit') as $node) {
            $id = $node->getAttribute('id') ?: $node->getAttribute('resname');
            $source=''; $target='';
            foreach ($node->childNodes as $n) {
                if ($n instanceof DOMElement && $n->tagName === 'source') $source = inner_xml($n);
                if ($n instanceof DOMElement && $n->tagName === 'target') $target = inner_xml($n);
            }
            $units[] = ['id'=>$id,'source'=>$source,'target'=>$target];
        }
    } else {
        foreach ($xp->query('//x2:unit') as $unit) {
            $id = $unit->getAttribute('id');
            $sourceParts=[]; $targetParts=[];
            foreach ($unit->getElementsByTagName('segment') as $seg) {
                $src=''; $tgt='';
                foreach ($seg->childNodes as $n) {
                    if ($n instanceof DOMElement && $n->localName === 'source') $src = inner_xml($n);
                    if ($n instanceof DOMElement && $n->localName === 'target') $tgt = inner_xml($n);
                }
                $sourceParts[]=$src; $targetParts[]=$tgt;
            }
            $units[]=['id'=>$id,'source'=>implode("\n",$sourceParts),'target'=>implode("\n",$targetParts)];
        }
    }
    return ['is12'=>$is12,'sourceLang'=>$sourceLang,'targetLang'=>$targetLang,'units'=>$units,'xml'=>$dom->saveXML()];
}
function inner_xml(DOMElement $element): string {
    $s=''; foreach ($element->childNodes as $child) $s .= $element->ownerDocument->saveXML($child);
    return $s ?: '';
}
function build_xliff(string $originalXml, array $targetsById, ?string $targetLang = null): string {
    _check_xml_ext();
    libxml_use_internal_errors(true);
    $dom = new DOMDocument(); $dom->preserveWhiteSpace = false; $dom->formatOutput = true;
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
    if ($targetLang) { if ($is12) $fileNode->setAttribute('target-language', $targetLang); else $fileNode->setAttribute('trgLang', $targetLang); }
    if ($is12) {
        foreach ($xp->query('//x:trans-unit') as $node) {
            $id = $node->getAttribute('id') ?: $node->getAttribute('resname');
            if (!array_key_exists($id, $targetsById)) continue;
            $targetText = $targetsById[$id];
            $targetEl = null;
            foreach ($node->childNodes as $n) if ($n instanceof DOMElement && $n->tagName === 'target') { $targetEl = $n; break; }
            if (!$targetEl) {
                $targetEl = $dom->createElement('target'); $inserted=false;
                foreach ($node->childNodes as $n) if ($n instanceof DOMElement && $n->tagName === 'source') { if ($n->nextSibling) $node->insertBefore($targetEl, $n->nextSibling); else $node->appendChild($targetEl); $inserted=true; break; }
                if (!$inserted) $node->appendChild($targetEl);
            }
            while ($targetEl->firstChild) $targetEl->removeChild($targetEl->firstChild);
            if (strlen(trim($targetText)) > 0) {
                $frag = $dom->createDocumentFragment();
                $ok = $frag->appendXML($targetText);
                if ($ok) $targetEl->appendChild($frag); else $targetEl->appendChild($dom->createTextNode($targetText));
            }
        }
    } else {
        foreach ($xp->query('//x2:unit') as $unit) {
            $id = $unit->getAttribute('id');
            if (!array_key_exists($id, $targetsById)) continue;
            $targetText = $targetsById[$id];
            $segment = $unit->getElementsByTagName('segment')->item(0);
            if (!$segment) { $segment = $dom->createElementNS('urn:oasis:names:tc:xliff:document:2.0', 'segment'); $unit->appendChild($segment); }
            $targetEl = null;
            foreach ($segment->childNodes as $n) if ($n instanceof DOMElement && $n->localName === 'target') { $targetEl = $n; break; }
            if (!$targetEl) { $targetEl = $dom->createElementNS('urn:oasis:names:tc:xliff:document:2.0', 'target'); $segment->appendChild($targetEl); }
            while ($targetEl->firstChild) $targetEl->removeChild($targetEl->firstChild);
            if (strlen(trim($targetText)) > 0) {
                $frag = $dom->createDocumentFragment();
                $ok = $frag->appendXML($targetText);
                if ($ok) $targetEl->appendChild($frag); else $targetEl->appendChild($dom->createTextNode($targetText));
            }
        }
    }
    return $dom->saveXML();
}
