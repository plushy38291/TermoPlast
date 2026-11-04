# tools/ — Pruebas automatizadas de TermoPlast

Scripts versionados para verificar el sistema de forma reproducible. Útiles antes
de una entrega o la defensa.

## 1) `smoke_test.php` — prueba de humo (recomendado, sin dependencias)

Solo necesita PHP (ya viene con XAMPP) y el sitio en marcha. Verifica que las
páginas públicas respondan 200, que las protegidas redirijan (302) sin sesión,
que los assets carguen y que **ninguna página tenga errores de PHP**.

```bash
# Con Apache/XAMPP corriendo (ajustá la URL si hace falta):
php tools/smoke_test.php

# Indicando la base explícita:
php tools/smoke_test.php http://localhost/TermoPlast

# Chequeo PROFUNDO (entra con credenciales y revisa las vistas internas):
php tools/smoke_test.php http://localhost/TermoPlast admin:TU_CLAVE gero1@gmail.com:TU_CLAVE
```

Sale con código `0` si todo pasó y `1` si hubo fallos (sirve para integración continua).

## 2) `auditoria_visual.py` — auditoría visual/consola (opcional)

Recorre todas las vistas con un navegador real (Chromium) en claro y oscuro, y
reporta errores de consola/JS, 404 de assets, desborde horizontal y cuántos
gráficos (`<canvas>`) dibujan de verdad. Guarda capturas en `tools/_capturas/`.

```bash
# Una sola vez:
pip install playwright
playwright install chromium

# Correr (con el sitio en marcha):
python tools/auditoria_visual.py http://localhost/TermoPlast admin:TU_CLAVE gero1@gmail.com:TU_CLAVE
```

> Las capturas en `tools/_capturas/` NO se versionan (están en `.gitignore`).
