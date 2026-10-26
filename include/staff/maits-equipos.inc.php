<?php
/*********************************************************************
    maits-equipos.inc.php   [M.A.I.T.S]
    Vista de la pestaña "Equipos" (ver scp/equipos.php)
**********************************************************************/
if (!defined('OSTSCPINC') || !$thisstaff) die('Access Denied');

$h = function ($t) { return Format::htmlchars($t); };
$nombre = function ($s) { return $s ? Format::htmlchars($s->getName()) : ''; };

// ===================================================================
//  Formulario: crear / editar equipo
// ===================================================================
if (in_array($accion, array('nuevo', 'editar'))) {
    $v = $_POST ?: array();
    if (!$_POST && $equipo) {
        $v = array(
            'nombre'   => $equipo->getName(),
            'lider'    => $equipo->getLeadId(),
            'activo'   => $equipo->isEnabled() ? 1 : 0,
            'notas'    => $equipo->notes,
            'miembros' => array_map(function ($s) { return $s->getId(); }, $equipo->getMembers()),
        );
    } elseif (!$_POST) {
        $v = array('activo' => 1, 'miembros' => array());
    }
    $sel = array_map('intval', (array) ($v['miembros'] ?? array()));
    $agentes = array();
    foreach (MaitsEquipos::agentesActivos() as $s)
        $agentes[$s->dept ? $s->dept->getName() : 'Sin departamento'][] = $s;
?>
<div class="maits-pagina">
  <h2><?php echo $equipo ? 'Editar equipo: '.$h($equipo->getName()) : 'Crear un equipo'; ?></h2>
  <p class="maits-intro">Un equipo es un grupo de agentes que resuelve un tipo de problema
     (por ejemplo "Electricistas" o "Redes"). Los tickets se pueden asignar a un equipo y le
     llegan a todos sus miembros; el <strong>líder</strong> es quien está a cargo.</p>

  <form method="post" action="equipos.php" class="maits-form">
    <?php csrf_token(); ?>
    <input type="hidden" name="a" value="guardar">
    <?php if ($equipo) { ?><input type="hidden" name="id" value="<?php echo $equipo->getId(); ?>"><?php } ?>

    <div class="maits-campo">
      <label for="nombre">1. Nombre del equipo <span class="error">*</span></label>
      <input type="text" id="nombre" name="nombre" maxlength="125" value="<?php echo $h($v['nombre'] ?? ''); ?>"
        placeholder="Ej. Electricistas, Redes, Limpieza" autofocus>
      <?php if (!empty($errors['nombre'])) echo '<div class="maits-error">'.$h($errors['nombre']).'</div>'; ?>
    </div>

    <div class="maits-campo">
      <label for="lider">2. Líder (encargado del equipo)</label>
      <select id="lider" name="lider">
        <option value="0">— Sin líder —</option>
        <?php foreach ($agentes as $dept => $lista) { ?>
        <optgroup label="<?php echo $h($dept); ?>">
          <?php foreach ($lista as $s) {
            printf('<option value="%d" %s>%s</option>', $s->getId(),
                ((int) ($v['lider'] ?? 0) == $s->getId()) ? 'selected' : '', $nombre($s));
          } ?>
        </optgroup>
        <?php } ?>
      </select>
      <div class="maits-nota">El líder se agrega solo como miembro del equipo.</div>
    </div>

    <div class="maits-campo">
      <label>3. Miembros <span class="error">*</span></label>
      <input type="search" class="maits-filtro" placeholder="Buscar agente por nombre…"
        oninput="var t=this.value.toLowerCase();$(this).closest('.maits-campo').find('.maits-agente').each(function(){$(this).toggle($(this).text().toLowerCase().indexOf(t)>=0);});">
      <?php if (!empty($errors['miembros'])) echo '<div class="maits-error">'.$h($errors['miembros']).'</div>'; ?>
      <div class="maits-agentes">
        <?php foreach ($agentes as $dept => $lista) { ?>
        <fieldset>
          <legend><?php echo $h($dept); ?></legend>
          <?php foreach ($lista as $s) { ?>
          <label class="maits-agente">
            <input type="checkbox" name="miembros[]" value="<?php echo $s->getId(); ?>"
              <?php if (in_array($s->getId(), $sel)) echo 'checked'; ?>>
            <?php echo $nombre($s); ?>
          </label>
          <?php } ?>
        </fieldset>
        <?php } ?>
      </div>
    </div>

    <div class="maits-campo">
      <label class="maits-check"><input type="checkbox" name="activo" value="1"
        <?php if (!empty($v['activo'])) echo 'checked'; ?>> Equipo activo
        <span class="maits-nota">(si lo desactivas ya no se le pueden asignar tickets)</span></label>
    </div>

    <div class="maits-campo">
      <label for="notas">Notas (opcional)</label>
      <textarea id="notas" name="notas" rows="3" placeholder="¿Qué tipo de problemas atiende este equipo?"><?php
        echo $h(Format::html2text($v['notas'] ?? '')); ?></textarea>
    </div>

    <div class="maits-acciones">
      <button type="submit" class="maits-btn principal"><i class="icon-save"></i>
        <?php echo $equipo ? 'Guardar cambios' : 'Crear equipo'; ?></button>
      <a class="maits-btn" href="equipos.php?a=gestionar">Cancelar</a>
    </div>
  </form>
</div>
<?php
    return;
}

// ===================================================================
//  Gestionar: lista de todos los equipos
// ===================================================================
if ($accion == 'gestionar') {
    $equipos = Team::objects()->order_by('name');
?>
<div class="maits-pagina">
  <div class="maits-titulo-fila">
    <h2>Gestionar equipos</h2>
    <a class="maits-btn principal" href="equipos.php?a=nuevo"><i class="icon-plus"></i> Crear equipo</a>
  </div>
  <p class="maits-intro">Aquí puedes crear equipos y cambiar su líder o sus miembros.
     Para borrar un equipo pídeselo a un administrador.</p>
  <table class="list maits-tabla" width="100%">
    <thead><tr><th>Equipo</th><th>Líder</th><th>Miembros</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($equipos as $t) {
        $miembros = $t->getMembers(); ?>
      <tr>
        <td><strong><?php echo $h($t->getName()); ?></strong></td>
        <td><?php echo $t->getLead() ? $nombre($t->getLead()) : '<span class="faded">Sin líder</span>'; ?></td>
        <td><?php echo count($miembros); ?>
          <span class="faded"><?php echo $h(implode(', ', array_map(function ($s) { return $s->getName(); }, array_slice($miembros, 0, 4))))
            . (count($miembros) > 4 ? '…' : ''); ?></span></td>
        <td><?php echo $t->isEnabled() ? '<span class="maits-pill ok">Activo</span>' : '<span class="maits-pill">Inactivo</span>'; ?></td>
        <td class="maits-derecha"><a class="maits-btn chico" href="equipos.php?a=editar&amp;id=<?php echo $t->getId(); ?>"><i class="icon-edit"></i> Editar</a></td>
      </tr>
    <?php }
    if (!$equipos->count()) { ?>
      <tr><td colspan="5" class="maits-vacio">Todavía no hay equipos. Presiona <strong>Crear equipo</strong> para hacer el primero.</td></tr>
    <?php } ?>
    </tbody>
  </table>
</div>
<?php
    return;
}

// ===================================================================
//  Mi área: departamentos y equipos del agente
// ===================================================================
$deptos = MaitsEquipos::departamentosDe($thisstaff);
$equipos = MaitsEquipos::equiposDe($thisstaff);
?>
<div class="maits-pagina">
  <div class="maits-titulo-fila">
    <h2>Mi área</h2>
    <?php if ($puede) { ?>
    <a class="maits-btn" href="equipos.php?a=gestionar"><i class="icon-cog"></i> Gestionar equipos</a>
    <?php } ?>
  </div>

  <h3 class="maits-seccion"><i class="icon-building"></i> Mis departamentos</h3>
  <p class="maits-intro">Un departamento es el área que recibe los tickets (según el tema que elige el usuario).
     El <strong>gerente</strong> es quien recibe el aviso de cada ticket nuevo y lo reparte.</p>
  <div class="maits-tarjetas">
  <?php foreach ($deptos as $d) {
      $dept = $d['dept'];
      $gerente = $dept->getManager();
      $miembros = MaitsEquipos::miembrosDe($dept); ?>
    <section class="maits-tarjeta">
      <header>
        <strong><?php echo $h($dept->getName()); ?></strong>
        <span class="maits-pill"><?php echo $h($d['tipo']); ?></span>
      </header>
      <dl>
        <dt>Tu rol aquí</dt><dd><?php echo $h($d['rol'] ?: '—'); ?></dd>
        <dt>Gerente</dt><dd><?php echo $gerente ? $nombre($gerente) : '<span class="faded">Sin gerente asignado</span>'; ?></dd>
      </dl>
      <div class="maits-sub">Compañeros (<?php echo count($miembros); ?>)</div>
      <ul class="maits-personas">
        <?php foreach ($miembros as $m) {
            list($s, $tipo, $rol) = $m; ?>
        <li><span><?php echo $nombre($s); ?><?php if ($s->getId() == $thisstaff->getId()) echo ' <em>(tú)</em>'; ?></span>
            <small><?php echo $h($rol); ?><?php if ($tipo != 'Principal') echo ' · acceso extendido'; ?></small></li>
        <?php } ?>
      </ul>
    </section>
  <?php }
  if (!$deptos) { ?>
    <p class="maits-vacio">No perteneces a ningún departamento. Pídele a un administrador que te agregue.</p>
  <?php } ?>
  </div>

  <h3 class="maits-seccion"><i class="icon-group"></i> Mis equipos</h3>
  <p class="maits-intro">Un equipo es un grupo de agentes que resuelve cierto tipo de problemas.
     Si un ticket se asigna a tu equipo, lo verás en <strong>Tickets → Mis Solicitudes</strong>.</p>
  <div class="maits-tarjetas">
  <?php $n = 0;
  foreach ($equipos as $t) { $n++;
      $lider = $t->getLead(); ?>
    <section class="maits-tarjeta">
      <header>
        <strong><?php echo $h($t->getName()); ?></strong>
        <?php echo $t->isEnabled() ? '<span class="maits-pill ok">Activo</span>' : '<span class="maits-pill">Inactivo</span>'; ?>
      </header>
      <dl>
        <dt>Líder</dt><dd><?php echo $lider ? $nombre($lider) . ($lider->getId() == $thisstaff->getId() ? ' <em>(tú)</em>' : '') : '<span class="faded">Sin líder</span>'; ?></dd>
      </dl>
      <div class="maits-sub">Miembros (<?php echo count($t->getMembers()); ?>)</div>
      <ul class="maits-personas">
        <?php foreach ($t->getMembers() as $s) { ?>
        <li><span><?php echo $nombre($s); ?><?php if ($s->getId() == $thisstaff->getId()) echo ' <em>(tú)</em>'; ?></span>
            <small><?php echo $s->getDept() ? $h($s->getDept()->getName()) : ''; ?></small></li>
        <?php } ?>
      </ul>
    </section>
  <?php }
  if (!$n) { ?>
    <p class="maits-vacio">Todavía no estás en ningún equipo.</p>
  <?php } ?>
  </div>
</div>
