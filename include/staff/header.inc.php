<?php
header("Content-Type: text/html; charset=UTF-8");
header("Content-Security-Policy: frame-ancestors ".$cfg->getAllowIframes()."; script-src 'self' 'unsafe-inline' 'unsafe-eval'; object-src 'none'");

// [M.A.I.T.S] Titulo de la pestana del navegador: "<titulo del sitio>:: Agentes"
// (o ":: Administración"). El titulo del sitio sale de Administración ->
// Configuración -> "Nombre/título de Helpdesk"; en la copia de pruebas
// dice "M.A.I.T.S (PRUEBAS)". En un ticket o tarea se agrega su número.
$maits_sitio = ($cfg && $cfg->getTitle()) ? $cfg->getTitle() : 'M.A.I.T.S';
$maits_seccion = (isset($nav) && $nav && $nav->isAdminPanel()) ? 'Administración' : 'Agentes';
$maits_pagina = $ost ? $ost->getPageTitle() : '';
$title = $maits_sitio.':: '.$maits_seccion
    .(($maits_pagina && stripos($maits_pagina, 'osTicket') === false) ? ' - '.$maits_pagina : '');

if (!isset($_SERVER['HTTP_X_PJAX'])) { ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html<?php
if (($lang = Internationalization::getCurrentLanguage())
        && ($info = Internationalization::getLanguageInfo($lang))
        && (@$info['direction'] == 'rtl'))
    echo ' dir="rtl" class="rtl"';
if ($lang) {
    echo ' lang="' . Internationalization::rfc1766($lang) . '"';
}

// Dropped IE Support Warning
if (osTicket::is_ie())
    $ost->setWarning(__('osTicket no longer supports Internet Explorer.'));
?>>
<head>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta http-equiv="cache-control" content="no-cache" />
    <meta http-equiv="pragma" content="no-cache" />
    <meta http-equiv="x-pjax-version" content="<?php echo GIT_VERSION; ?>">
    <title><?php echo Format::htmlchars($title); ?></title>
    <!--[if IE]>
    <style type="text/css">
        .tip_shadow { display:block !important; }
    </style>
    <![endif]-->
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-3.7.0.min.js?8d38b06"></script>
    <link rel="stylesheet" href="<?php echo ROOT_PATH ?>css/thread.css?8d38b06" media="all"/>
    <link rel="stylesheet" href="<?php echo ROOT_PATH ?>scp/css/scp.css?8d38b06" media="all"/>
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/redactor.css?8d38b06" media="screen"/>
    <link rel="stylesheet" href="<?php echo ROOT_PATH ?>css/typeahead.css?8d38b06" media="screen"/>
    <link type="text/css" href="<?php echo ROOT_PATH; ?>css/ui-lightness/jquery-ui-1.13.2.custom.min.css?8d38b06"
         rel="stylesheet" media="screen" />
    <link rel="stylesheet" href="<?php echo ROOT_PATH ?>css/jquery-ui-timepicker-addon.css?8d38b06" media="all"/>
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/font-awesome.min.css?8d38b06"/>
    <!--[if IE 7]>
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/font-awesome-ie7.min.css?8d38b06"/>
    <![endif]-->
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH ?>scp/css/dropdown.css?8d38b06"/>
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/loadingbar.css?8d38b06"/>
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/flags.css?8d38b06"/>
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/select2.min.css?8d38b06"/>
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/rtl.css?8d38b06"/>
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH ?>scp/css/translatable.css?8d38b06"/>
    <!-- [M.A.I.T.S] Estilo propio del panel de agentes (va despues de scp.css) -->
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH ?>scp/css/maits-agentes.css?v=8"/>
    <script type="text/javascript" src="<?php echo ROOT_PATH ?>scp/js/maits-agentes.js?v=3"></script>
    <!-- Favicons -->
    <link rel="icon" type="image/png" href="<?php echo ROOT_PATH ?>images/oscar-favicon-32x32.png" sizes="32x32" />
    <link rel="icon" type="image/png" href="<?php echo ROOT_PATH ?>images/oscar-favicon-16x16.png" sizes="16x16" />

    <?php
    if($ost && ($headers=$ost->getExtraHeaders())) {
        echo "\n\t".implode("\n\t", $headers)."\n";
    }
    ?>
</head>
<body class="<?php echo (isset($nav) && $nav && $nav->isAdminPanel()) ? 'maits-admin' : 'maits-agente'; ?>">
<div id="container">
    <?php
    if($ost->getError())
        echo sprintf('<div id="error_bar">%s</div>', $ost->getError());
    elseif($ost->getWarning())
        echo sprintf('<div id="warning_bar">%s</div>', $ost->getWarning());
    elseif($ost->getNotice())
        echo sprintf('<div id="notice_bar">%s</div>', $ost->getNotice());
    ?>
    <div id="header">
        <?php // [M.A.I.T.S] Encabezado: logo, en que panel estas y tu cuenta
        $maits_admin = isset($nav) && $nav && $nav->isAdminPanel(); ?>
        <a href="<?php echo ROOT_PATH ?>scp/index.php" class="no-pjax" id="logo" title="Ir a los tickets">
            <span class="valign-helper"></span>
            <img src="<?php echo ROOT_PATH ?>scp/logo.php?<?php echo strtotime($cfg->lastModified('staff_logo_id')); ?>" alt="osTicket &mdash; <?php echo __('Customer Support System'); ?>"/>
        </a>
        <div class="maits-modo <?php echo $maits_admin ? 'admin' : ''; ?>"
            title="<?php echo $maits_admin
                ? 'Aquí se configura el sistema. Los cambios afectan a todos.'
                : 'Estás en la sección del personal que atiende los tickets.'; ?>">
            <i class="<?php echo $maits_admin ? 'icon-cogs' : 'icon-briefcase'; ?>"></i>
            <?php echo $maits_admin ? 'Panel de administración' : 'Panel de agentes'; ?>
        </div>
        <p id="info" class="no-pjax">
            <span class="maits-hola">Hola, <strong><?php echo Format::htmlchars($thisstaff->getFirstName()); ?></strong></span>
           <?php
            // Solo los administradores ven el boton para cambiar de panel
            if($thisstaff->isAdmin() && !defined('ADMINPAGE')) { ?>
            <a href="<?php echo ROOT_PATH ?>scp/admin.php" class="no-pjax" title="Configuración del sistema (solo administradores)"><i class="icon-cogs"></i> Administración</a>
            <?php } elseif (defined('ADMINPAGE')) { ?>
            <a href="<?php echo ROOT_PATH ?>scp/index.php" class="no-pjax" title="Volver a los tickets"><i class="icon-briefcase"></i> Panel de agentes</a>
            <?php } ?>
            <a href="<?php echo ROOT_PATH ?>scp/profile.php"><i class="icon-user"></i> Mi perfil</a>
            <a href="<?php echo ROOT_PATH ?>scp/logout.php?auth=<?php echo $ost->getLinkToken(); ?>" class="no-pjax maits-salir"><i class="icon-signout"></i> Salir</a>
        </p>
    </div>
    <div id="pjax-container" class="<?php if ($_POST) echo 'no-pjax'; ?>">
<?php } else {
    header('X-PJAX-Version: ' . GIT_VERSION);
    if ($pjax = $ost->getExtraPjax()) { ?>
    <script type="text/javascript">
    <?php foreach (array_filter($pjax) as $s) echo $s.";"; ?>
    </script>
    <?php }
    foreach ($ost->getExtraHeaders() as $h) {
        if (strpos($h, '<script ') !== false)
            echo $h;
    } ?>
    <title><?php echo Format::htmlchars($title); ?></title><?php
} # endif X_PJAX ?>
    <ul id="nav">
<?php include STAFFINC_DIR . "templates/navigation.tmpl.php"; ?>
    </ul>
    <?php include STAFFINC_DIR . "templates/sub-navigation.tmpl.php"; ?>
    <?php include STAFFINC_DIR . "templates/maits-ayuda.tmpl.php"; // [M.A.I.T.S] ayuda de cada seccion ?>

        <div id="content">
        <?php if(isset($errors['err'])) { ?>
            <div id="msg_error"><?php echo $errors['err']; ?></div>
        <?php }elseif($msg) { ?>
            <div id="msg_notice"><?php echo $msg; ?></div>
        <?php }elseif($warn) { ?>
            <div id="msg_warning"><?php echo $warn; ?></div>
        <?php }
        foreach (Messages::getMessages() as $M) { ?>
            <div class="<?php echo strtolower($M->getLevel()); ?>-banner"><?php
                echo (string) $M; ?></div>
<?php   } ?>
