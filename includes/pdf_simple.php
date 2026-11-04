<?php
// ============================================================
//  pdf_simple.php — Generador de PDF MÍNIMO y SIN DEPENDENCIAS.
//  Arma un PDF válido (1.4) con un título y una tabla de texto en
//  fuente Courier, paginando solo con PHP. Suficiente para exportar
//  listados (requisito 5). No usa librerías externas ni internet.
// ============================================================

class PDFSimple
{
    private array $paginas = [];   // cada página = texto del stream
    private float $ancho = 612;    // Carta (puntos)
    private float $alto  = 792;
    private float $margen = 40;
    private int   $fontSize = 9;
    private float $lineH = 13;

    private array $lineasPend = [];

    public function __construct(private string $titulo = 'Reporte', bool $horizontal = false) {
        if ($horizontal) { $this->ancho = 792; $this->alto = 612; }  // Carta apaisada
    }

    // Convierte UTF-8 a CP1252 (WinAnsi) para conservar tildes/ñ en el PDF.
    private function win(string $s): string {
        $r = @iconv('UTF-8', 'CP1252//TRANSLIT', $s);
        if ($r === false) { $r = @iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s; }
        // Escapar caracteres especiales de cadena PDF.
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $r);
    }

    // Caracteres que entran por línea (Courier: ancho = 0.6 * fontSize).
    private function maxChars(): int {
        $usable = $this->ancho - 2 * $this->margen;
        return (int)floor($usable / (0.6 * $this->fontSize));
    }

    // Construye el PDF a partir de encabezados + filas y devuelve los bytes.
    public function tabla(array $headers, array $filas): string {
        $cols = count($headers);
        // Ancho de cada columna = máximo entre encabezado y celdas (en chars).
        $w = [];
        foreach ($headers as $i => $h) {
            $w[$i] = mb_strlen((string)$h);
        }
        foreach ($filas as $f) {
            $i = 0;
            foreach ($f as $v) { $w[$i] = max($w[$i] ?? 0, mb_strlen((string)$v)); $i++; }
        }
        // Limitar el total a lo que entra en la página (reparto simple).
        $maxC = $this->maxChars();
        $sep = 2; // espacios entre columnas
        $totalMin = array_sum($w) + $sep * ($cols - 1);
        if ($totalMin > $maxC) {
            // recortar proporcionalmente las columnas más anchas
            $factor = ($maxC - $sep * ($cols - 1)) / max(1, array_sum($w));
            foreach ($w as $i => $val) { $w[$i] = max(4, (int)floor($val * $factor)); }
        }

        $padRow = function (array $celdas) use ($w, $sep): string {
            $out = [];
            $i = 0;
            foreach ($celdas as $v) {
                $v = (string)$v;
                $ancho = $w[$i] ?? 8;
                if (mb_strlen($v) > $ancho) { $v = mb_substr($v, 0, max(1, $ancho - 1)) . '…'; }
                $out[] = str_pad($v, $ancho);
                $i++;
            }
            return implode(str_repeat(' ', $sep), $out);
        };

        // Líneas: título, fecha, encabezado, separador, filas.
        $this->lineasPend = [];
        $this->lineasPend[] = ['t' => $this->titulo, 'bold' => true];
        $this->lineasPend[] = ['t' => 'Generado: ' . date('d/m/Y H:i'), 'bold' => false];
        $this->lineasPend[] = ['t' => '', 'bold' => false];
        $this->lineasPend[] = ['t' => $padRow($headers), 'bold' => true];
        $this->lineasPend[] = ['t' => str_repeat('-', min($this->maxChars(), array_sum($w) + $sep * ($cols - 1))), 'bold' => false];
        foreach ($filas as $f) {
            $this->lineasPend[] = ['t' => $padRow(array_values($f)), 'bold' => false];
        }
        $this->lineasPend[] = ['t' => '', 'bold' => false];
        $this->lineasPend[] = ['t' => 'Total de registros: ' . count($filas), 'bold' => false];

        $this->paginar();
        return $this->render();
    }

    private function paginar(): void {
        $yTop = $this->alto - $this->margen;
        $yBot = $this->margen;
        $porPagina = (int)floor(($yTop - $yBot) / $this->lineH);
        $chunks = array_chunk($this->lineasPend, max(1, $porPagina));
        foreach ($chunks as $chunk) {
            $this->paginas[] = $this->streamDe($chunk);
        }
        if (!$this->paginas) { $this->paginas[] = $this->streamDe([]); }
    }

    private function streamDe(array $lineas): string {
        $x = $this->margen;
        $y = $this->alto - $this->margen;
        $s = "BT /F1 {$this->fontSize} Tf {$this->lineH} TL {$x} {$y} Td\n";
        foreach ($lineas as $ln) {
            $txt = $this->win($ln['t']);
            // "negrita" simulada: Courier-Bold vía segundo set de Tf no; usamos el mismo.
            $s .= "({$txt}) Tj T*\n";
        }
        $s .= "ET";
        return $s;
    }

    private function render(): string {
        $obj = [];
        // 1: catálogo, 2: pages, 3: font, 4..: páginas y contenidos
        $nPag = count($this->paginas);
        $kids = [];
        $objNum = 3; // font es 3
        $pageObjs = [];
        $contentObjs = [];
        $num = 4;
        foreach ($this->paginas as $stream) {
            $pageObjs[] = $num;       // objeto página
            $contentObjs[] = $num + 1; // objeto contenido
            $kids[] = "$num 0 R";
            $num += 2;
        }

        $out = "%PDF-1.4\n";
        $offsets = [];
        $push = function (string $body) use (&$out, &$offsets) {
            $offsets[] = strlen($out);
            $out .= $body;
        };

        // obj 1 catálogo
        $push("1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n");
        // obj 2 pages
        $push("2 0 obj\n<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count $nPag >>\nendobj\n");
        // obj 3 font
        $push("3 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>\nendobj\n");
        // páginas + contenidos
        foreach ($this->paginas as $i => $stream) {
            $pNum = $pageObjs[$i];
            $cNum = $contentObjs[$i];
            $push("$pNum 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->ancho} {$this->alto}] "
                . "/Resources << /Font << /F1 3 0 R >> >> /Contents $cNum 0 R >>\nendobj\n");
            $len = strlen($stream);
            $push("$cNum 0 obj\n<< /Length $len >>\nstream\n$stream\nendstream\nendobj\n");
        }

        // xref
        $xrefPos = strlen($out);
        $totalObjs = count($offsets) + 1; // +1 por el objeto 0
        $out .= "xref\n0 $totalObjs\n0000000000 65535 f \n";
        foreach ($offsets as $off) {
            $out .= sprintf("%010d 00000 n \n", $off);
        }
        $out .= "trailer\n<< /Size $totalObjs /Root 1 0 R >>\nstartxref\n$xrefPos\n%%EOF";
        return $out;
    }
}
