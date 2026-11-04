// ============================================================
//  preview3d.js — Previsualización 3D del SVG ("producto terminado").
//  Extruye las formas del SVG en un sólido rotable con three.js LOCAL
//  (funciona offline y es compatible con la CSP: todo se sirve desde 'self').
//
//  Novedad: las medidas del molde (ancho × largo × espesor en mm) se aplican
//  como ESCALA sobre un modelo de espesor unitario, así cambian el 3D EN VIVO
//  sin reconstruir la geometría.
//
//  API (window.TP3D):
//    render(svgText, mountEl, dims)  -> construye la escena
//    update(dims)                    -> reescala en vivo (ancho/largo/espesor)
//    limpiar()                       -> libera la escena
//  dims = { ancho, largo, espesor } en mm (0/undefined = tamaño natural).
// ============================================================
import * as THREE from './three/three.module.js';
import { SVGLoader } from './three/SVGLoader.js';
import { OrbitControls } from './three/OrbitControls.js';

let renderer, scene, camera, controls, animId, grupo, pivot;
let W0 = 1, H0 = 1, D0 = 1;   // tamaño natural del modelo (espesor unitario)

function limpiar() {
  if (animId) { cancelAnimationFrame(animId); animId = null; }
  if (controls) { controls.dispose(); controls = null; }
  if (renderer) {
    renderer.dispose();
    const el = renderer.domElement;
    if (el && el.parentNode) el.parentNode.removeChild(el);
    renderer = null;
  }
  scene = null; camera = null; grupo = null; pivot = null;
}

function colorDe(path) {
  const style = path.userData && path.userData.style;
  const fill = style ? style.fill : null;
  if (!fill || fill === 'none' || fill.indexOf('url(') === 0) return 0x15b3a6; // verde marca
  try { return new THREE.Color(fill).getHex(); } catch (e) { return 0x15b3a6; }
}

function fallback(mount) {
  mount.innerHTML = '<p style="color:var(--text-muted); font-size:.85rem; padding:16px; margin:0;">No se pudo generar la vista 3D de este archivo.</p>';
}

// Aplica ancho/largo/espesor (mm) como escala y normaliza el tamaño en pantalla.
function update(dims) {
  if (!grupo || !pivot) return;
  dims = dims || {};
  const A = dims.ancho   > 0 ? dims.ancho   : W0;
  const L = dims.largo   > 0 ? dims.largo   : H0;
  const E = dims.espesor > 0 ? dims.espesor : 1;
  // El modelo base mide W0×H0×D0 (espesor unitario): escalamos a A×L×E.
  // La Y va negativa porque el SVG tiene el eje Y hacia abajo.
  grupo.scale.set(A / W0, -(L / H0), E / D0);
  // Normalizamos para que la pieza entre siempre bien en el visor,
  // preservando la proporción ancho:largo:espesor.
  const maxd = Math.max(A, L, E) || 1;
  pivot.scale.setScalar(120 / maxd);
}

function render3D(svgText, mount, dims) {
  limpiar();
  mount.innerHTML = '';
  const w = mount.clientWidth || 320;
  const h = mount.clientHeight || 240;

  let datos;
  try { datos = new SVGLoader().parse(svgText); } catch (e) { fallback(mount); return; }
  const paths = (datos && datos.paths) || [];
  if (!paths.length) { fallback(mount); return; }

  // --- Construimos la geometría UNA vez con espesor unitario (depth = 1) ---
  renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.setSize(w, h);
  mount.appendChild(renderer.domElement);

  scene = new THREE.Scene();
  camera = new THREE.PerspectiveCamera(45, w / h, 0.1, 10000);
  scene.add(new THREE.AmbientLight(0xffffff, 0.78));
  const l1 = new THREE.DirectionalLight(0xffffff, 1.15); l1.position.set(1, 1, 2); scene.add(l1);
  const l2 = new THREE.DirectionalLight(0xffffff, 0.45); l2.position.set(-1, -0.5, -1); scene.add(l2);

  grupo = new THREE.Group();
  let hubo = false;
  paths.forEach(path => {
    const color = colorDe(path);
    const shapes = SVGLoader.createShapes(path);
    shapes.forEach(shape => {
      const geo = new THREE.ExtrudeGeometry(shape, {
        depth: 1, bevelEnabled: true, bevelThickness: 0.06,
        bevelSize: 0.04, bevelSegments: 2, curveSegments: 16
      });
      const mat = new THREE.MeshStandardMaterial({ color, metalness: 0.12, roughness: 0.55, side: THREE.DoubleSide });
      grupo.add(new THREE.Mesh(geo, mat));
      hubo = true;
    });
  });
  if (!hubo) { fallback(mount); return; }

  // Medimos el modelo base (sin escalar aún) y lo CENTRAMOS en el origen.
  let box = new THREE.Box3().setFromObject(grupo);
  const size = box.getSize(new THREE.Vector3());
  const center = box.getCenter(new THREE.Vector3());
  W0 = size.x || 1; H0 = size.y || 1; D0 = size.z || 1;
  grupo.children.forEach(m => m.geometry.translate(-center.x, -center.y, -center.z));

  pivot = new THREE.Group();
  pivot.add(grupo);
  scene.add(pivot);

  camera.position.set(45, 35, 200);
  camera.lookAt(0, 0, 0);

  controls = new OrbitControls(camera, renderer.domElement);
  controls.enableDamping = true;
  controls.dampingFactor = 0.08;
  controls.enablePan = false;
  controls.minDistance = 90;
  controls.maxDistance = 500;
  controls.autoRotate = true;
  controls.autoRotateSpeed = 1.9;

  function onResize() {
    const nw = mount.clientWidth || w, nh = mount.clientHeight || h;
    camera.aspect = nw / nh; camera.updateProjectionMatrix();
    renderer.setSize(nw, nh);
  }
  window.addEventListener('resize', onResize);

  update(dims);   // aplica las medidas actuales

  function loop() { animId = requestAnimationFrame(loop); controls.update(); renderer.render(scene, camera); }
  loop();
}

window.TP3D = { render: render3D, update: update, limpiar: limpiar };
