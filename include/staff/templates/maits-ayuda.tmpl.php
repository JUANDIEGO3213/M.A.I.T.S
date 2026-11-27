<?php
/*********************************************************************
    [M.A.I.T.S] Ayuda breve de cada seccion del panel de agentes.

    Explica para que sirve la pantalla en la que esta el agente.
    Cada agente puede ocultarla con el boton "Ya entendí" (se recuerda
    en su navegador) y volver a verla con "¿Qué es esta sección?".
    Para cambiar los textos, edita el arreglo $maits_ayudas.
**********************************************************************/
if (!isset($nav) || !$nav)
    return;

$maits_script = basename($_SERVER['SCRIPT_NAME']);
$maits_tab = $nav->getActiveTab();
$maits_clave = null;

if ($maits_script == 'censuras.php') {
    $maits_clave = 'censuras';
} elseif ($nav->isAdminPanel()) {
    $maits_clave = 'admin';
} elseif ($maits_script == 'tickets.php' || $maits_script == 'index.php') {
    if (!empty($_REQUEST['id']))
        $maits_clave = 'ticket';
    elseif (($_REQUEST['a'] ?? '') == 'open')
        $maits_clave = 'nuevo-ticket';
    else
        $maits_clave = 'tickets';
} elseif ($maits_script == 'tasks.php') {
    $maits_clave = !empty($_REQUEST['id']) ? 'tarea' : 'tareas';
} elseif ($maits_script == 'users.php' || $maits_script == 'orgs.php') {
    $maits_clave = 'usuarios';
} elseif (in_array($maits_script, array('kb.php', 'faq.php', 'categories.php', 'canned.php'))) {
    $maits_clave = 'kb';
} elseif ($maits_script == 'equipos.php') {
    $maits_clave = in_array($_REQUEST['a'] ?? '', array('gestionar', 'nuevo', 'editar', 'guardar'))
        ? 'equipos-gestionar' : 'equipos';
} elseif ($maits_script == 'reportes.php') {
    $maits_clave = 'reportes';
} elseif ($maits_script == 'dashboard.php') {
    $maits_clave = 'estadisticas';
} elseif ($maits_script == 'directory.php') {
    $maits_clave = 'directorio';
} elseif ($maits_script == 'profile.php') {
    $maits_clave = 'perfil';
}

// titulo => lista de puntos (texto con HTML sencillo)
$maits_ayudas = array(
    'tickets' => array('Tickets: los reportes de los usuarios', array(
        'Cada fila es un problema que reportó un usuario. <strong>Haz clic en el asunto</strong> para leerlo y contestar.',
        '<strong>Abiertos</strong> son los pendientes, <strong>Mis Solicitudes</strong> los que tienes asignados y <strong>Cerrado</strong> los ya resueltos.',
        'Los asuntos en <strong>negritas</strong> tienen mensajes nuevos sin responder. Para cambiar varios a la vez, marca las casillas y usa los botones de la derecha.',
    )),
    'ticket' => array('Atender un ticket', array(
        'Arriba está la información del ticket y debajo la conversación con el usuario.',
        'Al final de la página escribe: <strong>Responder al usuario</strong> le llega por correo; <strong>Nota interna</strong> solo la ven los agentes.',
        'Con los botones de la barra puedes <strong>asignarlo</strong> a un agente, <strong>transferirlo</strong> a otro departamento o <strong>cambiar el estado</strong> (ciérralo cuando el problema quede resuelto).',
    )),
    'nuevo-ticket' => array('Crear un ticket a nombre de un usuario', array(
        'Úsalo cuando alguien te reporte un problema en persona o por teléfono.',
        'Busca al usuario por su correo (o créalo), elige el tema de ayuda y describe el problema.',
    )),
    'tareas' => array('Tareas: trabajo interno del equipo', array(
        'Sirven para repartir pendientes entre agentes, por ejemplo "comprar refacción" o "revisar el salón 3A".',
        'El usuario <strong>no ve</strong> las tareas. Pueden estar ligadas a un ticket o ser independientes.',
    )),
    'tarea' => array('Atender una tarea', array(
        'Lee la descripción, deja notas con tu avance y ciérrala cuando termines.',
    )),
    'usuarios' => array('Usuarios: quién ha reportado problemas', array(
        'Aquí están todas las personas que han creado tickets. Abre un nombre para ver sus datos y todos sus tickets.',
        '<strong>Activo (registrado)</strong> = ya inició sesión con su correo. <strong>Invitado</strong> = solo se conoce su correo.',
    )),
    'kb' => array('Base de conocimientos', array(
        '<strong>Respuestas predefinidas</strong>: textos que escribes una vez y luego insertas al responder un ticket, para no repetir lo mismo.',
        '<strong>Preguntas frecuentes</strong>: artículos con soluciones a problemas comunes.',
    )),
    'estadisticas' => array('Estadísticas', array(
        'Gráfica y tablas de cuántos tickets se crearon, cerraron o vencieron por periodo, departamento, tema o agente.',
        'Cambia las fechas arriba y presiona <strong>Actualizar</strong>. También puedes exportar los datos.',
    )),
    'equipos' => array('Mi área: con quién trabajas', array(
        '<strong>Departamentos</strong>: las áreas donde recibes tickets, tu rol en cada una y quién es el gerente.',
        '<strong>Equipos</strong>: los grupos de agentes a los que perteneces y quién es su líder.',
    )),
    'equipos-gestionar' => array('Gestionar equipos', array(
        'Crea un equipo, elige a su <strong>líder</strong> (el encargado) y a sus <strong>miembros</strong>.',
        'Después, desde un ticket, usa <strong>Asignar → Equipo</strong> para mandárselo a ese equipo.',
    )),
    'reportes' => array('Crear reporte', array(
        'Sigue los 3 pasos: qué tickets, qué datos y el tipo de archivo. Lo marcado como <strong>Recomendado</strong> ya viene listo.',
        '<strong>Excel</strong> sale con logos y colores; <strong>PDF</strong> sirve para imprimir; <strong>CSV</strong> es solo texto.',
    )),
    'directorio' => array('Directorio de agentes', array(
        'La lista del personal que atiende tickets, con su departamento y datos de contacto.',
    )),
    'perfil' => array('Mi perfil', array(
        'Cambia tu nombre, teléfono, contraseña, firma para las respuestas y preferencias (idioma, zona horaria, avisos por correo).',
    )),
    'censuras' => array('Censuras: palabras que no se permiten', array(
        'Cuando alguien crea un ticket o escribe un mensaje, las palabras de esta lista se cambian por <strong>asteriscos</strong> (una * por cada letra) en cuanto se envía. Así lo ven los usuarios, los agentes y los correos.',
        'No importan mayúsculas, acentos, letras repetidas ni números en lugar de letras (<em>PUUUTO</em> o <em>p3nd3jo</em> también se censuran) y se incluyen los plurales (<em>menso</em> → <em>mensos</em>).',
        'Solo se censura la <strong>palabra completa</strong>: "puta" no afecta a "computadora". Si quieres todo lo que <strong>empiece</strong> igual, termina la palabra con * (<em>pendej*</em> → pendejo, pendeja, pendejada).',
        '<strong>Agregar censura</strong> (verde) para sumar palabras, <strong>Eliminar censura</strong> (rojo) para quitar las que marques, y haz clic en una palabra para corregirla. Abajo puedes <strong>probar</strong> un texto. Los tickets que ya existían no cambian.',
    )),
    'admin' => array('Panel de administración', array(
        'Aquí se configura todo el sistema: departamentos, temas de ayuda, agentes, correos y más.',
        '<strong>Los cambios afectan a todos los usuarios y agentes.</strong> Si no estás seguro de algo, pregunta antes de guardar.',
        'Para volver a atender tickets usa <strong>Panel de agentes</strong> (arriba a la derecha).',
    )),
);

if (!$maits_clave || !isset($maits_ayudas[$maits_clave]))
    return;
list($maits_titulo, $maits_puntos) = $maits_ayudas[$maits_clave];
?>
<div class="maits-ayuda" data-ayuda="<?php echo $maits_clave; ?>">
    <div class="maits-ayuda-cuerpo">
        <i class="icon-lightbulb maits-ayuda-icono"></i>
        <div>
            <strong class="maits-ayuda-titulo"><?php echo $maits_titulo; ?></strong>
            <ul>
            <?php foreach ($maits_puntos as $p) echo "<li>$p</li>\n"; ?>
            </ul>
        </div>
        <button type="button" class="maits-ayuda-cerrar" title="Ocultar esta ayuda">Ya entendí</button>
    </div>
    <button type="button" class="maits-ayuda-abrir"><i class="icon-question-sign"></i> ¿Qué es esta sección?</button>
</div>
