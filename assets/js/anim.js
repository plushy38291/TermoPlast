// ============================================================
//  anim.js — Micro-animaciones de la interfaz (V4.0).
//  · Contadores animados (count-up) en los números clave.
//  · Aparición al hacer scroll (reveal) para secciones/tarjetas.
//  Respeta prefers-reduced-motion: si está activo, muestra todo
//  en su estado final sin animar. No usa librerías externas.
// ============================================================
(function () {
  // Marca de que el JS está activo: así el CSS solo oculta los .reveal cuando
  // sabemos que podremos mostrarlos (si el JS falla, quedan visibles).
  document.documentElement.classList.add('js');
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // ---- Contadores (count-up) ----
  // Uso: <span class="count" data-to="2.9" data-dec="1" data-suffix=""></span>
  function animarContador(el) {
    var destino = parseFloat(el.getAttribute('data-to'));
    if (isNaN(destino)) return;
    var dec = parseInt(el.getAttribute('data-dec') || '0', 10);
    var suf = el.getAttribute('data-suffix') || '';
    var pre = el.getAttribute('data-prefix') || '';
    var fmt = function (n) {
      return pre + n.toLocaleString('es-AR', { minimumFractionDigits: dec, maximumFractionDigits: dec }) + suf;
    };
    if (reduce || destino === 0) { el.textContent = fmt(destino); return; }
    var dur = 1100, t0 = null;
    function paso(ts) {
      if (!t0) t0 = ts;
      var p = Math.min(1, (ts - t0) / dur);
      var e = 1 - Math.pow(1 - p, 3);           // easeOutCubic
      el.textContent = fmt(destino * e);
      if (p < 1) requestAnimationFrame(paso); else el.textContent = fmt(destino);
    }
    requestAnimationFrame(paso);
  }

  // ---- Reveal al entrar en viewport ----
  function revelar(el) { el.classList.add('is-visible'); }

  function init() {
    var counters = Array.prototype.slice.call(document.querySelectorAll('.count[data-to]'));
    var reveals  = Array.prototype.slice.call(document.querySelectorAll('.reveal'));

    if (!('IntersectionObserver' in window)) {
      counters.forEach(animarContador);
      reveals.forEach(revelar);
      return;
    }
    var io = new IntersectionObserver(function (entries, obs) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        var el = en.target;
        if (el.classList.contains('count')) animarContador(el);
        if (el.classList.contains('reveal')) revelar(el);
        obs.unobserve(el);
      });
    }, { threshold: 0.2, rootMargin: '0px 0px -40px 0px' });

    counters.forEach(function (c) { io.observe(c); });
    reveals.forEach(function (r) { io.observe(r); });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
