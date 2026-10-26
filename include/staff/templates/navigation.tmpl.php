<?php
// [M.A.I.T.S] Menu principal del panel de agentes/administracion:
// orden de uso (Tickets primero), iconos y nombres faciles de entender.
$maits_tabs = array(
    // Panel de agentes                 icono              nombre                      que es (aparece al pasar el mouse)
    'tickets'   => array('icon-inbox',     'Tickets',              'Los reportes que mandan los usuarios'),
    'tasks'     => array('icon-check',     'Tareas',               'Trabajo interno entre agentes (el usuario no las ve)'),
    'users'     => array('icon-group',     'Usuarios',             'Las personas que han creado tickets'),
    'equipos'   => array('icon-sitemap',   'Equipos',              'Tus departamentos y equipos de trabajo'),
    'kbase'     => array('icon-book',      'Base de conocimientos','Respuestas predefinidas y preguntas frecuentes'),
    'dashboard' => array('icon-bar-chart', 'Estadísticas',         'Gráficas de tickets, directorio de agentes y tu perfil'),
    'apps'      => array('icon-th-large',  null,                   null),
    // Panel de administracion
    'settings'  => array('icon-cogs',      null, 'Configuración general del sistema'),
    'manage'    => array('icon-wrench',    null, 'Temas de ayuda, filtros, SLA, formularios y listas'),
    'emails'    => array('icon-envelope',  null, 'Cuentas de correo y plantillas de mensajes'),
    'staff'     => array('icon-user',      null, 'Agentes, equipos, roles y departamentos'),
);
// En administracion, "dashboard" es el panel de registros (otro icono y nombre)
if ($nav && $nav->isAdminPanel())
    $maits_tabs['dashboard'] = array('icon-dashboard', null, 'Registros del sistema e información del servidor');

if($nav && ($tabs=$nav->getTabs()) && is_array($tabs)){
    // Orden: primero las que estan en $maits_tabs (en ese orden), luego las demas
    $orden = array();
    foreach (array_keys($maits_tabs) as $k)
        if (isset($tabs[$k])) $orden[$k] = $tabs[$k];
    if (!$nav->isAdminPanel())
        $tabs = $orden + $tabs;

    foreach($tabs as $name =>$tab) {
        if ($tab['href'][0] != '/')
            $tab['href'] = ROOT_PATH . 'scp/' . $tab['href'];
        $m = isset($maits_tabs[$name]) ? $maits_tabs[$name] : array('icon-circle-blank', null, null);
        $texto = $m[1] ?: $tab['desc'];
        $ayuda = $m[2] ?: ($tab['title'] ?? '');
        echo sprintf('<li class="%s %s maits-tab-%s"><a href="%s" title="%s"><i class="%s"></i> %s</a>',
            isset($tab['active']) ? 'active':'inactive',
            @$tab['class'] ?: '',
            $name,
            $tab['href'], Format::htmlchars($ayuda), $m[0], $texto);
        if(!isset($tab['active']) && ($subnav=$nav->getSubMenu($name))){
            echo "<ul>\n";
            foreach($subnav as $k => $item) {
                if (isset($item['id']) && !($id=$item['id']))
                    $id="nav$k";
                if ($item['href'][0] != '/')
                    $item['href'] = ROOT_PATH . 'scp/' . $item['href'];

                echo sprintf(
                    '<li><a class="%s" href="%s" title="%s" id="%s">%s</a></li>',
                    $item['iconclass'],
                    $item['href'], $item['title'] ?? null,
                    $id ?? null, $item['desc']);
            }
            echo "\n</ul>\n";
        }
        echo "\n</li>\n";
    }
} ?>
