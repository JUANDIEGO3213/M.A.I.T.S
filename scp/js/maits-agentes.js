/* =====================================================================
   M.A.I.T.S - Ayudas del panel de agentes
   1. Pone texto a los botones que solo tenian icono (Asignar, Estado...).
   2. Recuerda si el agente oculto la ayuda de cada seccion.
   Funciona tambien cuando osTicket cambia de pagina sin recargar (PJAX).
   ===================================================================== */
(function ($) {
  'use strict';

  // icono -> texto que se muestra junto al icono
  var ETIQUETAS = {
    'icon-flag': 'Estado',
    'icon-user': 'Asignar',
    'icon-share': 'Transferir',
    'icon-code-fork': 'Combinar',
    'icon-link': 'Vincular',
    'icon-trash': 'Eliminar',
    'icon-print': 'Imprimir',
    'icon-edit': 'Editar',
    'icon-cog': 'Más opciones',
    'icon-mail-reply': 'Responder',
    'icon-file-text': 'Nota interna',
    'icon-ok-circle': 'Cerrar',
    'icon-undo': 'Reabrir'
  };

  function guardar(clave, valor) {
    try { window.localStorage.setItem(clave, valor); } catch (e) { /* sin almacenamiento */ }
  }
  function leer(clave) {
    try { return window.localStorage.getItem(clave); } catch (e) { return null; }
  }

  function etiquetarBotones(ctx) {
    $('.sticky.bar .action-button, #response_options .action-button', ctx).each(function () {
      var $b = $(this);
      if ($b.data('maits-etiqueta') || $b.find('.maits-etq').length) return;
      // Solo botones que no tienen texto (los que solo muestran un icono)
      if ($.trim($b.text()) !== '') return;
      var $icono = $b.find('i').not('.icon-caret-down').first();
      if (!$icono.length) return;
      var clases = ($icono.attr('class') || '').split(/\s+/);
      var texto = null;
      for (var i = 0; i < clases.length; i++) {
        if (ETIQUETAS[clases[i]]) { texto = ETIQUETAS[clases[i]]; break; }
      }
      if (!texto) return;
      $icono.after('<span class="maits-etq">' + texto + '</span>');
      $b.addClass('maits-con-texto').data('maits-etiqueta', true);
      // Ya tiene texto visible: quitar la burbuja (tooltip) que tapaba el menu
      $b.removeAttr('data-toggle');
      if ($.fn.tooltip) { try { $b.tooltip('destroy'); } catch (e) { /* sin tooltip */ } }
      $b.off('mouseenter.tooltip mouseleave.tooltip focus.tooltip blur.tooltip');
    });
  }

  function prepararAyuda(ctx) {
    $('.maits-ayuda', ctx).each(function () {
      var $a = $(this), clave = 'maits-ayuda-' + $a.data('ayuda');
      $a.toggleClass('oculta', leer(clave) === '1');
    });
  }

  function quitarBurbujas() {
    if (!$.fn.tooltip) return;
    $('.maits-con-texto').each(function () {
      try { $(this).tooltip('destroy'); } catch (e) { /* sin tooltip */ }
    });
  }

  function iniciar(ctx) {
    etiquetarBotones(ctx || document);
    prepararAyuda(ctx || document);
    // osTicket crea sus burbujas despues; quitarlas de los botones que ya tienen texto
    setTimeout(quitarBurbujas, 100);
  }

  $(document).on('click', '.maits-ayuda-cerrar', function () {
    var $a = $(this).closest('.maits-ayuda');
    $a.addClass('oculta');
    guardar('maits-ayuda-' + $a.data('ayuda'), '1');
  });
  $(document).on('click', '.maits-ayuda-abrir', function () {
    var $a = $(this).closest('.maits-ayuda');
    $a.removeClass('oculta');
    guardar('maits-ayuda-' + $a.data('ayuda'), '0');
  });

  $(function () { iniciar(document); });
  $(document).on('pjax:end', function () { iniciar(document); });
  // Algunas barras se dibujan despues de cargar (ajax); revisar un poco despues
  $(document).on('ajaxComplete', function () { etiquetarBotones(document); });
})(jQuery);
