# TermoPlast — Manual de Despliegue y Endurecimiento

Guía para dejar TermoPlast **listo para usar** en una PC con XAMPP: puesta en
marcha, que los `.htaccess` tomen efecto (**AllowOverride All**), **HTTPS local**,
servir en **red LAN** (incluso **sin Wi-Fi**, por cable o hotspot) y un
**checklist de seguridad** para la defensa/producción.

> Autor: Mateo Porcel · Tesis 7° año E.E.S.T. N°3 "Roberto Arlt".

---

## 1) Puesta en marcha (XAMPP)

1. Copiá la carpeta `TermoPlast` dentro de `C:\xampp\htdocs\`.
2. Abrí **XAMPP Control Panel** → **Start** en **Apache** y **MySQL**.
3. Creá la base e importá el esquema (phpMyAdmin → *Importar*, o por consola):
   - Base: `termoplast` (utf8mb4).
   - Scripts en orden: `sql/1_*.sql` … `sql/6_*.sql`.
   - Opcional (datos de demostración para que los gráficos muestren tendencias):
     `sql/7_datos_demo.sql`.
4. Primer administrador: abrí una vez `http://localhost/TermoPlast/admin/crear_admin.php`,
   **anotá la contraseña** que muestra (se ve una sola vez) y **borrá o renombrá**
   ese archivo. (Si ya existe un admin, el script queda inerte y no toca nada.)
5. Chatbot (IA): seguí `chatbot/INSTALAR_WINDOWS.txt` para crear el venv e iniciar
   el servicio Python en `127.0.0.1:5001`. Si no está levantado, el chat avisa con
   elegancia y el resto del sitio funciona igual.

---

## 2) AllowOverride All — que los `.htaccess` funcionen

La app trae `.htaccess` que **protegen archivos sensibles** (`config/`, `includes/`,
`.sql`, `.txt`, `.log`, ocultos). En XAMPP, para que Apache **los lea**, el
directorio debe tener `AllowOverride All` (por defecto a veces está en `None`).

1. Abrí `C:\xampp\apache\conf\httpd.conf`.
2. Buscá el bloque del `DocumentRoot` (`<Directory "C:/xampp/htdocs">`).
3. Cambiá `AllowOverride None` por `AllowOverride All`:
   ```apache
   <Directory "C:/xampp/htdocs">
       Options -Indexes +FollowSymLinks
       AllowOverride All
       Require all granted
   </Directory>
   ```
4. Guardá y **reiniciá Apache** desde el panel de XAMPP.

**Verificación rápida:** entrá a `http://localhost/TermoPlast/config/conexion.php`
en el navegador. Debe dar **403 Forbidden** (no mostrar código). Lo mismo con
`http://localhost/TermoPlast/credenciales.txt`. Si muestra el contenido,
`AllowOverride All` no está activo.

---

## 3) HTTPS local (certificado autofirmado)

Útil para probar la app sobre `https://` en la misma PC (y que el navegador no
marque "no seguro" en formularios de login).

**Opción A — rápida (XAMPP ya trae SSL):**
1. En `httpd.conf` verificá que esté activo el módulo SSL y el include:
   ```apache
   LoadModule ssl_module modules/mod_ssl.so
   Include conf/extra/httpd-ssl.conf
   ```
2. Reiniciá Apache y entrá a `https://localhost/TermoPlast/`.
   El navegador avisará que el certificado es autofirmado → *Avanzado → Continuar*.

**Opción B — certificado confiable con `mkcert` (sin avisos):**
1. Instalá [`mkcert`](https://github.com/FiloSottile/mkcert) y corré `mkcert -install`.
2. Generá el certificado para tu host/IP:
   `mkcert localhost 127.0.0.1 192.168.1.66`
3. Copiá los `.pem` generados y apuntá a ellos en `httpd-ssl.conf`
   (`SSLCertificateFile` / `SSLCertificateKeyFile`). Reiniciá Apache.

> Si servís por HTTPS, el sistema **activa solo** la cabecera **HSTS** y marca la
> cookie de sesión como **Secure** (ya está contemplado en el código).

---

## 4) Servir en la red (LAN) — incluso sin Wi-Fi

Los **códigos QR** y el acceso desde el celular necesitan que otro dispositivo
pueda abrir la IP de la PC servidor. Pasos:

1. **IP de la PC servidor:** en Windows corré `ipconfig` y mirá *Dirección IPv4*
   (ej. `192.168.1.66`). Conviene fijarla (IP estática o reserva por DHCP en el
   router) para que no cambie.
2. **Configurá la URL pública** en `config/catalogo.php`:
   ```php
   define('URL_PUBLICA', 'http://192.168.1.66/TermoPlast');
   ```
   (o `https://…` si activaste SSL). Esta es la base de los QR.
3. **Firewall de Windows:** permití **Apache (httpd.exe)** en redes privadas, o
   abrí los puertos **80** (HTTP) y **443** (HTTPS). Sin esto, el celular no
   conecta aunque estén en la misma red.
4. Probá desde el celular: `http://192.168.1.66/TermoPlast/`.

**Sin Wi-Fi / sin internet (defensa en el aula):** no hace falta internet, solo
una **red local** entre la PC y el celular. Opciones:
- **Cable + PC como punto de acceso:** activá *Zona con cobertura inalámbrica
  móvil* (hotspot) en Windows; el celular se conecta a esa red y usás la IP que
  Windows le asigna a la PC en el hotspot (suele ser `192.168.137.1`). Actualizá
  `URL_PUBLICA` a esa IP.
- **Router/switch sin salida a internet:** igual sirve; es una LAN. Usá la IP que
  tome la PC en esa red.
- El **mapa de puntos de retiro** usa tiles de OpenStreetMap (necesitan internet):
  sin conexión muestra un aviso "Mapa sin conexión" y la **lista de direcciones**
  sigue visible. El resto de la app (QR, chatbot, gráficos, exportaciones)
  funciona 100% offline en la LAN.

---

## 5) Checklist de seguridad (antes de mostrar/producción)

- [ ] `AllowOverride All` activo y verificado (403 en `config/conexion.php`).
- [ ] `admin/crear_admin.php` **borrado o renombrado** tras crear el primer admin.
- [ ] `credenciales.txt` **fuera del repositorio** (ya está en `.gitignore`) y no
      accesible por URL (403).
- [ ] Contraseñas de admin/emprendedor **cambiadas** (no las de prueba).
- [ ] Modo desarrollo: el sitio **no muestra errores** a visitantes de la red (el
      código ya pone `display_errors` en ON **solo** para `127.0.0.1`/localhost).
- [ ] Si servís por HTTPS: certificado cargado y la app accesible por `https://`.
- [ ] Firewall: solo los puertos necesarios (80/443) abiertos.
- [ ] Backup del esquema y de `uploads/` antes de la defensa.

---

## 6) Problemas comunes

| Síntoma | Causa probable | Solución |
|---|---|---|
| El QR da "conexión rechazada" en el celular | `URL_PUBLICA` apunta a `localhost` o IP vieja | Poné la IP real de la PC (paso 4.2) |
| El celular no abre la IP de la PC | Firewall bloquea Apache | Permití httpd.exe / puertos 80-443 |
| `config/…` se ve en el navegador | `AllowOverride None` | Poné `AllowOverride All` y reiniciá Apache |
| El chat responde "asistente no disponible" | El servicio Python no está levantado | Iniciá `chatbot` (ver `INSTALAR_WINDOWS.txt`) |
| El mapa queda en blanco | Sin internet para los tiles | Es esperado: usá la lista de puntos; o conectá internet |
