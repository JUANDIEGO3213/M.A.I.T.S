<?php
/*********************************************************************
    [M.A.I.T.S] Censura de palabras ofensivas.

    Al guardar un ticket o un mensaje, las palabras de la lista se
    reemplazan por asteriscos (una * por cada letra), como en el chat
    de los videojuegos. Se aplica al asunto del ticket y a todos los
    mensajes, respuestas y notas.

    La lista se administra en Panel de administración → Administrar →
    Censuras (scp/censuras.php) y se guarda en la tabla de configuración
    de osTicket (ost_config, namespace "maits.censura"), así que no hace
    falta crear tablas nuevas.

    Cómo se reconoce una palabra:
      - Sin importar mayúsculas ni acentos (PENDEJO = pendejo = péndejo).
      - Letras repetidas cuentan como una (puuuuto = puto).
      - Números y símbolos usados como letras (p3nd3j0, put@).
      - Plurales simples (menso → mensos; cabrón → cabrones).
      - Palabra completa: "puta" NO censura "computadora".
      - Si la palabra termina en *, censura todo lo que empiece así
        (pendej* → pendejo, pendeja, pendejada...).
**********************************************************************/

class MaitsCensura {

    const NS = 'maits.censura';
    const MARCA = '__lista_inicial';
    const MAX_LARGO = 60;

    // Lista con la que arranca la primera vez (el administrador puede
    // quitar o agregar las que quiera).
    static $inicial = array(
        'pendej*', 'pndj', 'puto', 'puta', 'ching*', 'verga', 'vrg',
        'culer*', 'culo', 'cabron*', 'mierd*', 'joto', 'maricon', 'marica',
        'idiota', 'imbecil', 'estupid*', 'menso', 'mensa', 'baboso', 'babosa',
        'tarado', 'tarada', 'pinche', 'mamon', 'mamona', 'ojete', 'huevon*',
        'carajo', 'coño', 'joder', 'malparid*', 'cagad*', 'puñet*',
        'ptm', 'hdp', 'alv', 'ctm',
    );

    private static $exactas = null;   // normalizada => true
    private static $prefijos = null;  // lista de prefijos normalizados

    /* ---------------- Normalización ---------------- */

    static function normalizar($palabra) {
        $p = mb_strtolower(trim($palabra), 'UTF-8');
        $p = strtr($p, array(
            'á'=>'a','à'=>'a','ä'=>'a','â'=>'a','é'=>'e','è'=>'e','ë'=>'e','ê'=>'e',
            'í'=>'i','ì'=>'i','ï'=>'i','î'=>'i','ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o',
            'ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u',
            '0'=>'o','1'=>'i','3'=>'e','4'=>'a','5'=>'s','7'=>'t','@'=>'a','$'=>'s',
        ));
        // letras repetidas cuentan como una
        return preg_replace('/(.)\1+/u', '$1', $p);
    }

    /** Clave con la que se guarda (normalizada, con * si es "empieza con") */
    static function clave($palabra) {
        $palabra = trim($palabra);
        $prefijo = substr($palabra, -1) == '*';
        return self::normalizar(rtrim($palabra, '*')) . ($prefijo ? '*' : '');
    }

    /* ---------------- Lista de palabras ---------------- */

    private static function asegurarLista() {
        $sql = 'SELECT 1 FROM '.CONFIG_TABLE.' WHERE namespace='.db_input(self::NS)
            .' AND `key`='.db_input(self::MARCA);
        if (db_num_rows(db_query($sql)))
            return;
        foreach (self::$inicial as $p)
            self::insertar($p, 'Lista inicial');
        db_query('INSERT IGNORE INTO '.CONFIG_TABLE.' (namespace, `key`, value, updated) VALUES ('
            .db_input(self::NS).','.db_input(self::MARCA).",'1',NOW())");
    }

    private static function insertar($palabra, $autor) {
        $valor = json_encode(array('p' => $palabra, 'por' => $autor,
            'creada' => date('Y-m-d H:i:s')), JSON_UNESCAPED_UNICODE);
        return db_query('INSERT IGNORE INTO '.CONFIG_TABLE.' (namespace, `key`, value, updated) VALUES ('
            .db_input(self::NS).','.db_input(self::clave($palabra)).','.db_input($valor).',NOW())')
            && db_affected_rows() > 0;
    }

    /** Todas las palabras: id, palabra, clave, por, creada, actualizada */
    static function todas() {
        self::asegurarLista();
        $res = db_query('SELECT id, `key`, value, updated FROM '.CONFIG_TABLE
            .' WHERE namespace='.db_input(self::NS).' AND `key` NOT LIKE '.db_input('\_\_%'));
        $lista = array();
        while ($res && ($r = db_fetch_array($res))) {
            $v = json_decode($r['value'], true) ?: array('p' => $r['value']);
            $lista[] = array(
                'id' => (int) $r['id'],
                'palabra' => $v['p'],
                'clave' => $r['key'],
                'prefijo' => substr($r['key'], -1) == '*',
                'por' => $v['por'] ?? '',
                'creada' => $v['creada'] ?? $r['updated'],
                'actualizada' => $r['updated'],
            );
        }
        usort($lista, function($a, $b) { return strcmp($a['clave'], $b['clave']); });
        return $lista;
    }

    static function buscar($id) {
        foreach (self::todas() as $p)
            if ($p['id'] == $id) return $p;
        return null;
    }

    private static function cargar() {
        if (self::$exactas !== null)
            return;
        self::$exactas = array();
        self::$prefijos = array();
        try {
            foreach (self::todas() as $p) {
                if ($p['prefijo'])
                    self::$prefijos[] = rtrim($p['clave'], '*');
                else
                    self::$exactas[$p['clave']] = true;
            }
        } catch (Throwable $e) {
            // si algo falla con la lista, el ticket se guarda igual
        }
    }

    static function reiniciarCache() {
        self::$exactas = self::$prefijos = null;
    }

    /* ---------------- Validar / agregar / editar / eliminar ---------------- */

    /** Revisa una palabra escrita por el administrador. Devuelve el error o null. */
    static function validar($palabra, $ignorarId = 0) {
        if ($palabra === '')
            return 'Escribe la palabra.';
        if (mb_strlen($palabra) > self::MAX_LARGO)
            return 'Es demasiado larga (máximo '.self::MAX_LARGO.' letras).';
        if (preg_match('/\s/u', $palabra))
            return 'Escribe una sola palabra por renglón o separadas por coma (sin espacios).';
        if (!preg_match('/^[\p{L}\p{M}0-9@$]+\*?$/u', $palabra))
            return 'Escribe una sola palabra, sin espacios ni signos (solo puede llevar * al final).';
        $clave = self::clave($palabra);
        $base = rtrim($clave, '*');
        if (mb_strlen($base) < 2)
            return 'Es demasiado corta.';
        if (substr($clave, -1) == '*' && mb_strlen($base) < 3)
            return 'Para usar * escribe al menos 3 letras antes (por ejemplo: pendej*).';
        foreach (self::todas() as $p)
            if ($p['clave'] == $clave && $p['id'] != $ignorarId)
                return sprintf('"%s" ya está en la lista.', $p['palabra']);
        return null;
    }

    /**
     * Agrega una o varias palabras (separadas por coma, punto y coma o renglón).
     * Devuelve las agregadas; los problemas quedan en $errores (palabra => motivo).
     */
    static function agregar($texto, $autor, &$errores) {
        $errores = array();
        $agregadas = array();
        $vistas = array();
        foreach (preg_split('/[,;\r\n]+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY) as $palabra) {
            $palabra = trim($palabra);
            if ($palabra === '') continue;
            $clave = self::clave($palabra);
            if (isset($vistas[$clave])) continue;
            $vistas[$clave] = true;
            if ($e = self::validar($palabra)) {
                $errores[$palabra] = $e;
                continue;
            }
            if (self::insertar($palabra, $autor))
                $agregadas[] = $palabra;
            else
                $errores[$palabra] = 'No se pudo guardar.';
        }
        if (!$agregadas && !$errores)
            $errores[''] = 'Escribe al menos una palabra.';
        self::reiniciarCache();
        return $agregadas;
    }

    static function editar($id, $palabra, &$error) {
        $palabra = trim($palabra);
        if (!($actual = self::buscar($id))) {
            $error = 'Esa palabra ya no existe.';
            return false;
        }
        if ($error = self::validar($palabra, $id))
            return false;
        $valor = json_encode(array('p' => $palabra, 'por' => $actual['por'],
            'creada' => $actual['creada']), JSON_UNESCAPED_UNICODE);
        $ok = db_query('UPDATE '.CONFIG_TABLE.' SET `key`='.db_input(self::clave($palabra))
            .', value='.db_input($valor).', updated=NOW() WHERE id='.db_input((int) $id)
            .' AND namespace='.db_input(self::NS));
        self::reiniciarCache();
        if (!$ok) $error = 'No se pudo guardar.';
        return (bool) $ok;
    }

    static function eliminar(array $ids) {
        $ids = array_filter(array_map('intval', $ids));
        if (!$ids) return 0;
        db_query('DELETE FROM '.CONFIG_TABLE.' WHERE namespace='.db_input(self::NS)
            .' AND `key` NOT LIKE '.db_input('\_\_%')
            .' AND id IN ('.implode(',', $ids).')');
        self::reiniciarCache();
        return db_affected_rows();
    }

    /* ---------------- Censurar ---------------- */

    static function esProhibida($token) {
        self::cargar();
        $n = self::normalizar($token);
        if ($n === '') return false;
        if (isset(self::$exactas[$n]))
            return true;
        // plurales: mensos, cabrones
        if (substr($n, -1) == 's' && isset(self::$exactas[substr($n, 0, -1)]))
            return true;
        if (substr($n, -2) == 'es' && isset(self::$exactas[substr($n, 0, -2)]))
            return true;
        foreach (self::$prefijos as $pre)
            if (strncmp($n, $pre, strlen($pre)) === 0)
                return true;
        return false;
    }

    /** Censura texto simple (asunto, mensajes sin formato). */
    static function texto($s) {
        if (!is_string($s) || $s === '')
            return $s;
        self::cargar();
        if (!self::$exactas && !self::$prefijos)
            return $s;
        return preg_replace_callback('/[\p{L}\p{M}\p{N}@$]+/u', function($m) {
            return self::esProhibida($m[0])
                ? str_repeat('*', mb_strlen($m[0], 'UTF-8')) : $m[0];
        }, $s);
    }

    /** Censura HTML: solo cambia el texto visible, nunca etiquetas ni enlaces. */
    static function html($html) {
        if (!is_string($html) || $html === '')
            return $html;
        $partes = preg_split('/(<[^>]*>|&#?[a-zA-Z0-9]+;)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($partes === false)
            return $html;
        foreach ($partes as $i => $p) {
            if ($i % 2 == 0 && $p !== '')
                $partes[$i] = self::texto($p);
        }
        return implode('', $partes);
    }
}
