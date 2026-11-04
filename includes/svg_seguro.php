<?php
// ============================================================
//  svg_seguro.php
//  Trata los SVG subidos como HOSTILES. Un SVG es un XML y puede
//  llevar código adentro (<script>, onload=..., href="javascript:").
//  Si el navegador abre ese archivo, ese código corre EN TU SITIO.
//  Por eso, antes de guardar, validamos que sea un SVG de verdad
//  y lo limpiamos de todo lo que pueda ejecutarse.
// ============================================================

/**
 * Valida y limpia un SVG.
 *
 * @param  string       $rutaTmp  ruta del archivo temporal recién subido
 * @return string|false           el SVG ya limpio (texto), o false si no sirve
 */
function svg_validar_y_limpiar($rutaTmp) {

    // 1) Leemos el contenido. Rechazamos vacío o ilegible.
    $contenido = @file_get_contents($rutaTmp);
    if ($contenido === false || trim($contenido) === '') {
        return false;
    }

    // 2) Chequeo rápido: ¿realmente contiene la etiqueta <svg?
    if (stripos($contenido, '<svg') === false) {
        return false;
    }

    // 3) Lo parseamos con el lector de XML de PHP.
    //    LIBXML_NONET prohíbe que el XML salga a internet: así evitamos
    //    ataques XXE y cargas externas. NO usamos LIBXML_NOENT (que
    //    expandiría entidades) justamente para no abrir esa puerta.
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);   // silenciamos los avisos del parser
    $ok = $doc->loadXML($contenido, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    if (!$ok) {
        return false;   // XML roto o no válido → afuera
    }

    $xpath = new DOMXPath($doc);

    // 4) Eliminamos elementos peligrosos completos:
    //    - <script>        : código JS directo
    //    - <foreignObject> : puede incrustar HTML (y HTML trae scripts)
    //    Usamos local-name() para que funcione con o sin prefijos de namespace.
    foreach (['script', 'foreignObject'] as $tag) {
        $nodos = $xpath->query('//*[local-name()="' . $tag . '"]');
        foreach ($nodos as $nodo) {
            $nodo->parentNode->removeChild($nodo);
        }
    }

    // 5) Recorremos TODOS los elementos y sacamos atributos peligrosos:
    //    - los que empiezan con "on" (onload, onclick, onmouseover, ...)
    //    - href / xlink:href que apunten a "javascript:"
    foreach ($xpath->query('//*') as $el) {
        if (!$el->hasAttributes()) continue;

        // Primero anotamos cuáles quitar (no se borra mientras se recorre).
        $aQuitar = [];
        foreach ($el->attributes as $attr) {
            $nombre = strtolower($attr->nodeName);
            $valor  = strtolower(trim($attr->nodeValue));

            $esManejador = strpos($nombre, 'on') === 0;
            $esJsLink    = strpos($nombre, 'href') !== false
                           && strpos($valor, 'javascript:') === 0;

            if ($esManejador || $esJsLink) {
                $aQuitar[] = $attr->nodeName;
            }
        }
        foreach ($aQuitar as $n) {
            $el->removeAttribute($n);
        }
    }

    // 6) Devolvemos el SVG ya saneado, listo para escribir en disco.
    return $doc->saveXML();
}
?>
