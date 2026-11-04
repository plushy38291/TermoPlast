// ============================================================
//  tema.js — Alterna modo claro/oscuro y recuerda la elección.
//  Sin onclick en línea (compatible con la CSP por nonce).
// ============================================================
(function () {
  var KEY = "termoplast_tema";

  function leer() { try { return localStorage.getItem(KEY); } catch (e) { return null; } }
  function guardar(v) { try { localStorage.setItem(KEY, v); } catch (e) {} }

  function esOscuroEfectivo() {
    var t = document.documentElement.getAttribute("data-tema");
    if (t === "dark") return true;
    if (t === "light") return false;
    return window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
  }

  function aplicar(t) {
    if (t === "dark" || t === "light") document.documentElement.setAttribute("data-tema", t);
    else document.documentElement.removeAttribute("data-tema");
    pintarIconos();
  }

  function pintarIconos() {
    var ico = esOscuroEfectivo() ? "☀️" : "🌙";
    var els = document.querySelectorAll(".tema-toggle");
    for (var i = 0; i < els.length; i++) {
      els[i].textContent = ico;
      els[i].setAttribute("aria-label", esOscuroEfectivo() ? "Cambiar a modo claro" : "Cambiar a modo oscuro");
    }
  }

  // Aplicar la elección guardada cuanto antes (reduce el parpadeo).
  var g = leer();
  if (g) aplicar(g);

  document.addEventListener("DOMContentLoaded", function () {
    pintarIconos();
    var els = document.querySelectorAll(".tema-toggle");
    for (var i = 0; i < els.length; i++) {
      els[i].addEventListener("click", function () {
        var nuevo = esOscuroEfectivo() ? "light" : "dark";
        guardar(nuevo);
        aplicar(nuevo);
      });
    }
  });

  // --- Menú hamburguesa (navegación en móvil) ---
  document.addEventListener("DOMContentLoaded", function () {
    var btn = document.querySelector(".nav-toggle");
    if (!btn) return;
    var bar = btn.closest(".topbar");
    function cerrar() { bar.classList.remove("nav-open"); btn.setAttribute("aria-expanded", "false"); }
    btn.addEventListener("click", function (e) {
      e.stopPropagation();
      var abierto = bar.classList.toggle("nav-open");
      btn.setAttribute("aria-expanded", abierto ? "true" : "false");
    });
    // Cerrar al elegir una sección o al tocar fuera del menú.
    bar.querySelectorAll(".nav-item").forEach(function (a) { a.addEventListener("click", cerrar); });
    document.addEventListener("click", function (e) {
      if (bar.classList.contains("nav-open") && !bar.contains(e.target)) cerrar();
    });
  });


  // --- Confirmaciones de formularios (reemplaza onsubmit en línea, compatible con CSP) ---
  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("form[data-confirm]").forEach(function (f) {
      f.addEventListener("submit", function (e) {
        if (!window.confirm(f.getAttribute("data-confirm"))) e.preventDefault();
      });
    });
  });

  // --- Anti doble-submit: desactiva el botón al enviar y muestra "Enviando…" ---
  // Delegado en document; corre DESPUÉS de la validación y de la confirmación,
  // así respeta un envío cancelado. El disable se DIFIERE (setTimeout 0) para
  // que el name/value del botón todavía viaje en el POST.
  document.addEventListener("submit", function (e) {
    if (e.defaultPrevented) return;                 // validación HTML5 o confirm cancelada
    var form = e.target;
    if (form.hasAttribute("data-ajax")) return;     // formularios AJAX (chat) se manejan solos
    var btn = form.querySelector('button[type="submit"], button:not([type]), input[type="submit"]');
    if (!btn || btn.disabled) return;
    setTimeout(function () {
      btn.disabled = true;
      if (btn.tagName === "BUTTON") { btn.dataset.orig = btn.innerHTML; btn.innerHTML = "Enviando…"; }
      else { btn.value = "Enviando…"; }
    }, 0);
  });

})();
