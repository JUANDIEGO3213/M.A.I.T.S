<?php
/*********************************************************************
    censuras.php   [M.A.I.T.S]

    Panel de administración → Administrar → Censuras.
    Lista de palabras ofensivas que se cambian por asteriscos en los
    tickets y mensajes. Solo para administradores.
**********************************************************************/
require('admin.inc.php');
require_once(INCLUDE_DIR.'maits/censura.php');

$errors = array();
$accion = $_REQUEST['a'] ?? '';
$palabra = null;
$prueba = null;

if (!empty($_REQUEST['id']) && !($palabra = MaitsCensura::buscar((int) $_REQUEST['id']))) {
    $errors['err'] = 'Esa palabra ya no existe (tal vez alguien la eliminó).';
}

if ($_POST) {
    switch ($_POST['do'] ?? '') {
    case 'agregar':
        $errores = array();
        $agregadas = MaitsCensura::agregar($_POST['palabras'] ?? '', (string) $thisstaff->getName(), $errores);
        $texto = '';
        if ($agregadas) {
            $texto = (count($agregadas) == 1)
                ? sprintf('Se agregó la censura "%s".', Format::htmlchars($agregadas[0]))
                : sprintf('Se agregaron %d censuras: %s.', count($agregadas),
                    Format::htmlchars(implode(', ', $agregadas)));
        }
        if ($errores) {
            $lineas = array();
            foreach ($errores as $p => $e)
                $lineas[] = ($p !== '' ? '<strong>'.Format::htmlchars($p).'</strong>: ' : '').Format::htmlchars($e);
            if (!$agregadas) {
                $errors['err'] = 'No se agregó ninguna palabra:<br>'.implode('<br>', $lineas);
                $accion = 'agregar';
                break;
            }
            $_SESSION['::sysmsgs']['warn'] = $texto.'<br>Estas no se agregaron:<br>'.implode('<br>', $lineas);
        } else {
            $_SESSION['::sysmsgs']['msg'] = $texto;
        }
        Http::redirect('censuras.php');
        break;

    case 'editar':
        $error = null;
        if ($palabra && MaitsCensura::editar($palabra['id'], $_POST['palabra'] ?? '', $error)) {
            $_SESSION['::sysmsgs']['msg'] = sprintf('Se guardó la censura "%s".',
                Format::htmlchars(trim($_POST['palabra'])));
            Http::redirect('censuras.php');
        }
        $errors['palabra'] = $error;
        $errors['err'] = 'No se guardó el cambio. Revisa lo marcado en rojo.';
        break;

    case 'mass_process':
        $ids = (isset($_POST['ids']) && is_array($_POST['ids'])) ? $_POST['ids'] : array();
        if (!$ids) {
            $errors['err'] = 'Marca al menos una palabra para eliminarla.';
        } elseif (strtolower($_POST['a'] ?? '') == 'delete') {
            $n = MaitsCensura::eliminar($ids);
            $_SESSION['::sysmsgs']['msg'] = ($n == 1)
                ? 'Se eliminó 1 censura.' : sprintf('Se eliminaron %d censuras.', $n);
            Http::redirect('censuras.php');
        }
        break;

    case 'probar':
        $prueba = array(
            'original' => (string) ($_POST['texto'] ?? ''),
            'resultado' => MaitsCensura::texto((string) ($_POST['texto'] ?? '')),
        );
        break;
    }
}

$nav->setTabActive('manage');
$ost->setPageTitle('Censuras');
require(STAFFINC_DIR.'header.inc.php');
require(STAFFINC_DIR.'maits-censuras.inc.php');
include(STAFFINC_DIR.'footer.inc.php');
