// ============================================================
//  chatbot.js — Lógica del widget de chat del asistente.
//  Envía el mensaje al puente PHP y muestra la respuesta.
// ============================================================
(function () {
  document.addEventListener("DOMContentLoaded", function () {
    var toggle = document.getElementById("cb-toggle");
    var panel  = document.getElementById("cb-panel");
    var cerrar = document.getElementById("cb-cerrar");
    var form   = document.getElementById("cb-form");
    var input  = document.getElementById("cb-input");
    var body   = document.getElementById("cb-body");
    var csrf   = document.getElementById("cb-csrf");
    if (!toggle || !panel || !form) return;

    function abrir(v) {
      panel.classList.toggle("cb-open", v);
      toggle.setAttribute("aria-expanded", v ? "true" : "false");
      toggle.setAttribute("aria-label", v ? "Cerrar asistente" : "Abrir asistente");
      toggle.textContent = v ? "✕" : "💬";     // el botón flotante refleja el estado
      if (v) input.focus();
    }

    // SIEMPRE arranca CERRADO: el chat no ocupa espacio hasta que vos lo abrís
    // con la nubecita. Se cierra con la ✕ (o Escape) y vuelve a la nubecita.
    abrir(false);

    toggle.addEventListener("click", function () { abrir(!panel.classList.contains("cb-open")); });
    cerrar.addEventListener("click", function () { abrir(false); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape" && panel.classList.contains("cb-open")) abrir(false); });

    function burbuja(texto, quien, comoHTML) {
      var d = document.createElement("div");
      d.className = "cb-msg " + quien;
      if (comoHTML) d.innerHTML = texto; else d.textContent = texto;
      body.appendChild(d);
      body.scrollTop = body.scrollHeight;
      return d;
    }

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var msg = input.value.trim();
      if (!msg) return;
      burbuja(msg, "user", false);        // el texto del usuario NUNCA como HTML
      input.value = "";
      // Indicador "escribiendo…" (tres puntitos animados) mientras el asistente
      // responde. Útil sobre todo cuando genera con un LLM (tarda unos segundos).
      var esperando = burbuja('<span class="cb-typing" aria-label="escribiendo"><span></span><span></span><span></span></span>', "bot", true);

      var datos = new URLSearchParams();
      datos.append("mensaje", msg);
      datos.append("csrf", csrf ? csrf.value : "");

      var endpoint = form.getAttribute("data-endpoint") || "chatbot_responder.php";
      fetch(endpoint, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: datos.toString(),
      })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          esperando.innerHTML = d.respuesta || "…";   // la respuesta del server puede traer <strong>
          // Acciones sugeridas: el bot no solo responde, te lleva a la acción
          // (navegar o pre-llenar un pedido). label y url los arma el PHP
          // (listas fijas), por eso usamos textContent/href y no innerHTML.
          var acciones = Array.isArray(d.acciones) && d.acciones.length
            ? d.acciones
            : (d.accion ? [d.accion] : []);
          if (acciones.length) {
            var cont = document.createElement("div");
            cont.className = "cb-acciones";
            acciones.forEach(function (ac) {
              if (!ac || !ac.url || !ac.label) return;
              var a = document.createElement("a");
              a.className = "cb-accion";
              a.setAttribute("href", ac.url);
              a.textContent = ac.label + " →";
              cont.appendChild(a);
            });
            if (cont.childNodes.length) esperando.appendChild(cont);
          }
          body.scrollTop = body.scrollHeight;
        })
        .catch(function () {
          esperando.textContent = "No pude conectarme. Reintentá en un momento.";
        });
    });
  });
})();
