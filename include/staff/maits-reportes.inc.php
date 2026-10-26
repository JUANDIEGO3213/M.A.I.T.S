<?php
/*********************************************************************
    maits-reportes.inc.php   [M.A.I.T.S]
    Formulario "Crear reporte" (ver scp/reportes.php)
**********************************************************************/
if (!defined('OSTSCPINC') || !$thisstaff) die('Access Denied');

$h = function ($t) { return Format::htmlchars($t); };
$p = $_POST ?: array();
$enviado = !empty($p);
$columnas = MaitsReporte::columnasDisponibles();

// Valores iniciales: desde una cola de tickets se puede preseleccionar el grupo
$inicio = $_GET['grupo'] ?? '';
$marcado = function ($g) use ($p, $enviado, $inicio) {
    if ($enviado) return !empty($p['incluir'][$g]);
    if ($inicio && isset(MaitsReporte::$grupos[$inicio])) return $g == $inicio;
    return true;
};
$periodo = function ($g) use ($p) { return $p['periodo'][$g] ?? 'todos'; };
$fecha = function ($campo, $g) use ($p) { return $p[$campo][$g] ?? ''; };
$colMarcada = function ($c, $info) use ($p, $enviado) {
    return $enviado ? in_array($c, (array) ($p['columnas'] ?? array())) : (bool) $info[2];
};
$err = function ($g) use ($errors) {
    return !empty($errors['periodo'][$g]) ? '<div class="maits-error">'.Format::htmlchars($errors['periodo'][$g]).'</div>' : '';
};
$hoy = date('Y-m-d');
$inicioMes = date('Y-m-01');
?>
<div class="maits-pagina maits-reporte">
  <h2>Crear reporte de tickets</h2>
  <p class="maits-intro">Elige qué tickets incluir, qué datos mostrar y el tipo de archivo.
    El reporte solo incluye los tickets que tú puedes ver.</p>

  <form method="post" action="reportes.php" id="maits-form-reporte">
    <?php csrf_token(); ?>
    <input type="hidden" name="a" value="generar">

    <!-- ============ 1. Qué tickets ============ -->
    <section class="maits-paso">
      <h3><span class="maits-num">1</span> ¿Qué tickets incluir? <span class="maits-etiqueta rec">Recomendado</span></h3>
      <?php if (!empty($errors['grupos'])) echo '<div class="maits-error">'.$h($errors['grupos']).'</div>'; ?>
      <div class="maits-grupos">
      <?php foreach (MaitsReporte::$grupos as $g => $info) {
          list($titulo, $desc, $cual) = $info; ?>
        <div class="maits-grupo <?php echo $marcado($g) ? 'activo' : ''; ?>" data-grupo="<?php echo $g; ?>">
          <label class="maits-grupo-titulo">
            <input type="checkbox" name="incluir[<?php echo $g; ?>]" value="1" <?php if ($marcado($g)) echo 'checked'; ?>>
            <strong><?php echo $titulo; ?></strong>
          </label>
          <p class="maits-nota"><?php echo $desc; ?></p>
          <div class="maits-periodo">
            <span class="maits-sub"><?php echo $cual; ?>:</span>
            <label><input type="radio" name="periodo[<?php echo $g; ?>]" value="todos" <?php if ($periodo($g) == 'todos') echo 'checked'; ?>> Todos hasta hoy</label>
            <label><input type="radio" name="periodo[<?php echo $g; ?>]" value="rango" <?php if ($periodo($g) == 'rango') echo 'checked'; ?>> Entre fechas</label>
            <?php if ($g != 'creados') { ?>
            <label><input type="radio" name="periodo[<?php echo $g; ?>]" value="igual" <?php if ($periodo($g) == 'igual') echo 'checked'; ?>> Mismo periodo que «Creados»</label>
            <?php } ?>
            <div class="maits-rango">
              <label>Del <input type="date" name="desde[<?php echo $g; ?>]" value="<?php echo $h($fecha('desde', $g) ?: $inicioMes); ?>" max="<?php echo $hoy; ?>"></label>
              <label>al <input type="date" name="hasta[<?php echo $g; ?>]" value="<?php echo $h($fecha('hasta', $g) ?: $hoy); ?>"></label>
            </div>
            <?php echo $err($g); ?>
          </div>
        </div>
      <?php } ?>
      </div>
      <label class="maits-check maits-fecha-rep">
        <input type="checkbox" name="fecha_reporte" value="1" <?php if (!$enviado || !empty($p['fecha_reporte'])) echo 'checked'; ?>>
        <strong>Mostrar la fecha del reporte</strong> (día/mes/año en que se creó: <?php echo date('d/m/Y'); ?>)
      </label>
    </section>

    <!-- ============ 2. Qué datos ============ -->
    <section class="maits-paso">
      <h3><span class="maits-num">2</span> ¿Qué datos mostrar de cada ticket?</h3>
      <?php if (!empty($errors['columnas'])) echo '<div class="maits-error">'.$h($errors['columnas']).'</div>'; ?>
      <p class="maits-nota">Cada dato es una columna del reporte. Pasa el mouse sobre un nombre para ver qué significa.</p>

      <div class="maits-cols-titulo"><span class="maits-etiqueta rec">Recomendadas</span> lo más útil para un reporte
        <span class="maits-links"><a href="#" data-marcar="rec" data-valor="1">Marcar todas</a> · <a href="#" data-marcar="rec" data-valor="0">Quitar todas</a></span></div>
      <div class="maits-cols">
      <?php foreach ($columnas as $c => $info) { if (!$info[2]) continue; ?>
        <label class="maits-col" data-tipo="rec" title="<?php echo $h($info[1]); ?>">
          <input type="checkbox" name="columnas[]" value="<?php echo $h($c); ?>" <?php if ($colMarcada($c, $info)) echo 'checked'; ?>>
          <span><strong><?php echo $h($info[0]); ?></strong><small><?php echo $h($info[1]); ?></small></span>
        </label>
      <?php } ?>
      </div>

      <details class="maits-opcionales" <?php if ($enviado && array_diff((array) ($p['columnas'] ?? array()), array_keys(array_filter($columnas, function ($i) { return $i[2]; })))) echo 'open'; ?>>
        <summary><span class="maits-etiqueta opc">Opcionales</span> agrégalas solo si las necesitas
          (<?php echo count(array_filter($columnas, function ($i) { return !$i[2]; })); ?> más)</summary>
        <div class="maits-cols">
        <?php foreach ($columnas as $c => $info) { if ($info[2]) continue; ?>
          <label class="maits-col" data-tipo="opc" title="<?php echo $h($info[1]); ?>">
            <input type="checkbox" name="columnas[]" value="<?php echo $h($c); ?>" <?php if ($colMarcada($c, $info)) echo 'checked'; ?>>
            <span><strong><?php echo $h($info[0]); ?></strong><small><?php echo $h($info[1]); ?></small></span>
          </label>
        <?php } ?>
        </div>
      </details>
    </section>

    <!-- ============ 3. Tipo de archivo ============ -->
    <section class="maits-paso">
      <h3><span class="maits-num">3</span> Tipo de archivo</h3>
      <?php $fmt = $p['formato'] ?? 'xlsx'; ?>
      <div class="maits-formatos">
        <label class="maits-formato">
          <input type="radio" name="formato" value="xlsx" <?php if ($fmt == 'xlsx') echo 'checked'; ?>>
          <span><i class="icon-table"></i><strong>Excel</strong> <em class="maits-etiqueta rec">Recomendado</em>
            <small>Con logos, encabezados de color, filtros y una hoja por grupo. Se puede editar.</small></span>
        </label>
        <label class="maits-formato">
          <input type="radio" name="formato" value="pdf" <?php if ($fmt == 'pdf') echo 'checked'; ?>>
          <span><i class="icon-file-text"></i><strong>PDF</strong>
            <small>Listo para imprimir o enviar. Con muchas columnas la letra sale más chica.</small></span>
        </label>
        <label class="maits-formato">
          <input type="radio" name="formato" value="csv" <?php if ($fmt == 'csv') echo 'checked'; ?>>
          <span><i class="icon-list"></i><strong>CSV</strong>
            <small>Solo los datos, sin logos ni colores. Para otros programas.</small></span>
        </label>
      </div>
    </section>

    <div class="maits-acciones">
      <button type="submit" class="maits-btn principal grande"><i class="icon-download-alt"></i> Crear y descargar reporte</button>
      <span class="maits-nota">El archivo se descarga solo; puede tardar unos segundos si son muchos tickets.</span>
    </div>
  </form>
</div>
<script type="text/javascript">
$(function () {
  var $f = $('#maits-form-reporte');
  function actualizar() {
    $f.find('.maits-grupo').each(function () {
      var $g = $(this), on = $g.find('.maits-grupo-titulo input').is(':checked');
      $g.toggleClass('activo', on);
      $g.find('.maits-periodo input').prop('disabled', !on);
      var p = $g.find('input[type=radio]:checked').val();
      $g.find('.maits-rango').toggle(on && p === 'rango');
    });
  }
  $f.on('change', 'input', actualizar);
  $f.on('click', '[data-marcar]', function (e) {
    e.preventDefault();
    $f.find('.maits-col[data-tipo=' + $(this).data('marcar') + '] input').prop('checked', $(this).data('valor') == 1);
  });
  // osTicket avisa "cambios sin guardar" al salir; aqui no aplica
  $f.on('submit', function () { $(window).off('beforeunload'); $f.data('changed', false); });
  actualizar();
});
</script>
