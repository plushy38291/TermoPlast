// ============================================================
//  qr.js — Autogenera códigos QR en el navegador (requisito 6).
//  Busca todos los elementos con [data-qr] y dibuja adentro un QR
//  que codifica la URL indicada. Usa la librería local qrcode.js
//  (expone el global `qrcode`). La imagen es un data:GIF, permitido
//  por la CSP (img-src 'self' data:). No usa internet.
// ============================================================
(function () {
  if (typeof qrcode === 'undefined') return;
  function render() {
    document.querySelectorAll('[data-qr]').forEach(function (el) {
      if (el.dataset.qrDone) return;
      var url = el.getAttribute('data-qr');
      if (!url) return;
      try {
        var q = qrcode(0, 'M');      // tipo 0 = auto-elige el tamaño según el texto; corrección media
        q.addData(url);
        q.make();
        el.innerHTML = q.createImgTag(4, 8);   // cellSize=4, margen=8
        var img = el.querySelector('img');
        if (img) {
          img.alt = 'Código QR';
          img.style.width = '132px';
          img.style.height = '132px';
          img.style.imageRendering = 'pixelated';
        }
        el.dataset.qrDone = '1';
      } catch (e) {
        el.textContent = 'QR no disponible';
      }
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', render);
  } else {
    render();
  }
})();
