#!/usr/bin/env python3
"""
auditoria_visual.py — Auditoría visual/consola de TermoPlast (OPCIONAL).

Recorre con un navegador real (Playwright/Chromium) las vistas públicas,
de emprendedor y de admin, en modo CLARO y OSCURO, y reporta por cada una:
  · errores de consola / de página (JS),
  · 404 de assets,
  · desborde horizontal (scroll),
  · cuántos <canvas> dibujan realmente (píxeles no transparentes),
y guarda una captura de pantalla completa en tools/_capturas/.

REQUISITOS (una sola vez):
    pip install playwright
    playwright install chromium

USO (con Apache/XAMPP o `php -S` corriendo):
    python tools/auditoria_visual.py                         # http://localhost/TermoPlast
    python tools/auditoria_visual.py http://localhost/TermoPlast admin:CLAVE gero1@gmail.com:CLAVE

Sin credenciales, solo audita las páginas públicas (las protegidas redirigen).
"""
import os, re, sys
try:
    from playwright.sync_api import sync_playwright
except ImportError:
    sys.exit("Falta Playwright. Instalá: pip install playwright && playwright install chromium")

BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://localhost/TermoPlast").rstrip("/")
ADMIN = sys.argv[2] if len(sys.argv) > 2 else ""   # usuario:clave
EMP   = sys.argv[3] if len(sys.argv) > 3 else ""   # email:clave
OUT = os.path.join(os.path.dirname(__file__), "_capturas")
os.makedirs(OUT, exist_ok=True)

PUB = [("index","/index.php"),("login","/auth/login.php"),
       ("registro","/auth/registro_emprendedor.php"),("recuperar","/auth/recuperar.php")]
EMP_PAGES = [("panel","/pages/panel.php"),("impacto","/pages/impacto.php"),
       ("seguimiento","/pages/seguimiento.php"),("hacer_pedido","/pages/hacer_pedido.php"),
       ("subir_diseno","/pages/subir_diseno.php"),("mis_disenos","/pages/mis_disenos.php"),
       ("perfil","/pages/perfil.php"),("mapa","/pages/mapa.php")]
ADMIN_PAGES = [("dashboard","/admin/dashboard.php"),("admin_panel","/admin/admin_panel.php"),
       ("seguridad","/admin/admin_seguridad.php"),("abm_materiales","/admin/abm.php?e=materiales"),
       ("abm_emprendedores","/admin/abm.php?e=emprendedores")]

def csrf(html):
    m = re.search(r'name="csrf" value="([^"]+)"', html); return m.group(1) if m else ""

report, fails = [], 0
def visit(ctx, name, path, mode):
    global fails
    pg = ctx.new_page(); errs = []; a404 = []
    pg.on("pageerror", lambda e: errs.append("PAGEERR:" + str(e)))
    pg.on("console", lambda m: errs.append("console."+m.type+":"+m.text) if m.type == "error" else None)
    pg.on("response", lambda r: a404.append(r.url) if r.status == 404 else None)
    try:
        pg.goto(BASE + path, wait_until="networkidle", timeout=20000)
    except Exception as e:
        report.append(f"[{mode}] {name}: NAV-ERROR {e}"); pg.close(); return
    if mode == "dark":
        pg.evaluate("document.documentElement.setAttribute('data-tema','dark');document.body&&document.body.setAttribute('data-tema','dark')")
    pg.wait_for_timeout(1200)
    cv = pg.evaluate("""()=>{var t=0,d=0;document.querySelectorAll('canvas').forEach(function(c){t++;try{var im=c.getContext('2d').getImageData(0,0,c.width,c.height).data;for(var i=3;i<im.length;i+=4){if(im[i]!==0){d++;break;}}}catch(e){}});return t+'/'+d;}""")
    ov = pg.evaluate("()=>{var e=document.documentElement;return e.scrollWidth>e.clientWidth+2?'HSCROLL':''}")
    pg.screenshot(path=f"{OUT}/{name}_{mode}.png", full_page=True)
    jserr = [e for e in errs if "PAGEERR" in e or "console.error" in e]
    flags = []
    if jserr: flags.append("JS-ERROR:" + str(jserr[:1]))
    if ov: flags.append(ov)
    if a404: flags.append("404:" + str([u.split("/")[-1] for u in a404[:3]]))
    status = "OK" if not flags else " ".join(flags)
    if flags: fails += 1
    report.append(f"[{mode}] {name:20s} canvas={cv:6s} {status}")
    pg.close()

with sync_playwright() as pw:
    br = pw.chromium.launch()
    for mode in ("light", "dark"):
        if ADMIN:
            u, c = (ADMIN.split(":", 1) + [""])[:2]
            actx = br.new_context(viewport={"width": 1366, "height": 1000})
            p = actx.new_page(); p.goto(BASE + "/admin/admin_login.php", wait_until="networkidle")
            t = csrf(p.content())
            p.evaluate("([t,u,c])=>{var f=document.querySelector('form');f.csrf.value=t;f.usuario.value=u;f.password.value=c}", [t, u, c])
            p.click('button[type=submit]'); p.wait_for_load_state("networkidle"); p.close()
            for n, pa in ADMIN_PAGES: visit(actx, n, pa, mode)
            actx.close()
        ectx = br.new_context(viewport={"width": 1366, "height": 1000})
        if EMP:
            e, c = (EMP.split(":", 1) + [""])[:2]
            p = ectx.new_page(); p.goto(BASE + "/auth/login.php", wait_until="networkidle")
            t = csrf(p.content())
            p.evaluate("([t,e,c])=>{var f=document.querySelector('form');f.csrf.value=t;f.email.value=e;f.password.value=c}", [t, e, c])
            p.click('button[type=submit]'); p.wait_for_load_state("networkidle"); p.close()
            for n, pa in EMP_PAGES: visit(ectx, n, pa, mode)
        for n, pa in PUB: visit(ectx, n, pa, mode)
        ectx.close()
    br.close()

print("\n".join(report))
print(f"\n== {len(report)} vistas revisadas · {fails} con hallazgos · capturas en tools/_capturas/ ==")
sys.exit(1 if fails else 0)
