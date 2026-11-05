<?php
/*********************************************************************
    equipos.php   [M.A.I.T.S]

    Pestaña "Equipos" del panel de agentes:
      - Mi área: departamentos y equipos del agente (todos los agentes).
      - Gestionar equipos: crear/editar equipos, líder y miembros
        (solo con el permiso de rol "Crear y editar equipos").
**********************************************************************/
require('staff.inc.php');

$puede = MaitsEquipos::puedeGestionar($thisstaff);
$accion = $_REQUEST['a'] ?? '';
$errors = array();
$equipo = null;

if (in_array($accion, array('gestionar', 'nuevo', 'editar', 'guardar')) && !$puede) {
    $errors['err'] = 'No tienes permiso para crear o editar equipos. Pídele a un administrador que active "Crear y editar equipos" en tu rol.';
    $accion = '';
}

if ($accion == 'editar' || $accion == 'guardar') {
    if (!empty($_REQUEST['id']) && !($equipo = Team::lookup((int) $_REQUEST['id']))) {
        $errors['err'] = 'Ese equipo no existe.';
        $accion = 'gestionar';
    }
}

if ($_POST && $accion == 'guardar' && $puede) {
    $nuevo = !$equipo;
    $equipo = $equipo ?: Team::create();
    if (MaitsEquipos::guardar($equipo, $_POST, $errors)) {
        $_SESSION['::sysmsgs']['msg'] = $nuevo
            ? sprintf('Equipo "%s" creado.', Format::htmlchars($equipo->getName()))
            : sprintf('Equipo "%s" actualizado.', Format::htmlchars($equipo->getName()));
        Http::redirect('equipos.php?a=gestionar');
    }
    if (empty($errors['err']))
        $errors['err'] = 'Revisa los datos marcados en rojo.';
    $accion = $nuevo ? 'nuevo' : 'editar';
    if ($nuevo) $equipo = null;
}

$nav->setTabActive('equipos');
// Marcar solo la opcion actual del submenu (las dos apuntan a equipos.php)
$nav->setActiveSubMenu(in_array($accion, array('gestionar', 'nuevo', 'editar')) ? 2 : 1);
$ost->setPageTitle(in_array($accion, array('gestionar', 'nuevo', 'editar'))
    ? 'Gestionar equipos' : 'Mi área');

require(STAFFINC_DIR.'header.inc.php');
require(STAFFINC_DIR.'maits-equipos.inc.php');
include(STAFFINC_DIR.'footer.inc.php');
