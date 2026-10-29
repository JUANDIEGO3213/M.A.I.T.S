<?php
/*********************************************************************
    include/maits/reporte.php   [M.A.I.T.S]

    Reporte de tickets: Creados / Abiertos / Cerrados por periodo,
    exportado a Excel (.xlsx con logos), PDF o CSV.

    Reutiliza las mismas columnas que la exportación de osTicket
    (CustomQueue::getExportableFields), así que los valores salen
    igual que en el CSV original, pero con nombres claros.

    LOGOS: se toman de images/reporte/
        logo_izquierdo.png (o .jpg)  -> arriba a la izquierda
        logo_derecho.png   (o .jpg)  -> arriba al centro/derecha
    Si no existen, se usa el escudo del ITS (images/ITS-Saltillo-64x64.png).
**********************************************************************/
require_once INCLUDE_DIR . 'maits/xlsx.php';

class MaitsReporte {

    const LIMITE = 50000;     // máximo de tickets por grupo (Excel y CSV)
    const LIMITE_PDF = 2000;  // el PDF tarda ~8 ms y usa ~0.1 MB por ticket
    const BLOQUE_PDF = 100;   // filas por cada WriteHTML() del PDF
    const MEMORIA = '512M';   // memoria mínima para generar el archivo
    const LINEA_FIRMA = '______________________________';

    /** Lineas del bloque de firma (ver include/maits/config-reporte.php) */
    static function firma() {
        $c = @include INCLUDE_DIR . 'maits/config-reporte.php';
        $c = is_array($c) ? $c : array();
        $lineas = array();
        if (trim($c['nombre'] ?? '') !== '')
            $lineas[] = trim($c['nombre']);
        else
            $lineas[] = 'Nombre y firma del responsable';
        if (trim($c['cargo'] ?? '') !== '')
            $lineas[] = trim($c['cargo']);
        $lineas[] = trim($c['institucion'] ?? '') !== '' ? trim($c['institucion']) : 'Instituto Tecnológico de Saltillo';
        return $lineas;
    }

    // Grupos de tickets que se pueden incluir
    static $grupos = array(
        'creados'  => array('Creados',  'Todos los tickets que se reportaron (abiertos y cerrados).', 'Fecha en que se crearon'),
        'abiertos' => array('Abiertos', 'Tickets que siguen pendientes (activos).',                  'Fecha en que se crearon'),
        'cerrados' => array('Cerrados', 'Tickets que ya se solucionaron.',                            'Fecha en que se cerraron'),
    );

    // Columnas: ruta de osTicket => [nombre, para qué sirve, recomendada?, ancho en Excel]
    // Las 16 recomendadas son las mismas (y en el mismo orden) que la plantilla
    // Reporte_M.A.I.T.S_Plantilla.xlsx
    static $columnas = array(
        'number'                => array('Número de Ticket',        'El número con el que el usuario da seguimiento a su reporte.', 1, 14),
        'created'               => array('Fecha de creación',       'Día y hora en que se reportó.', 1, 16),
        'cdata__subject'        => array('Asunto',                  'El título que escribió el usuario.', 1, 32),
        'user__name'            => array('De',                      'Nombre de quien reportó.', 1, 14),
        'user__emails__address' => array('De correo electrónico',   'Correo de quien reportó.', 1, 22),
        'cdata__priority'       => array('Prioridad',               'Baja, Normal, Alta o Urgente.', 1, 12.9),
        'dept_id'               => array('Departamento',            'Área que atiende el ticket.', 1, 23),
        'topic_id'              => array('Tema de ayuda',           'La opción que eligió el usuario al crear el ticket.', 1, 20),
        'source'                => array('Fuente',                  'Por dónde llegó: Web, correo, teléfono…', 1, 10),
        'status__id'            => array('Estado actual',           'Abierto, Resuelto o Cerrado (con color).', 1, 14),
        'lastupdate'            => array('Última actualización',    'Último cambio o mensaje en el ticket.', 1, 16),
        'est_duedate'           => array('Fecha de expiración SLA', 'Fecha límite calculada por el plan de tiempos de respuesta (SLA).', 1, 18),
        'sla_id'                => array('Plan SLA',                'Plan de tiempos de respuesta que se le aplicó.', 1, 16),
        'duedate'               => array('Fecha de vencimiento',    'Fecha límite que un agente puso a mano (si la hay).', 1, 16),
        'closed'                => array('Fecha de cierre',         'Día en que se cerró. Vacío si sigue abierto.', 1, 14),
        'isoverdue'             => array('Atrasado',                'Sí, si ya pasó su fecha límite (en rojo).', 1, 10),
        'staff_id'              => array('Agente asignado',         'Agente que tiene el ticket a su cargo.', 0, 20),
        'team_id'               => array('Equipo asignado',         'Equipo que tiene el ticket a su cargo.', 0, 20),
        'isanswered'            => array('Respondido',              'Sí, si el personal ya le contestó al usuario.', 0, 11),
        'thread_count'          => array('Cantidad de mensajes',    'Cuántos mensajes y respuestas tiene la conversación.', 0, 11),
        'reopen_count'          => array('Veces reabierto',         'Cuántas veces se volvió a abrir después de cerrarse.', 0, 10),
        'attachment_count'      => array('Archivos adjuntos',       'Cuántas fotos o archivos se adjuntaron.', 0, 10),
        'task_count'            => array('Tareas',                  'Cuántas tareas internas tiene el ticket.', 0, 9),
        'merged'                => array('Combinado',               'Sí, si se unió con otro ticket.', 0, 11),
        'linked'                => array('Enlazado',                'Sí, si está relacionado con otro ticket.', 0, 10),
    );

    static $fechas = array('created', 'closed', 'lastupdate', 'est_duedate', 'duedate');
    static $centradas = array('number', 'cdata__priority', 'source', 'isoverdue', 'isanswered',
        'merged', 'linked', 'thread_count', 'reopen_count', 'attachment_count', 'task_count');

    var $staff;
    var $opciones;
    var $tz;
    var $dbtz;
    var $generado;   // DateTime local
    var $totales = array();   // grupo => número de tickets (ver contar())

    function __construct(Staff $staff, $opciones) {
        global $cfg;
        $this->staff = $staff;
        $this->opciones = $opciones;
        $this->tz = new DateTimeZone($cfg->getTimezone($staff) ?: date_default_timezone_get());
        $this->dbtz = new DateTimeZone($cfg->getDbTimezone() ?: 'UTC');
        $this->generado = new DateTime('now', $this->tz);
    }

    /** Todas las columnas disponibles: las conocidas + campos extra del formulario */
    static function columnasDisponibles() {
        $todas = array();
        $osticket = CustomQueue::getExportableFields();
        foreach (self::$columnas as $ruta => $info)
            if (isset($osticket[$ruta]))
                $todas[$ruta] = $info;
        foreach ($osticket as $ruta => $etiqueta)
            if (!isset($todas[$ruta]))
                $todas[$ruta] = array($etiqueta, 'Campo adicional del formulario del ticket.', 0, 18);
        return $todas;
    }

    /** Lee y valida las opciones del formulario */
    static function leerOpciones($post, &$errores) {
        $o = array('grupos' => array(), 'fecha' => !empty($post['fecha_reporte']),
            'formato' => in_array($post['formato'] ?? '', array('xlsx', 'pdf', 'csv')) ? $post['formato'] : 'xlsx');
        $creados = null;
        foreach (array_keys(self::$grupos) as $g) {
            if (empty($post['incluir'][$g]))
                continue;
            $p = $post['periodo'][$g] ?? 'todos';
            $desde = $post['desde'][$g] ?? '';
            $hasta = $post['hasta'][$g] ?? '';
            if ($p == 'igual') {
                if ($creados === null) {
                    $errores['periodo'][$g] = 'Para usar "el mismo periodo que Creados", también marca "Creados".';
                    continue;
                }
                list($p, $desde, $hasta) = $creados;
            }
            if ($p == 'rango') {
                $ok = function ($d) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d); };
                if (!$ok($desde) || !$ok($hasta))
                    $errores['periodo'][$g] = 'Escribe la fecha de inicio y la fecha final.';
                elseif ($desde > $hasta)
                    $errores['periodo'][$g] = 'La fecha de inicio es después de la fecha final.';
            } else {
                $p = 'todos';
            }
            $o['grupos'][$g] = array($p, $desde, $hasta);
            if ($g == 'creados')
                $creados = array($p, $desde, $hasta);
        }
        if (!$o['grupos'] && empty($errores['periodo']))
            $errores['grupos'] = 'Marca al menos un grupo: Creados, Abiertos o Cerrados.';

        $disponibles = self::columnasDisponibles();
        $o['columnas'] = array_values(array_filter((array) ($post['columnas'] ?? array()),
            function ($c) use ($disponibles) { return isset($disponibles[$c]); }));
        if (!$o['columnas'])
            $errores['columnas'] = 'Elige al menos una columna.';
        return $o;
    }

    /** Fecha local "Y-m-d" -> texto de la base de datos */
    function aDb($fecha, $fin = false) {
        $d = new DateTime($fecha . ($fin ? ' 23:59:59' : ' 00:00:00'), $this->tz);
        $d->setTimezone($this->dbtz);
        return $d->format('Y-m-d H:i:s');
    }

    /** Texto de la base de datos -> "Y-m-d H:i:s" en hora local del agente */
    function local($db) {
        if (!$db || strpos($db, '0000-00-00') === 0)
            return null;
        $d = new DateTime($db, $this->dbtz);
        $d->setTimezone($this->tz);
        return $d->format('Y-m-d H:i:s');
    }

    static function dmy($ymd) {
        return $ymd ? date('d/m/Y', strtotime($ymd)) : '';
    }

    function descripcionPeriodo($g) {
        list($p, $desde, $hasta) = $this->opciones['grupos'][$g];
        $que = mb_strtolower(self::$grupos[$g][2]);
        if ($p != 'rango')
            return 'Todos hasta el ' . $this->generado->format('d/m/Y');
        return sprintf('%s: del %s al %s', ucfirst($que), self::dmy($desde), self::dmy($hasta));
    }

    /** Tickets de un grupo (periodo + lo que el agente puede ver), sin columnas */
    function consulta($g) {
        list($p, $desde, $hasta) = $this->opciones['grupos'][$g];
        $query = Ticket::objects();
        if ($g == 'abiertos')
            $query->filter(array('status__state' => 'open'));
        elseif ($g == 'cerrados')
            $query->filter(array('status__state' => 'closed'));
        else
            $query->filter(array('status__state__in' => array('open', 'closed')));

        if ($p == 'rango') {
            $campo = ($g == 'cerrados') ? 'closed' : 'created';
            $query->filter(array(
                $campo . '__gte' => $this->aDb($desde),
                $campo . '__lte' => $this->aDb($hasta, true),
            ));
        }
        // Solo lo que el agente puede ver
        $query->filter($this->staff->getTicketsVisibility());
        return $query;
    }

    /** Cuántos tickets tiene un grupo */
    function contar($g) {
        if (!isset($this->totales[$g]))
            $this->totales[$g] = $this->consulta($g)->distinct('ticket_id')->count();
        return $this->totales[$g];
    }

    /** Máximo de tickets por grupo según el formato */
    function limite() {
        return $this->opciones['formato'] == 'pdf' ? self::LIMITE_PDF : self::LIMITE;
    }

    /**
     * Revisa que ningún grupo pase del máximo. Antes el reporte se cortaba
     * sin avisar (o se quedaba sin memoria); ahora se pide acotar el periodo.
     */
    function validarTamano(&$errores) {
        $max = $this->limite();
        foreach (array_keys($this->opciones['grupos']) as $g) {
            if (($n = $this->contar($g)) <= $max)
                continue;
            $errores['periodo'][$g] = sprintf(
                'Este grupo tiene %s tickets y el máximo por reporte %s es %s. Elige un periodo más corto%s.',
                number_format($n), $this->opciones['formato'] == 'pdf' ? 'en PDF' : '',
                number_format($max),
                $this->opciones['formato'] == 'pdf' ? ' o usa Excel, que admite hasta ' . number_format(self::LIMITE) : '');
        }
        return empty($errores);
    }

    /** Filas de un grupo: lista de arrays [ruta => [texto, valor]] + estado */
    function filas($g) {
        return iterator_to_array($this->recorrer($g), false);
    }

    /**
     * Igual que filas() pero entrega una fila a la vez (generador), para no
     * tener en memoria miles de tickets al armar Excel o CSV.
     */
    function recorrer($g) {
        $todas = CustomQueue::getExportableFields();
        $campos = array();
        foreach ($this->opciones['columnas'] as $c)
            $campos[$c] = $todas[$c];
        // siempre necesitamos el estado para dar color
        if (!isset($campos['status__id']))
            $campos['status__id'] = $todas['status__id'];

        $cola = new CustomQueue(array('root' => 'T', 'flags' => CustomQueue::FLAG_QUEUE));
        $query = $this->consulta($g);
        $columnas = $cola->getExportColumns($campos);
        foreach ($columnas as $col)
            $query = $col->mangleQuery($query, 'T');
        $query->setOption(QuerySet::OPT_NOCACHE, true);
        $query->order_by($g == 'cerrados' ? '-closed' : '-created')
            ->distinct('ticket_id')->limit($this->limite());

        $estados = $nombresEstado = array();
        foreach (TicketStatus::objects() as $s) {
            $estados[$s->getId()] = $s->getState();
            $nombresEstado[$s->getId()] = $s->getLocalName();
        }

        foreach ($query as $row) {
            $f = array();
            foreach ($columnas as $ruta => $col) {
                // El nombre del estado se toma de la lista ya cargada: con un
                // agente en sesión, from_query() consulta la lista de estados
                // en la base de datos por CADA ticket (20 000 consultas extra).
                $texto = ($ruta == 'status__id')
                    ? (string) ($nombresEstado[$row['status__id'] ?? 0] ?? '')
                    : (string) $col->from_query($row);
                // Si osTicket no dio texto, usar el valor tal cual, excepto los
                // identificadores (ej. agente "0" = sin asignar -> vacio)
                if ($texto === '' && !preg_match('/(_id|__id)$/', $ruta))
                    $texto = (string) ($row[$ruta] ?? '');
                $valor = null;
                if (in_array($ruta, self::$fechas)) {
                    $valor = $this->local($row[$ruta] ?? null);
                    $texto = $valor ? date('d/m/Y H:i', strtotime($valor)) : '';
                }
                $f[$ruta] = array($texto, $valor);
            }
            $f['_estado'] = $estados[$row['status__id'] ?? 0] ?? '';
            $f['_atrasado'] = !empty($row['isoverdue']);
            yield $f;
        }
    }

    function titulo($g) {
        return 'Reporte de tickets ' . mb_strtolower(self::$grupos[$g][0]);
    }

    function nombreArchivo() {
        $grupos = implode('-', array_map(function ($g) { return self::$grupos[$g][0]; },
            array_keys($this->opciones['grupos'])));
        return sprintf('Reporte M.A.I.T.S - %s - %s.%s', $grupos,
            $this->generado->format('d-m-Y'), $this->opciones['formato']);
    }

    static function logos() {
        $buscar = function ($base) {
            foreach (array('png', 'jpg', 'jpeg') as $ext)
                if (is_file($f = ROOT_DIR . "images/reporte/$base.$ext"))
                    return $f;
            return null;
        };
        $izq = $buscar('logo_izquierdo');
        $der = $buscar('logo_derecho') ?: (is_file($f = ROOT_DIR . 'images/ITS-Saltillo-64x64.png') ? $f : null);
        $res = array();
        foreach (array('izquierdo' => $izq, 'derecho' => $der) as $k => $f) {
            if (!$f || !($info = @getimagesize($f)))
                continue;
            $res[$k] = array('datos' => file_get_contents($f), 'w' => $info[0], 'h' => $info[1],
                'ext' => $info[2] == IMAGETYPE_JPEG ? 'jpg' : 'png', 'mime' => $info['mime']);
        }
        return $res;
    }

    // ---------------------------------------------------------------
    //  Excel (.xlsx)
    // ---------------------------------------------------------------
    function excel() {
        $x = new MaitsXlsx();
        $todas = self::columnasDisponibles();
        $cols = $this->opciones['columnas'];
        $n = count($cols);
        $ultima = max($n - 1, 3);
        $logos = self::logos();

        foreach (array_keys($this->opciones['grupos']) as $g) {
            $h = array('nombre' => self::$grupos[$g][0], 'filas' => array(), 'altos' => array(),
                'anchos' => array(), 'combinar' => array(), 'imagenes' => array(), 'xml_datos' => '');
            foreach ($cols as $i => $c)
                $h['anchos'][$i] = $todas[$c][3];

            // Fila 1 (como la plantilla): logo TecNM, escudo ITS y "FIRMA DEL INSTITUTO O ENCARGADO"
            $h['altos'][0] = 96;
            $grande = $n >= 11;
            $cLogo1 = $n >= 4 ? 1 : 0;
            $cLogo2 = $grande ? 5 : max(1, (int) floor($n / 3) + 1);
            $cFirma = $grande ? 8 : max($cLogo2 + 1, $ultima - 2);
            $fFirma = $grande ? 10 : $ultima;
            $escala = function ($l, $alto) { return array((int) round($l['w'] * $alto / $l['h']), $alto); };
            if (isset($logos['izquierdo'])) {
                list($w, $hh) = $escala($logos['izquierdo'], 119);
                if ($w > 422) { $hh = (int) round($hh * 422 / $w); $w = 422; }
                $h['imagenes'][] = array('datos' => $logos['izquierdo']['datos'], 'ext' => $logos['izquierdo']['ext'],
                    'col' => $cLogo1, 'fila' => 0, 'ancho' => $w, 'alto' => $hh, 'dx' => 5, 'dy' => 6);
            }
            if (isset($logos['derecho'])) {
                list($w, $hh) = $escala($logos['derecho'], 124);
                $h['imagenes'][] = array('datos' => $logos['derecho']['datos'], 'ext' => $logos['derecho']['ext'],
                    'col' => $cLogo2, 'fila' => 0, 'ancho' => $w, 'alto' => $hh, 'dx' => $grande ? 58 : 6, 'dy' => 2);
            }
            // Espacio para firmar, linea y debajo nombre / cargo / instituto
            $h['filas'][0][$cFirma] = array("\n" . self::LINEA_FIRMA . "\n" . implode("\n", self::firma()), MaitsXlsx::E_FIRMA);
            for ($c = $cFirma + 1; $c <= $fFirma; $c++)
                $h['filas'][0][$c] = array('', MaitsXlsx::E_FIRMA);
            if ($fFirma > $cFirma)
                $h['combinar'][] = MaitsXlsx::col($cFirma) . '1:' . MaitsXlsx::col($fFirma) . '1';

            // Fila 3: encabezados (azul, letra blanca)
            $fe = 2;
            $h['altos'][$fe] = 30;
            foreach ($cols as $i => $c)
                $h['filas'][$fe][$i] = array($todas[$c][0], MaitsXlsx::E_ENCABEZADO);

            // Datos: cada fila se convierte a XML en cuanto se lee, para no
            // guardar en memoria todos los tickets (con 20 000 se acababa)
            $r = $fe + 1;
            foreach ($this->recorrer($g) as $f) {
                // Alto de la fila segun el texto mas largo (Excel no lo calcula solo)
                $lineas = 1;
                foreach ($cols as $i => $c) {
                    if (in_array($c, self::$fechas)) continue;
                    $largo = mb_strlen($f[$c][0] ?? '');
                    $lineas = max($lineas, (int) ceil($largo / max(1, $todas[$c][3] * 1.1)));
                }
                $fila = array();
                foreach ($cols as $i => $c) {
                    list($texto, $valor) = $f[$c] ?? array('', null);
                    if ($c == 'status__id') {
                        $e = $f['_estado'] == 'open' ? MaitsXlsx::E_ABIERTO : MaitsXlsx::E_CERRADO;
                        $fila[$i] = array($texto, $e);
                    } elseif (in_array($c, self::$fechas)) {
                        $estilo = $c == 'closed' ? MaitsXlsx::E_FECHA_SOLA : MaitsXlsx::E_FECHA;
                        $fila[$i] = $valor ? array(MaitsXlsx::serial($valor), $estilo, 'd')
                                           : array('', MaitsXlsx::E_CELDA);
                    } elseif ($c == 'isoverdue' && $f['_atrasado']) {
                        $fila[$i] = array($texto, MaitsXlsx::E_ATRASADO);
                    } elseif (in_array($c, array('thread_count', 'reopen_count', 'attachment_count', 'task_count')) && is_numeric($texto)) {
                        $fila[$i] = array($texto, MaitsXlsx::E_NUMERO, 'n');
                    } elseif ($c == 'number' && ctype_digit($texto)) {
                        // como texto para conservar ceros a la izquierda
                        $fila[$i] = array($texto, MaitsXlsx::E_CENTRO);
                    } else {
                        $fila[$i] = array($texto, in_array($c, self::$centradas)
                            ? MaitsXlsx::E_CENTRO : MaitsXlsx::E_CELDA);
                    }
                }
                $h['xml_datos'] .= MaitsXlsx::filaXml($r, $fila, min(15 * $lineas + 3, 120));
                $r++;
            }
            $total = $r - $fe - 1;

            // Fila 2: de qué es el reporte, periodo, total y fecha del reporte
            $h['altos'][1] = 22;
            $partes = array(array($this->titulo($g) . '   ', true),
                array('Periodo: ', true), array($this->descripcionPeriodo($g) . '   '),
                array('Total de tickets: ', true), array($total . '   '));
            if ($this->opciones['fecha']) {
                $partes[] = array('Fecha del reporte: ', true);
                $partes[] = array($this->generado->format('d/m/Y'));
            }
            $h['filas'][1][0] = array($partes, MaitsXlsx::E_INFO, 'r');
            $h['combinar'][] = 'A2:' . MaitsXlsx::col($ultima) . '2';

            if (!$total) {
                $h['filas'][$r][0] = array('No hay tickets en este periodo.', MaitsXlsx::E_SUBTITULO);
                $r++;
            }
            $h['filtro'] = 'A' . ($fe + 1) . ':' . MaitsXlsx::col($n - 1) . max($fe + 1, $r);
            $h['fijar'] = $fe + 2;
            $x->agregarHoja($h);
        }
        return $x->generar();
    }

    // ---------------------------------------------------------------
    //  PDF
    // ---------------------------------------------------------------
    function pdf() {
        require_once INCLUDE_DIR . 'class.pdf.php';
        $todas = self::columnasDisponibles();
        $cols = $this->opciones['columnas'];
        $n = count($cols);
        $letra = $n <= 8 ? 9 : ($n <= 12 ? 7.5 : ($n <= 16 ? 6.5 : 5.5));
        $logos = self::logos();

        $pdf = new mPDFWithLocalImages(array('mode' => 'utf-8', 'format' => 'Letter-L',
            'tempDir' => sys_get_temp_dir(), 'margin_left' => 8, 'margin_right' => 8,
            'margin_top' => 8, 'margin_bottom' => 12));
        $pdf->SetTitle('Reporte M.A.I.T.S');
        $pdf->SetCreator('M.A.I.T.S');
        foreach ($logos as $k => $l)
            $pdf->imageVars['logo_' . $k] = $l['datos'];
        $pdf->SetHTMLFooter('<div style="font-size:7pt;color:#666;text-align:right">'
            . 'M.A.I.T.S — Generado el ' . $this->generado->format('d/m/Y H:i') . ' — Página {PAGENO} de {nbpg}</div>');

        $css = '<style>
            body { font-family: arial, sans-serif; font-size: ' . $letra . 'pt; color:#1b1b1b; }
            table.cab { width:100%; border-collapse:collapse; margin-bottom:4px; }
            table.cab td { vertical-align:middle; }
            .firma { font-family: serif; font-size:9.5pt; text-align:center; vertical-align:bottom; width:30%; line-height:1.3; }
            .firma .espacio { height:28px; }
            h1 { font-size:14pt; color:#1F4E78; margin:4px 0 2px; }
            .info { font-size:8.5pt; color:#555; margin-bottom:6px; }
            table.datos { width:100%; border-collapse:collapse; }
            table.datos th { background:#1F4E78; color:#fff; font-weight:bold; padding:4px 3px; border:0.5px solid #9aa4b8; text-align:center; }
            table.datos td { padding:3px; border:0.5px solid #b4b4b4; vertical-align:middle; }
            td.c { text-align:center; }
            table.datos td.abierto { background:#FFEB9C; color:#9C5700; text-align:center; }
            table.datos td.cerrado { background:#C6EFCE; color:#006100; text-align:center; }
            table.datos td.atrasado { background:#FFC7CE; color:#9C0006; text-align:center; }
            tr.par td { background:#f7f8fb; }
        </style>';

        $primero = true;
        foreach (array_keys($this->opciones['grupos']) as $g) {
            $filas = $this->filas($g);
            $html = $css;
            $html .= '<table class="cab"><tr>'
                . '<td style="width:35%">' . (isset($logos['izquierdo']) ? '<img src="var:logo_izquierdo" style="height:60px">' : '') . '</td>'
                . '<td style="width:35%;text-align:center">' . (isset($logos['derecho']) ? '<img src="var:logo_derecho" style="height:70px">' : '') . '</td>'
                . '<td class="firma"><div class="espacio"></div>' . self::LINEA_FIRMA . '<br>'
                . implode('<br>', array_map(array('Format', 'htmlchars'), self::firma())) . '</td></tr></table>';
            $html .= '<h1>M.A.I.T.S — ' . Format::htmlchars($this->titulo($g)) . '</h1>';
            $html .= '<div class="info">Periodo: ' . Format::htmlchars($this->descripcionPeriodo($g))
                . ' &nbsp;&nbsp; Total de tickets: <b>' . count($filas) . '</b>'
                . ($this->opciones['fecha'] ? ' &nbsp;&nbsp; Fecha del reporte: <b>' . $this->generado->format('d/m/Y') . '</b>' : '')
                . '</div>';
            if (!$primero)
                $pdf->AddPage();
            $pdf->WriteHTML($html);
            $primero = false;

            // La tabla se escribe en bloques: un solo WriteHTML() con miles de
            // filas pasa el limite pcre.backtrack_limit de mPDF (error fatal
            // desde ~1 500 tickets) y además usa mucha más memoria.
            $enc = '<table class="datos" repeat_header="1"><thead><tr>';
            foreach ($cols as $c)
                $enc .= '<th>' . Format::htmlchars($todas[$c][0]) . '</th>';
            $enc .= '</tr></thead><tbody>';
            foreach (array_chunk($filas, self::BLOQUE_PDF, true) as $bloque) {
                $html = $enc;
                foreach ($bloque as $i => $f) {
                    $html .= '<tr' . ($i % 2 ? ' class="par"' : '') . '>';
                    foreach ($cols as $c) {
                        $t = Format::htmlchars($f[$c][0] ?? '');
                        if ($c == 'status__id')
                            $html .= '<td class="' . ($f['_estado'] == 'open' ? 'abierto' : 'cerrado') . '">' . $t . '</td>';
                        elseif ($c == 'isoverdue' && $f['_atrasado'])
                            $html .= '<td class="atrasado">' . $t . '</td>';
                        else
                            $html .= '<td' . ((in_array($c, self::$centradas) || in_array($c, self::$fechas)) ? ' class="c"' : '') . '>' . $t . '</td>';
                    }
                    $html .= '</tr>';
                }
                $pdf->WriteHTML($html . '</tbody></table>');
            }
            if (!$filas)
                $pdf->WriteHTML($enc . '<tr><td colspan="' . $n . '" class="c">No hay tickets en este periodo.</td></tr></tbody></table>');
            unset($filas);
        }
        return $pdf->Output('', 'S');
    }

    // ---------------------------------------------------------------
    //  CSV (solo datos, sin logos ni colores)
    // ---------------------------------------------------------------
    function csv() {
        $todas = self::columnasDisponibles();
        $cols = $this->opciones['columnas'];
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");  // para que Excel lea bien los acentos
        if ($this->opciones['fecha'])
            self::filaCsv($out, array('Fecha del reporte: ' . $this->generado->format('d/m/Y')));
        $multi = count($this->opciones['grupos']) > 1;
        $enc = array_map(function ($c) use ($todas) { return $todas[$c][0]; }, $cols);
        self::filaCsv($out, $multi ? array_merge(array('Grupo'), $enc) : $enc);
        foreach (array_keys($this->opciones['grupos']) as $g) {
            foreach ($this->recorrer($g) as $f) {
                $linea = array();
                foreach ($cols as $c)
                    $linea[] = self::celdaCsv($f[$c][0] ?? '');
                self::filaCsv($out, $multi ? array_merge(array(self::$grupos[$g][0]), $linea) : $linea);
            }
        }
        rewind($out);
        $res = stream_get_contents($out);
        fclose($out);
        return $res;
    }

    /**
     * fputcsv() con el escape explícito: desde PHP 8.4 omitirlo es obsoleto
     * (su valor por defecto cambiará). '' sigue el estándar CSV (RFC 4180),
     * que es como Excel lee las comillas.
     */
    static function filaCsv($out, $campos) {
        return fputcsv($out, $campos, ',', '"', '');
    }

    /**
     * Evita la "inyección de fórmulas" (CSV injection): un asunto escrito por
     * el usuario como =HYPERLINK(...) o +cmd|... se ejecutaría como fórmula al
     * abrir el CSV en Excel. Se antepone un apóstrofo (recomendación OWASP).
     */
    static function celdaCsv($texto) {
        $texto = (string) $texto;
        return ($texto !== '' && strpos("=+-@\t\r", $texto[0]) !== false) ? "'" . $texto : $texto;
    }

    /** Sube memory_limit si es menor que MEMORIA (nunca lo baja) */
    static function asegurarMemoria() {
        $bytes = function ($v) {
            $v = trim((string) $v);
            if ($v === '' || $v === '-1') return PHP_INT_MAX;
            $n = (int) $v;
            switch (strtolower(substr($v, -1))) {
                case 'g': $n *= 1024;
                case 'm': $n *= 1024;
                case 'k': $n *= 1024;
            }
            return $n;
        };
        if ($bytes(ini_get('memory_limit')) < $bytes(self::MEMORIA))
            @ini_set('memory_limit', self::MEMORIA);
    }

    /** Genera el archivo y lo manda al navegador */
    function descargar() {
        @set_time_limit(300);
        self::asegurarMemoria();
        switch ($this->opciones['formato']) {
            case 'pdf':  $datos = $this->pdf();   $tipo = 'application/pdf'; break;
            case 'csv':  $datos = $this->csv();   $tipo = 'text/csv; charset=UTF-8'; break;
            default:     $datos = $this->excel(); $tipo = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }
        $nombre = $this->nombreArchivo();
        header('Content-Type: ' . $tipo);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $nombre)
            . '"; filename*=UTF-8\'\'' . rawurlencode($nombre));
        header('Content-Length: ' . strlen($datos));
        header('Cache-Control: private, no-store');
        echo $datos;
        exit;
    }
}
