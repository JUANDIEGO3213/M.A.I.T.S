<?php
/*********************************************************************
    include/maits/xlsx.php   [M.A.I.T.S]

    Generador sencillo de archivos de Excel (.xlsx) sin librerías
    externas: hojas con celdas de texto/número/fecha, estilos fijos,
    celdas combinadas, filtros, paneles fijos e imágenes (logos).
**********************************************************************/

/** ZIP mínimo (deflate) para armar el .xlsx sin depender de php-zip */
class MaitsZip {
    private $archivos = array();
    private $datos = '';
    private $central = '';

    function agregar($nombre, $contenido) {
        $crc = crc32($contenido);
        $comprimido = gzdeflate($contenido, 6);
        $t = getdate();
        $hora = ($t['hours'] << 11) | ($t['minutes'] << 5) | (int) ($t['seconds'] / 2);
        $fecha = (($t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday'];
        $offset = strlen($this->datos);
        $cab = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 8, $hora, $fecha,
            $crc, strlen($comprimido), strlen($contenido), strlen($nombre), 0);
        $this->datos .= $cab . $nombre . $comprimido;
        $this->central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 8,
            $hora, $fecha, $crc, strlen($comprimido), strlen($contenido),
            strlen($nombre), 0, 0, 0, 0, 0, $offset) . $nombre;
        $this->archivos[] = $nombre;
    }

    function contenido() {
        $n = count($this->archivos);
        return $this->datos . $this->central . pack('VvvvvVVv', 0x06054b50, 0, 0,
            $n, $n, strlen($this->central), strlen($this->datos), 0);
    }
}

class MaitsXlsx {
    // Indices de estilo (ver estilos())
    const E_NORMAL = 0, E_TITULO = 1, E_ETIQUETA = 2, E_TEXTO = 3, E_ENCABEZADO = 4,
          E_CELDA = 5, E_CENTRO = 6, E_FECHA = 7, E_ABIERTO = 8, E_CERRADO = 9,
          E_FIRMA = 10, E_NUMERO = 11, E_SUBTITULO = 12, E_FECHA_SOLA = 13,
          E_ATRASADO = 14, E_INFO = 15;

    private $hojas = array();
    private $imagenes = array();   // [ruta interna => bytes]

    /**
     * $hoja = array(
     *   'nombre' => 'Creados',
     *   'filas'  => array(fila => array(col => array(valor, estilo, tipo))),  tipo: s|n|d
     *   'anchos' => array(col => ancho),  'altos' => array(fila => alto en puntos),
     *   'combinar' => array('A1:C1', ...), 'filtro' => 'A5:N20', 'fijar' => 6,
     *   'imagenes' => array(array('datos'=>bytes, 'ext'=>'png', 'col'=>0, 'fila'=>0, 'ancho'=>px, 'alto'=>px)),
     * )
     */
    function agregarHoja($hoja) {
        $this->hojas[] = $hoja;
    }

    static function col($i) {
        $s = '';
        for ($i++; $i > 0; $i = (int) (($i - 1) / 26))
            $s = chr(65 + ($i - 1) % 26) . $s;
        return $s;
    }

    static function xml($t) {
        $t = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', (string) $t);
        return htmlspecialchars($t, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** Fecha "Y-m-d H:i:s" (ya en hora local) -> número de serie de Excel */
    static function serial($fecha) {
        $ts = strtotime($fecha . ' UTC');
        return $ts === false ? null : ($ts / 86400 + 25569);
    }

    // Colores y letras iguales a la plantilla Reporte_M.A.I.T.S_Plantilla.xlsx
    const AZUL = 'FF1F4E78';

    private function estilos() {
        $azul = self::AZUL;
        $borde = 'borderId="1" applyBorder="1"';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<numFmts count="2"><numFmt numFmtId="164" formatCode="dd/mm/yyyy hh:mm"/><numFmt numFmtId="165" formatCode="dd/mm/yyyy"/></numFmts>
<fonts count="10">
<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>
<font><b/><sz val="16"/><color rgb="'.$azul.'"/><name val="Arial"/><family val="2"/></font>
<font><b/><sz val="11"/><color rgb="'.$azul.'"/><name val="Arial"/><family val="2"/></font>
<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Arial"/><family val="2"/></font>
<font><sz val="10"/><color rgb="FF9C6500"/><name val="Arial"/><family val="2"/></font>
<font><sz val="10"/><color rgb="FF006100"/><name val="Arial"/><family val="2"/></font>
<font><sz val="10"/><name val="Century"/><family val="1"/></font>
<font><i/><sz val="10"/><color rgb="FF595959"/><name val="Arial"/><family val="2"/></font>
<font><sz val="10"/><name val="Arial"/><family val="2"/></font>
<font><sz val="10"/><color rgb="FF9C0006"/><name val="Arial"/><family val="2"/></font>
</fonts>
<fills count="6">
<fill><patternFill patternType="none"/></fill>
<fill><patternFill patternType="gray125"/></fill>
<fill><patternFill patternType="solid"><fgColor rgb="'.$azul.'"/><bgColor indexed="64"/></patternFill></fill>
<fill><patternFill patternType="solid"><fgColor rgb="FFFFEB9C"/><bgColor indexed="64"/></patternFill></fill>
<fill><patternFill patternType="solid"><fgColor rgb="FFC6EFCE"/><bgColor indexed="64"/></patternFill></fill>
<fill><patternFill patternType="solid"><fgColor rgb="FFFFC7CE"/><bgColor indexed="64"/></patternFill></fill>
</fills>
<borders count="2">
<border><left/><right/><top/><bottom/><diagonal/></border>
<border><left style="thin"><color rgb="FFBFBFBF"/></left><right style="thin"><color rgb="FFBFBFBF"/></right><top style="thin"><color rgb="FFBFBFBF"/></top><bottom style="thin"><color rgb="FFBFBFBF"/></bottom><diagonal/></border>
</borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="16">
<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>
<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>
<xf numFmtId="0" fontId="8" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>
<xf numFmtId="0" fontId="3" fillId="2" '.$borde.' xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
<xf numFmtId="0" fontId="8" fillId="0" '.$borde.' xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>
<xf numFmtId="0" fontId="8" fillId="0" '.$borde.' xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
<xf numFmtId="164" fontId="8" fillId="0" '.$borde.' xfId="0" applyFont="1" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
<xf numFmtId="0" fontId="4" fillId="3" '.$borde.' xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
<xf numFmtId="0" fontId="5" fillId="4" '.$borde.' xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
<xf numFmtId="0" fontId="6" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="bottom" wrapText="1"/></xf>
<xf numFmtId="0" fontId="8" fillId="0" '.$borde.' xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
<xf numFmtId="0" fontId="7" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>
<xf numFmtId="165" fontId="8" fillId="0" '.$borde.' xfId="0" applyFont="1" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
<xf numFmtId="0" fontId="9" fillId="5" '.$borde.' xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
<xf numFmtId="0" fontId="8" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>
</cellXfs>
<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
</styleSheet>';
    }

    /** Texto con partes en negritas: array(array('texto', negrita?), ...) */
    private static function textoRico($partes) {
        $x = '';
        foreach ($partes as $p) {
            list($t, $b) = array_pad((array) $p, 2, false);
            $x .= '<r><rPr>' . ($b ? '<b/>' : '') . '<sz val="10"/><color rgb="' . ($b ? self::AZUL : 'FF404040')
                . '"/><rFont val="Arial"/><family val="2"/></rPr><t xml:space="preserve">' . self::xml($t) . '</t></r>';
        }
        return $x;
    }

    private function hojaXml($i, $h, $tieneDibujo) {
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
           . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
           . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        // Al imprimir: todas las columnas en el ancho de la hoja
        $x .= '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>';
        $x .= '<sheetViews><sheetView workbookViewId="0" showGridLines="0"' . ($i == 0 ? ' tabSelected="1"' : '') . '>';
        if (!empty($h['fijar']))
            $x .= sprintf('<pane ySplit="%d" topLeftCell="A%d" activePane="bottomLeft" state="frozen"/>'
                . '<selection pane="bottomLeft" activeCell="A%2$d" sqref="A%2$d"/>', $h['fijar'] - 1, $h['fijar']);
        $x .= '</sheetView></sheetViews><sheetFormatPr defaultRowHeight="15"/>';
        if (!empty($h['anchos'])) {
            $x .= '<cols>';
            foreach ($h['anchos'] as $c => $w)
                $x .= sprintf('<col min="%d" max="%d" width="%.2f" customWidth="1"/>', $c + 1, $c + 1, $w);
            $x .= '</cols>';
        }
        $x .= '<sheetData>';
        ksort($h['filas']);
        foreach ($h['filas'] as $f => $celdas) {
            $alto = $h['altos'][$f] ?? null;
            $x .= sprintf('<row r="%d"%s>', $f + 1, $alto ? sprintf(' ht="%.1f" customHeight="1"', $alto) : '');
            ksort($celdas);
            foreach ($celdas as $c => $cel) {
                list($valor, $estilo, $tipo) = array_pad($cel, 3, 's');
                $ref = self::col($c) . ($f + 1);
                if ($valor === null || $valor === '') {
                    $x .= sprintf('<c r="%s" s="%d"/>', $ref, $estilo);
                } elseif ($tipo == 'r') {
                    $x .= sprintf('<c r="%s" s="%d" t="inlineStr"><is>%s</is></c>', $ref, $estilo, self::textoRico($valor));
                } elseif ($tipo == 'n' || $tipo == 'd') {
                    $x .= sprintf('<c r="%s" s="%d"><v>%s</v></c>', $ref, $estilo, 0 + $valor);
                } else {
                    $x .= sprintf('<c r="%s" s="%d" t="inlineStr"><is><t xml:space="preserve">%s</t></is></c>',
                        $ref, $estilo, self::xml($valor));
                }
            }
            $x .= '</row>';
        }
        $x .= '</sheetData>';
        if (!empty($h['filtro']))
            $x .= '<autoFilter ref="' . $h['filtro'] . '"/>';
        if (!empty($h['combinar'])) {
            $x .= '<mergeCells count="' . count($h['combinar']) . '">';
            foreach ($h['combinar'] as $m)
                $x .= '<mergeCell ref="' . $m . '"/>';
            $x .= '</mergeCells>';
        }
        $x .= '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
            . '<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>';
        if ($tieneDibujo)
            $x .= '<drawing r:id="rId1"/>';
        return $x . '</worksheet>';
    }

    private function dibujoXml($imgs, $base) {
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
           . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" '
           . 'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
           . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        foreach ($imgs as $k => $im) {
            $emu = 9525; // EMU por pixel
            $cx = (int) round($im['ancho'] * $emu);
            $cy = (int) round($im['alto'] * $emu);
            $id = $base + $k + 2;
            $x .= '<xdr:oneCellAnchor><xdr:from>'
                . '<xdr:col>' . (int) $im['col'] . '</xdr:col><xdr:colOff>' . (int) (($im['dx'] ?? 4) * $emu) . '</xdr:colOff>'
                . '<xdr:row>' . (int) $im['fila'] . '</xdr:row><xdr:rowOff>' . (int) (($im['dy'] ?? 4) * $emu) . '</xdr:rowOff>'
                . '</xdr:from><xdr:ext cx="' . $cx . '" cy="' . $cy . '"/>'
                . '<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="' . $id . '" name="Logo ' . ($k + 1) . '"/>'
                . '<xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr>'
                . '<xdr:blipFill><a:blip r:embed="rId' . ($k + 1) . '"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
                . '<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm>'
                . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>'
                . '</xdr:pic><xdr:clientData/></xdr:oneCellAnchor>';
        }
        return $x . '</xdr:wsDr>';
    }

    function generar() {
        $z = new MaitsZip();
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Default Extension="png" ContentType="image/png"/>'
            . '<Default Extension="jpeg" ContentType="image/jpeg"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>';
        $wbRels = '';
        $hojasXml = '';
        $nombres = '';
        $nImg = 0;
        foreach ($this->hojas as $i => $h) {
            $n = $i + 1;
            $imgs = $h['imagenes'] ?? array();
            $ct .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            if ($imgs) {
                $ct .= '<Override PartName="/xl/drawings/drawing' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>';
                $z->agregar("xl/worksheets/_rels/sheet$n.xml.rels",
                    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing' . $n . '.xml"/></Relationships>');
                $rels = '';
                foreach ($imgs as $k => $im) {
                    $nImg++;
                    $archivo = 'image' . $nImg . '.' . ($im['ext'] == 'jpg' ? 'jpeg' : $im['ext']);
                    $z->agregar('xl/media/' . $archivo, $im['datos']);
                    $rels .= '<Relationship Id="rId' . ($k + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/' . $archivo . '"/>';
                }
                $z->agregar("xl/drawings/_rels/drawing$n.xml.rels",
                    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>');
                $z->agregar("xl/drawings/drawing$n.xml", $this->dibujoXml($imgs, 0));
            }
            $z->agregar("xl/worksheets/sheet$n.xml", $this->hojaXml($i, $h, (bool) $imgs));
            $wbRels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
            $nombre = mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $h['nombre']), 0, 31);
            $hojasXml .= '<sheet name="' . self::xml($nombre) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
            if (!empty($h['filtro']))
                $nombres .= '<definedName name="_xlnm._FilterDatabase" localSheetId="' . $i . '" hidden="1">\''
                    . self::xml(str_replace("'", "''", $nombre)) . '\'!' . preg_replace('/([A-Z]+)(\d+)/', '\$$1\$$2', $h['filtro']) . '</definedName>';
        }
        $ct .= '</Types>';
        $nh = count($this->hojas);
        $wbRels .= '<Relationship Id="rId' . ($nh + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        $z->agregar('[Content_Types].xml', $ct);
        $z->agregar('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>');
        $ahora = gmdate('Y-m-d\TH:i:s\Z');
        $z->agregar('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>Reporte M.A.I.T.S</dc:title><dc:creator>M.A.I.T.S</dc:creator>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $ahora . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $ahora . '</dcterms:modified></cp:coreProperties>');
        $z->agregar('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>M.A.I.T.S</Application></Properties>');
        $z->agregar('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView/></bookViews><sheets>' . $hojasXml . '</sheets>'
            . ($nombres ? '<definedNames>' . $nombres . '</definedNames>' : '') . '</workbook>');
        $z->agregar('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $wbRels . '</Relationships>');
        $z->agregar('xl/styles.xml', $this->estilos());
        return $z->contenido();
    }
}
