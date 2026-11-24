/*********************************************************************
    [M.A.I.T.S] Reducir fotos antes de subirlas.

    Las fotos de celular pesan 2-8 MB y osTicket solo acepta archivos de
    hasta el "Tamaño máximo" configurado (1 MB por defecto). Antes de
    enviar una imagen grande, el navegador la achica (máximo 1600 px y
    formato JPEG), así queda en unos cientos de KB y se ve igual de bien.

    Funciona en el botón de imagen del editor, al arrastrar o pegar una
    imagen en el editor, y en "Soltar archivos aquí o elegirlos".
    Archivos que no son fotos (PDF, Word...) no se tocan.
**********************************************************************/
(function() {
    'use strict';

    var LIMITE = 700 * 1024;          // a partir de este peso se reduce
    var OBJETIVO = 950 * 1024;        // debe quedar por debajo de esto
    var PASOS = [[1600, 0.82], [1600, 0.7], [1280, 0.7], [1024, 0.65]];
    var TIPOS = /^image\/(jpeg|png|webp|bmp)$/i;   // GIF se deja (puede ser animado)

    function necesitaReducir(f) {
        return f && TIPOS.test(f.type) && f.size > LIMITE;
    }

    function cargar(file) {
        return new Promise(function(ok, mal) {
            var url = URL.createObjectURL(file), img = new Image();
            img.onload = function() { URL.revokeObjectURL(url); ok(img); };
            img.onerror = function() { URL.revokeObjectURL(url); mal(new Error('imagen')); };
            img.src = url;
        });
    }

    function aBlob(img, lado, calidad) {
        var w = img.naturalWidth, h = img.naturalHeight,
            k = Math.min(1, lado / Math.max(w, h)),
            c = document.createElement('canvas');
        c.width = Math.round(w * k); c.height = Math.round(h * k);
        var ctx = c.getContext('2d');
        ctx.fillStyle = '#fff';                 // PNG con transparencia -> fondo blanco
        ctx.fillRect(0, 0, c.width, c.height);
        ctx.drawImage(img, 0, 0, c.width, c.height);
        return new Promise(function(ok) { c.toBlob(ok, 'image/jpeg', calidad); });
    }

    function reducir(file) {
        if (!necesitaReducir(file) || !window.Promise || !window.HTMLCanvasElement)
            return Promise.resolve(file);
        return cargar(file).then(function(img) {
            var i = 0;
            function intentar() {
                return aBlob(img, PASOS[i][0], PASOS[i][1]).then(function(blob) {
                    if (blob && (blob.size <= OBJETIVO || i == PASOS.length - 1)) return blob;
                    i++; return intentar();
                });
            }
            return intentar();
        }).then(function(blob) {
            if (!blob || blob.size >= file.size) return file;
            var nombre = (file.name || 'foto').replace(/\.[^.]+$/, '') + '.jpg';
            try { return new File([blob], nombre, {type: 'image/jpeg', lastModified: Date.now()}); }
            catch (e) { blob.name = nombre; return blob; }
        }).catch(function() { return file; });
    }

    function reducirLista(files) {
        return Promise.all(Array.prototype.map.call(files, reducir)).then(function(lista) {
            var dt = new DataTransfer();
            lista.forEach(function(f) { dt.items.add(f); });
            return dt;
        });
    }

    function hayQueReducir(files) {
        return files && Array.prototype.some.call(files, necesitaReducir);
    }

    if (typeof DataTransfer === 'undefined')
        return;
    try { new DataTransfer(); } catch (e) { return; }

    var propio = typeof WeakSet !== 'undefined' ? new WeakSet() : null;
    var pegarSoportado = false;
    try {
        pegarSoportado = !!new ClipboardEvent('paste', {clipboardData: new DataTransfer()}).clipboardData;
    } catch (e) {}

    // 1) Elegir archivo (botón de imagen del editor y "elegirlos")
    document.addEventListener('change', function(e) {
        var input = e.target;
        if (!input || input.type !== 'file' || (propio && propio.has(e))) return;
        if (!hayQueReducir(input.files)) return;
        e.stopImmediatePropagation();
        reducirLista(input.files).then(function(dt) {
            input.files = dt.files;
            var ev = new Event('change', {bubbles: true});
            if (propio) propio.add(ev);
            input.dispatchEvent(ev);
        });
    }, true);

    // 2) Arrastrar y soltar (en el editor o en "Soltar archivos aquí")
    document.addEventListener('drop', function(e) {
        var files = e.dataTransfer && e.dataTransfer.files;
        if ((propio && propio.has(e)) || !hayQueReducir(files)) return;
        var destino = e.target;
        e.preventDefault(); e.stopImmediatePropagation();
        reducirLista(files).then(function(dt) {
            var ev = new DragEvent('drop', {bubbles: true, cancelable: true, dataTransfer: dt,
                clientX: e.clientX, clientY: e.clientY});
            if (propio) propio.add(ev);
            destino.dispatchEvent(ev);
        });
    }, true);

    // 3) Pegar una imagen en el editor (Ctrl+V)
    document.addEventListener('paste', function(e) {
        var files = e.clipboardData && e.clipboardData.files;
        if (!pegarSoportado || (propio && propio.has(e)) || !hayQueReducir(files)) return;
        var destino = e.target;
        e.preventDefault(); e.stopImmediatePropagation();
        reducirLista(files).then(function(dt) {
            var ev = new ClipboardEvent('paste', {bubbles: true, cancelable: true, clipboardData: dt});
            if (propio) propio.add(ev);
            destino.dispatchEvent(ev);
        });
    }, true);

    window.MaitsImagenes = {reducir: reducir};
})();
