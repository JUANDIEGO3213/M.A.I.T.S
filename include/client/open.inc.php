<?php
if(!defined('OSTCLIENTINC')) die('Access Denied!');
$info=array();
if($thisclient && $thisclient->isValid()) {
    $info=array('name'=>$thisclient->getName(),
                'email'=>$thisclient->getEmail(),
                'phone'=>$thisclient->getPhoneNumber());
}

$info=($_POST && $errors)?Format::htmlchars($_POST):$info;

$form = null;
if (!$info['topicId']) {
    if (array_key_exists('topicId',$_GET) && preg_match('/^\d+$/',$_GET['topicId']) && Topic::lookup($_GET['topicId']))
        $info['topicId'] = intval($_GET['topicId']);
    else
        $info['topicId'] = $cfg->getDefaultTopicId();
}

$forms = array();
if ($info['topicId'] && ($topic=Topic::lookup($info['topicId']))) {
    foreach ($topic->getForms() as $F) {
        if (!$F->hasAnyVisibleFields())
            continue;
        if ($_POST) {
            $F = $F->instanciate();
            $F->isValidForClient();
        }
        $forms[] = $F->getForm();
    }
}

?>
<h1>Crear un ticket</h1>
<p class="maits-ayuda">Llena los 3 pasos y presiona <strong>Crear ticket</strong>. Te daremos un número para que sigas su avance.</p>
<form id="ticketForm" method="post" action="open.php" enctype="multipart/form-data">
  <?php csrf_token(); ?>
  <input type="hidden" name="a" value="open">
  <table width="100%" cellpadding="1" cellspacing="0" border="0">
    <tbody>
    <tr><td colspan="2">
        <div class="maits-paso-titulo"><span class="maits-num">1</span> Tus datos</div>
    </td></tr>
<?php
        if (!$thisclient) {
            $uform = UserForm::getUserForm()->getForm($_POST);
            if ($_POST) $uform->isValid();
            $uform->render(array('staff' => false, 'mode' => 'create'));
        }
        else { ?>
        <tr><td colspan="2">
            <dl class="maits-datos">
                <dt><?php echo __('Email'); ?></dt><dd><?php echo $thisclient->getEmail(); ?></dd>
                <dt>Nombre</dt><dd><?php echo Format::htmlchars($thisclient->getName()); ?></dd>
            </dl>
            <p class="maits-ayuda">Te avisaremos a este correo cuando haya novedades.</p>
        </td></tr>
        <?php } ?>
    </tbody>
    <tbody>
    <tr><td colspan="2">
        <div class="form-header">
        <div class="maits-paso-titulo"><span class="maits-num">2</span> <label for="topicId">¿En qué área es el problema?</label></div>
        <p class="maits-ayuda">Elige la opción que más se parezca a tu problema.</p>
        </div>
    </td></tr>
    <tr>
        <td colspan="2">
            <select id="topicId" name="topicId" onchange="javascript:
                    var data = $(':input[name]', '#dynamic-form').serialize();
                    $.ajax(
                      'ajax.php/form/help-topic/' + this.value,
                      {
                        data: data,
                        dataType: 'json',
                        success: function(json) {
                          $('#dynamic-form').empty().append(json.html);
                          $(document.head).append(json.media);
                        }
                      });">
                <option value="" selected="selected">&mdash; Selecciona un área &mdash;</option>
                <?php
                if($topics=Topic::getPublicHelpTopics()) {
                    foreach($topics as $id =>$name) {
                        echo sprintf('<option value="%d" %s>%s</option>',
                                $id, ($info['topicId']==$id)?'selected="selected"':'', $name);
                    }
                } ?>
            </select>
            <font class="error">*&nbsp;<?php echo $errors['topicId']; ?></font>
        </td>
    </tr>
    <tr><td colspan="2">
        <div class="maits-paso-titulo"><span class="maits-num">3</span> Describe el problema</div>
    </td></tr>
    </tbody>
    <tbody id="dynamic-form">
        <?php
        $options = array('mode' => 'create');
        foreach ($forms as $form) {
            include(CLIENTINC_DIR . 'templates/dynamic-form.tmpl.php');
        }
        if (!$forms) { // [M.A.I.T.S] Mensaje mientras no se elige un area ?>
        <tr class="maits-espera"><td colspan="2">
            <i class="icon-hand-up"></i> Primero elige un área en el paso 2. Aquí aparecerán los campos para describir tu problema.
        </td></tr>
        <?php } ?>
    </tbody>
    <tbody>
    <?php
    if($cfg && $cfg->isCaptchaEnabled() && (!$thisclient || !$thisclient->isValid())) {
        if($_POST && $errors && !$errors['captcha'])
            $errors['captcha']=__('Please re-enter the text again');
        ?>
    <tr class="captchaRow">
        <td class="required"><?php echo __('CAPTCHA Text');?>:</td>
        <td>
            <span class="captcha"><img src="captcha.php" border="0" align="left"></span>
            &nbsp;&nbsp;
            <input id="captcha" type="text" name="captcha" size="6" autocomplete="off">
            <em><?php echo __('Enter the text shown on the image.');?></em>
            <font class="error">*&nbsp;<?php echo $errors['captcha']; ?></font>
        </td>
    </tr>
    <?php
    } ?>
    <tr><td colspan=2>&nbsp;</td></tr>
    </tbody>
  </table>
  <p class="buttons maits-botones">
        <input type="submit" value="Crear ticket">
        <input type="reset" name="reset" value="Borrar todo">
        <input type="button" name="cancel" value="<?php echo __('Cancel'); ?>" onclick="javascript:
            $('.richtext').each(function() {
                var redactor = $(this).data('redactor');
                if (redactor && redactor.opts.draftDelete)
                    redactor.plugin.draft.deleteDraft();
            });
            window.location.href='index.php';">
  </p>
</form>
