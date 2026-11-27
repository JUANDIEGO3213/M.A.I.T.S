<?php
/*********************************************************************
    [M.A.I.T.S] Pantalla "Censuras" (Administrar → Censuras).
    Variables que llegan de scp/censuras.php:
      $accion, $palabra (al editar), $errors, $prueba
**********************************************************************/
if (!defined('OSTADMININC') || !$thisstaff->isAdmin()) die('Access Denied');

$h = function($s) { return Format::htmlchars($s); };

/* ======================= Agregar ======================= */
if ($accion == 'agregar') { ?>
<div class="maits-censuras">
  <h2><a href="censuras.php">Censuras</a> <span class="maits-cens-migas">› Agregar censura</span></h2>
  <form action="censuras.php?a=agregar" method="post" class="maits-cens-tarjeta">
    <?php csrf_token(); ?>
    <input type="hidden" name="do" value="agregar">
    <label for="palabras" class="maits-cens-etiqueta">Palabras que se van a censurar</label>
    <p class="maits-cens-ayuda">Puedes escribir una o varias, separadas por coma o en renglones distintos.
      Termina una palabra con <code>*</code> para censurar todo lo que empiece así (por ejemplo <code>pendej*</code>).</p>
    <textarea id="palabras" name="palabras" rows="5" autofocus
      placeholder="Ejemplo: menso, tarado, culer*"><?php echo $h($_POST['palabras'] ?? ''); ?></textarea>
    <div class="maits-cens-botones">
      <button type="submit" class="maits-btn-verde"><i class="icon-plus-sign"></i> Agregar censura</button>
      <a href="censuras.php" class="button">Cancelar</a>
    </div>
  </form>
</div>
<?php
    return;
}

/* ======================= Editar ======================= */
if ($palabra) { ?>
<div class="maits-censuras">
  <h2><a href="censuras.php">Censuras</a> <span class="maits-cens-migas">› Editar "<?php echo $h($palabra['palabra']); ?>"</span></h2>
  <form action="censuras.php?id=<?php echo $palabra['id']; ?>" method="post" class="maits-cens-tarjeta">
    <?php csrf_token(); ?>
    <input type="hidden" name="do" value="editar">
    <input type="hidden" name="id" value="<?php echo $palabra['id']; ?>">
    <label for="palabra" class="maits-cens-etiqueta">Palabra</label>
    <p class="maits-cens-ayuda">Corrige cómo está escrita. Termina con <code>*</code> si quieres censurar todo lo que empiece así.</p>
    <input type="text" id="palabra" name="palabra" maxlength="<?php echo MaitsCensura::MAX_LARGO; ?>" autofocus
      value="<?php echo $h($_POST['palabra'] ?? $palabra['palabra']); ?>">
    <?php if (!empty($errors['palabra'])) { ?>
      <div class="maits-cens-error"><?php echo $h($errors['palabra']); ?></div>
    <?php } ?>
    <p class="maits-cens-dato">Agregada por <?php echo $h($palabra['por'] ?: '—'); ?>
      el <?php echo Format::datetime($palabra['creada']); ?>.</p>
    <div class="maits-cens-botones">
      <button type="submit" class="maits-btn-guardar"><i class="icon-save"></i> Guardar cambios</button>
      <a href="censuras.php" class="button">Cancelar</a>
    </div>
  </form>
</div>
<?php
    return;
}

/* ======================= Lista ======================= */
$lista = MaitsCensura::todas();
?>
<div class="maits-censuras">
<form action="censuras.php" method="post" name="censuras" id="maits-cens-form">
  <div class="sticky bar opaque">
    <div class="content">
      <div class="pull-left flush-left">
        <h2>Censuras</h2>
      </div>
      <div class="pull-right flush-right" id="actions">
        <a href="censuras.php?a=agregar" class="maits-btn-verde"><i class="icon-plus-sign"></i> Agregar censura</a>
        <a href="#" class="maits-btn-rojo confirm" data-name="delete"><i class="icon-trash"></i> Eliminar censura</a>
      </div>
    </div>
  </div>
  <div class="clear"></div>
  <?php csrf_token(); ?>
  <input type="hidden" name="do" value="mass_process">
  <input type="hidden" id="action" name="a" value="">

  <div class="maits-cens-resumen">
    <span><strong><?php echo count($lista); ?></strong> palabra<?php echo count($lista) == 1 ? '' : 's'; ?> en la lista.
      Se cambian por <code>****</code> en los tickets y mensajes nuevos.</span>
    <input type="search" id="maits-cens-buscar" placeholder="Buscar palabra…" autocomplete="off">
  </div>

  <table class="list maits-cens-tabla" border="0" cellspacing="1" cellpadding="0" width="100%">
    <thead>
      <tr>
        <th width="4%">&nbsp;</th>
        <th width="26%">Palabra</th>
        <th width="22%">Se censura</th>
        <th width="16%">Agregada por</th>
        <th width="16%">Última actualización</th>
        <th width="16%">Creada</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $marcadas = (!empty($errors) && isset($_POST['ids']) && is_array($_POST['ids'])) ? $_POST['ids'] : array();
    foreach ($lista as $p) {
        $base = rtrim($p['palabra'], '*'); ?>
      <tr data-buscar="<?php echo $h(mb_strtolower($p['palabra']).' '.$p['clave']); ?>">
        <td align="center"><input type="checkbox" class="ckb" name="ids[]" value="<?php echo $p['id']; ?>"
          <?php echo in_array($p['id'], $marcadas) ? 'checked' : ''; ?>></td>
        <td><a href="censuras.php?id=<?php echo $p['id']; ?>" title="Editar esta palabra"><?php echo $h($p['palabra']); ?></a></td>
        <td class="maits-cens-tipo"><?php
            echo $p['prefijo']
                ? 'Todo lo que empiece con <strong>'.$h($base).'</strong>'
                : 'Palabra completa y plural'; ?></td>
        <td><?php echo $h($p['por'] ?: '—'); ?></td>
        <td><?php echo Format::datetime($p['actualizada']); ?></td>
        <td><?php echo Format::datetime($p['creada']); ?></td>
      </tr>
    <?php }
    if (!$lista) { ?>
      <tr><td colspan="6">Todavía no hay palabras. Usa <strong>Agregar censura</strong>.</td></tr>
    <?php } ?>
      <tr class="maits-cens-nada" style="display:none"><td colspan="6">Ninguna palabra coincide con la búsqueda.</td></tr>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="6">
          Seleccionar:&nbsp;
          <a id="selectAll" href="#ckb">Todos</a>&nbsp;&nbsp;
          <a id="selectNone" href="#ckb">Ninguno</a>&nbsp;&nbsp;
          <a id="selectToggle" href="#ckb">Invertir</a>
        </td>
      </tr>
    </tfoot>
  </table>
</form>

<form action="censuras.php" method="post" class="maits-cens-tarjeta maits-cens-probar" id="probar">
  <?php csrf_token(); ?>
  <input type="hidden" name="do" value="probar">
  <label for="texto" class="maits-cens-etiqueta"><i class="icon-beaker"></i> Probar la censura</label>
  <p class="maits-cens-ayuda">Escribe un texto para ver cómo se vería en un ticket. No se guarda nada.</p>
  <textarea id="texto" name="texto" rows="3" placeholder="Ejemplo: Limpien los baños, mensos"><?php
    echo $h($prueba['original'] ?? ''); ?></textarea>
  <div class="maits-cens-botones">
    <button type="submit" class="maits-btn-guardar"><i class="icon-eye-open"></i> Probar</button>
  </div>
  <?php if ($prueba) { ?>
    <div class="maits-cens-resultado">
      <span>Así se vería:</span>
      <div><?php echo nl2br($h($prueba['resultado'])); ?></div>
      <?php if ($prueba['resultado'] === $prueba['original']) { ?>
        <em>No se encontró ninguna palabra de la lista.</em>
      <?php } ?>
    </div>
  <?php } ?>
</form>
</div>

<div style="display:none;" class="dialog" id="confirm-action">
  <h3>Eliminar censura</h3>
  <a class="close" href=""><i class="icon-remove-circle"></i></a>
  <hr/>
  <p class="confirm-action" style="display:none;" id="delete-confirm">
    <strong style="color:#b42318">¿Seguro que quieres eliminar las palabras marcadas?</strong><br><br>
    Dejarán de censurarse en los tickets nuevos. Si te equivocas, puedes volver a agregarlas.
  </p>
  <hr style="margin-top:1em"/>
  <p class="full-width">
    <span class="buttons pull-left"><input type="button" value="No, cancelar" class="close"></span>
    <span class="buttons pull-right"><input type="button" value="Sí, eliminar" class="confirm maits-btn-rojo"></span>
  </p>
  <div class="clear"></div>
</div>

<script>
$(function() {
  var $filas = $('.maits-cens-tabla tbody tr[data-buscar]');
  $('#maits-cens-buscar').on('input', function() {
    var q = $.trim($(this).val()).toLowerCase(), vis = 0;
    $filas.each(function() {
      var ok = !q || $(this).data('buscar').toString().indexOf(q) !== -1;
      $(this).toggle(ok); if (ok) vis++;
    });
    $('.maits-cens-nada').toggle($filas.length > 0 && vis === 0);
  });
  <?php if ($prueba) { ?>
  var p = document.getElementById('probar'); if (p) p.scrollIntoView({block: 'center'});
  <?php } ?>
});
</script>
