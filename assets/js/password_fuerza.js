// ============================================================
//  password_fuerza.js
//  Medidor visual de fuerza de contraseña. Se engancha a cada
//  <input class="pw-fuerza"> y dibuja una barra debajo.
//  El criterio es el MISMO que el del servidor (seguridad.php).
// ============================================================
(function () {
  function puntuar(p) {
    let s = 0;
    if (p.length >= 8) s++;
    if (p.length >= 12) s++;
    if (/[A-Z]/.test(p) && /[a-z]/.test(p)) s++;
    if (/\d/.test(p) && /[^A-Za-z0-9]/.test(p)) s++;
    return Math.min(4, s);
  }
  const NIVELES = [
    { txt: "Muy débil", col: "#dc2626", w: "20%" },
    { txt: "Débil",     col: "#f59e0b", w: "40%" },
    { txt: "Aceptable", col: "#eab308", w: "60%" },
    { txt: "Buena",     col: "#65a30d", w: "80%" },
    { txt: "Fuerte",    col: "#15803d", w: "100%" },
  ];
  function init() {
    document.querySelectorAll("input.pw-fuerza").forEach(function (inp) {
      const cont = document.createElement("div");
      cont.style.cssText = "margin-top:6px;";
      const track = document.createElement("div");
      track.style.cssText = "height:6px;background:#e2e8f0;border-radius:999px;overflow:hidden;";
      const bar = document.createElement("div");
      bar.style.cssText = "height:100%;width:0;border-radius:999px;transition:width .25s,background .25s;";
      const lbl = document.createElement("small");
      lbl.style.cssText = "display:block;margin-top:4px;color:#64748b;";
      track.appendChild(bar);
      cont.appendChild(track);
      cont.appendChild(lbl);
      inp.insertAdjacentElement("afterend", cont);
      inp.addEventListener("input", function () {
        if (!inp.value) { bar.style.width = "0"; lbl.textContent = ""; return; }
        const n = NIVELES[puntuar(inp.value)];
        bar.style.width = n.w; bar.style.background = n.col;
        lbl.textContent = "Seguridad: " + n.txt;
      });
    });
  }
  if (document.readyState !== "loading") init();
  else document.addEventListener("DOMContentLoaded", init);
})();
