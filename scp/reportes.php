<?php
/*********************************************************************
    reportes.php   [M.A.I.T.S]

    Crear un reporte de tickets (Excel con logos, PDF o CSV).
    Incluye solo los tickets que el agente puede ver.
**********************************************************************/
require('staff.inc.php');
require_once(INCLUDE_DIR.'maits/reporte.php');

$errors = array();
$opciones = null;

if ($_POST && ($_POST['a'] ?? '') == 'generar') {
    $errores = array();
    $opciones = MaitsReporte::leerOpciones($_POST, $errores);
    if (!$errores) {
        $reporte = new MaitsReporte($thisstaff, $opciones);
        if ($reporte->validarTamano($errores))
            $reporte->descargar();   // termina aqui
    }
    $errors = $errores;
    $errors['err'] = 'No se pudo crear el reporte. Revisa lo que está marcado en rojo.';
}

$nav->setTabActive('dashboard');
$nav->setActiveSubMenu(4);
$ost->setPageTitle('Crear reporte');
require(STAFFINC_DIR.'header.inc.php');
require(STAFFINC_DIR.'maits-reportes.inc.php');
include(STAFFINC_DIR.'footer.inc.php');
