<?php
function parse_xliff($xml){
  if(!class_exists('DOMDocument')){ throw new Exception('PHP DOM extension (php-xml) is required.'); }
  $doc = new DOMDocument();
  $doc->preserveWhiteSpace = false;
  $doc->formatOutput = false;
  if (!$doc->loadXML($xml)) throw new Exception('Invalid XLIFF XML.');

  $xpath = new DOMXPath($doc);
  $xpath->registerNamespace('x1', 'urn:oasis:names:tc:xliff:document:1.2');
  $xpath->registerNamespace('x2', 'urn:oasis:names:tc:xliff:document:2.0');

  $units = [];
  foreach ($xpath->query('//x1:trans-unit') as $tu){
    $id = $tu->getAttribute('id') ?: $tu->getAttribute('resname') ?: '';
    $src = $xpath->query('x1:source', $tu)->item(0);
    $tgt = $xpath->query('x1:target', $tu)->item(0);
    $sourceXml = $src ? inner_xml($src) : '';
    $targetXml = $tgt ? inner_xml($tgt) : '';
    $units[] = ['id'=>$id, 'source'=>$sourceXml, 'target'=>$targetXml, 'node'=>$tu];
  }
  foreach ($xpath->query('//x2:unit') as $unit){
    $id = $unit->getAttribute('id') ?: '';
    foreach ($xpath->query('.//x2:segment', $unit) as $seg){
      $src = $xpath->query('x2:source', $seg)->item(0);
      $tgt = $xpath->query('x2:target', $seg)->item(0);
      $sourceXml = $src ? inner_xml($src) : '';
      $targetXml = $tgt ? inner_xml($tgt) : '';
      $units[] = ['id'=>$id, 'source'=>$sourceXml, 'target'=>$targetXml, 'node'=>$seg];
    }
  }
  return ['doc'=>$doc, 'xpath'=>$xpath, 'units'=>$units, 'xml'=>$xml];
}
function inner_xml(DOMNode $node){
  $s=''; foreach (iterator_to_array($node->childNodes) as $ch){ $s .= $node->ownerDocument->saveXML($ch); } return $s;
}
function replace_targets($parsed, $targets){
  $doc = $parsed['doc']; $xpath = $parsed['xpath'];
  foreach ($xpath->query('//x1:trans-unit') as $tu){
    $id = $tu->getAttribute('id') ?: $tu->getAttribute('resname') ?: '';
    $src = $xpath->query('x1:source', $tu)->item(0); if(!$src) continue;
    $tgt = $xpath->query('x1:target', $tu)->item(0);
    if(!$tgt){ $tgt = $doc->createElementNS('urn:oasis:names:tc:xliff:document:1.2','target'); $tu->appendChild($tgt); }
    $new = $targets[$id] ?? null; if($new===null) continue;
    while($tgt->firstChild){ $tgt->removeChild($tgt->firstChild); }
    $frag = $doc->createDocumentFragment(); $frag->appendXML($new);
    $tgt->appendChild($frag);
  }
  foreach ($xpath->query('//x2:unit') as $unit){
    $id = $unit->getAttribute('id') ?: '';
    foreach ($xpath->query('.//x2:segment', $unit) as $seg){
      $src = $xpath->query('x2:source', $seg)->item(0); if(!$src) continue;
      $tgt = $xpath->query('x2:target', $seg)->item(0);
      if(!$tgt){ $tgt = $doc->createElementNS('urn:oasis:names:tc:xliff:document:2.0','target'); $seg->appendChild($tgt); }
      $new = $targets[$id] ?? null; if($new===null) continue;
      while($tgt->firstChild){ $tgt->removeChild($tgt->firstChild); }
      $frag = $doc->createDocumentFragment(); $frag->appendXML($new);
      $tgt->appendChild($frag);
    }
  }
  return $doc->saveXML();
}
