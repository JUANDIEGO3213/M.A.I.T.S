<?php
if(!defined('OSTCLIENTINC')) die('Access Denied');

$email=Format::input($_POST['luser']?:$_GET['e']);
$passwd=Format::input($_POST['lpasswd']?:$_GET['t']);

$content = Page::lookupByType('banner-client');

if ($content) {
    list($title, $body) = $ost->replaceTemplateVariables(
        array($content->getLocalName(), $content->getLocalBody()));
} else {
    $title = __('Sign In');
    $body = __('To better serve you, we encourage our clients to register for an account and verify the email address we have on record.');
}

?>
<h1><?php echo Format::display($title); ?></h1>
<p><?php echo Format::display($body); ?></p>
<form action="login.php" method="post" id="clientLogin">
    <?php csrf_token(); ?>
<div style="display:block;text-align:center">
    <?php if (false): ?>
    // =======================================================================
    // [MODIFICADO] Diego - 22/09/2026
    // Se oculto el login local (correo/contraseña)
    // Para dejar solo el acceso por Correo Institucional (por Microsoft 360)
    // Para revertir: cambiar "if (false)" por "if (true)"
    // Bloque afectado: <div calss= "login-box"> ... </div>
    // =======================================================================
    <div class="login-box">
    <strong><?php echo Format::htmlchars($errors['login']); ?></strong>
    <div>
        <input id="username" placeholder="<?php echo __('Email or Username'); ?>" type="text" name="luser" size="30" value="<?php echo $email; ?>" class="nowarn">
    </div>
    <div>
        <input id="passwd" placeholder="<?php echo __('Password'); ?>" type="password" name="lpasswd" size="30" maxlength="128" value="<?php echo $passwd; ?>" class="nowarn"></td>
    </div>
    <p>
        <input class="btn" type="submit" value="<?php echo __('Sign In'); ?>">
<?php if ($suggest_pwreset) { ?>
        <a style="padding-top:4px;display:inline-block;" href="pwreset.php"><?php echo __('Forgot My Password'); ?></a>
<?php } ?>
    </p>
    </div>
    <?php endif; ?>
<?php

$ext_bks = array();
foreach (UserAuthenticationBackend::allRegistered() as $bk)
    if ($bk instanceof ExternalAuthentication)
        $ext_bks[] = $bk;

if (count($ext_bks) == 1) {
    // [M.A.I.T.S] Un solo acceso (correo institucional): boton grande y explicado
    $bk = $ext_bks[0]; ?>
    <div class="maits-login-icono"><i class="icon-envelope"></i></div>
    <div class="maits-login-titulo">Entra con tu correo institucional</div>
    <a class="maits-boton principal" href="login.php?do=ext&amp;bk=<?php
        echo urlencode($bk->getBkId()); ?>"><i class="icon-signin"></i> Iniciar sesión con correo institucional</a>
    <p class="maits-ayuda">Se abrirá la página de inicio de sesión de Microsoft.
    Escribe tu correo <strong>@saltillo.tecnm.mx</strong> y la misma contraseña que usas en tu correo.</p>
<?php
} elseif (count($ext_bks)) {
    foreach ($ext_bks as $bk) { ?>
<div class="external-auth"><?php $bk->renderExternalLink(); ?></div><?php
    }
}
if ($cfg && $cfg->isClientRegistrationEnabled()) { ?>
    <p class="maits-ayuda">
    <?php echo __('Not yet registered?'); ?> <a href="account.php?do=create"><?php echo __('Create an account'); ?></a>
    </p>
<?php } ?>
</div>
</form>
<p class="maits-pie-login">
<?php
// [M.A.I.T.S] Antes este texto mandaba a "abrir un ticket", pero como se
// necesita iniciar sesion, el enlace regresaba a esta misma pagina.
// Solo se muestra si algun dia se permiten tickets sin iniciar sesion.
if (!$cfg->isClientLoginRequired()) {
    echo sprintf(__('If this is your first time contacting us or you\'ve lost the ticket number, please %s open a new ticket %s'),
        '<a href="open.php">', '</a>');
} else { ?>
    ¿Eres parte del personal que atiende los tickets? Entra por
    <a href="<?php echo ROOT_PATH; ?>scp/login.php">Acceso agentes</a>.
<?php } ?>
</p>
