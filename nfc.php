<?php
require_once 'conexion.php'; requiere_login();
$mensaje = ''; $error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['asignar_puntos'])) {
  $alumno = (int)($_POST['alumno_id'] ?? 0);
  $cat = (int)($_POST['categoria_id'] ?? 0);
  $pts = (int)($_POST['puntos'] ?? 0);
  // el alumno debe pertenecer a un curso del docente (salvo admin)
  $s = $conn->prepare("SELECT c.docente_id FROM alumnos a JOIN cursos c ON c.id=a.curso_id WHERE a.id=?");
  $s->bind_param("i",$alumno); $s->execute(); $r = $s->get_result()->fetch_assoc();
  if (!$r || (!es_admin() && (int)$r['docente_id'] !== docente_id())) $error = "Alumno no válido.";
  elseif (!in_array($pts, [1,2,3], true)) $error = "Puntaje inválido.";
  else {
    $s = $conn->prepare("INSERT INTO registro_puntos (alumno_id,categoria_id,puntos) SELECT a.id, c.id, ? FROM alumnos a, categorias c WHERE a.id=? AND c.id=?");
    $s->bind_param("iii", $pts, $alumno, $cat); $s->execute();
    if ($s->affected_rows > 0) $mensaje = "¡Puntos asignados correctamente!"; else $error = "Motivo no válido.";
  }
}
$cats = $conn->query("SELECT * FROM categorias");
?>
<!DOCTYPE html><html lang="es"><head><title>Ingreso NFC</title><?php include 'head.php'; ?></head>
<body class="bg-light">
<?php include 'menu.php'; ?>
<div class="container mt-2">
  <?php if($mensaje) echo "<div class='alert alert-success'>".h($mensaje)."</div>"; ?>
  <?php if($error) echo "<div class='alert alert-danger'>".h($error)."</div>"; ?>
  <div class="card p-3 shadow-sm text-center" id="estadoNFC">
    <h4>ACERCAR SU TARJETA</h4>
    <p class="text-muted">Presiona un botón y pide al alumno que acerque su tarjeta o muestre su QR.</p>
    <p id="estado" class="text-primary"></p>
    <button class="btn btn-dark w-100 rounded-pill mb-2" onclick="iniciarEscaneo()">🛜 Escanear tarjeta NFC</button>
    <button class="btn btn-outline-dark w-100 rounded-pill" onclick="iniciarQR()">📷 Escanear código QR</button>
    <div id="lectorQR" class="mt-3" style="display:none; max-width:320px; min-height:250px; margin:auto"></div>
  </div>
  <div class="card p-4 mt-3 shadow-sm" id="formPuntos" style="display:none">
    <h5 id="nombreAlumnoDisplay" class="text-primary mb-0"></h5>
    <p id="cursoDisplay" class="text-muted mb-3"></p>
    <form method="POST" onsubmit="return validar()">
      <input type="hidden" name="alumno_id" id="alumno_id_input">
      <label class="form-label">Motivo</label>
      <select name="categoria_id" class="form-select mb-3" required>
        <option value="" disabled selected>Elige un motivo...</option>
        <?php while($r=$cats->fetch_assoc()): ?><option value="<?= (int)$r['id'] ?>"><?= h($r['nombre']) ?></option><?php endwhile; ?>
      </select>
      <label class="form-label">Cantidad de puntos:</label>
      <div class="d-flex justify-content-between mb-4">
        <?php foreach([1,2,3] as $n): ?><button type="button" class="btn-punto" onclick="seleccionarPunto(<?= $n ?>, this)"><?= $n ?> pt<?= $n>1?'s':'' ?></button><?php endforeach; ?>
      </div>
      <input type="hidden" name="puntos" id="input_puntos">
      <button type="submit" name="asignar_puntos" class="btn btn-primary w-100 rounded-pill fs-5" style="background:#2e44d3">Confirmar Asignación</button>
    </form>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<script>
let ndef = null, qr = null, ocupado = false;

function seleccionarPunto(v, el){
  document.querySelectorAll('.btn-punto').forEach(b => b.classList.remove('active'));
  el.classList.add('active'); 
  document.getElementById('input_puntos').value = v;
}

function validar(){ 
  if(!document.getElementById('input_puntos').value){ 
    alert('Selecciona 1, 2 o 3 puntos'); 
    return false; 
  } 
  return true; 
}

async function cargar(codigo){
  if (ocupado) return false; 
  ocupado = true;
  try {
    const r = await fetch("buscar_alumno.php?uid=" + encodeURIComponent(codigo));
    const d = await r.json();
    if (d.error) { alert(d.error); return false; }
    
    await detenerQR();

    document.getElementById('estadoNFC').style.display = "none";
    document.getElementById('formPuntos').style.display = "block";
    document.getElementById('nombreAlumnoDisplay').innerText = d.nombre;
    document.getElementById('cursoDisplay').innerText = "Curso: " + d.curso + " · " + d.asignatura;
    document.getElementById('alumno_id_input').value = d.id;
    return true;
  } catch(e) {
    alert("Error al consultar el alumno: " + (e.message || e));
    return false;
  } finally { 
    ocupado = false; 
  }
}

async function iniciarEscaneo(){
  await detenerQR();
  if (!("NDEFReader" in window)) { 
    alert("Web NFC requiere Chrome en Android y HTTPS. Usa el lector QR."); 
    return; 
  }
  try {
    if (!ndef) { 
      ndef = new NDEFReader(); 
      ndef.addEventListener("reading", ({serialNumber}) => cargar(serialNumber)); 
    }
    await ndef.scan();
    document.getElementById('estado').innerText = "Escaneando... acerca la tarjeta ahora.";
  } catch(e){ 
    alert("Error al iniciar NFC: " + (e.message || e)); 
  }
}

async function iniciarQR(){
  if (typeof Html5Qrcode === 'undefined') { 
    alert("No se cargó la librería de código QR. Comprueba tu conexión."); 
    return; 
  }

  await detenerQR();

  const box = document.getElementById('lectorQR');
  box.style.display = 'block';

  // Permiso explícito de la cámara en el navegador
  try {
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
      const testStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
      testStream.getTracks().forEach(track => track.stop());
    }
  } catch(errPermiso) {
    box.style.display = 'none';
    alert("Permiso de cámara denegado o no disponible: " + (errPermiso.message || errPermiso));
    return;
  }

  // Inicialización del lector
  try {
    qr = new Html5Qrcode('lectorQR');

    const config = { 
      fps: 10, 
      qrbox: { width: 220, height: 220 },
      aspectRatio: 1.0
    };

    const onSuccess = async (txt) => {
      await cargar(txt);
    };

    const onErrorFrame = (err) => {};

    try {
      await qr.start({ facingMode: "environment" }, config, onSuccess, onErrorFrame);
    } catch (errFacing) {
      const cameras = await Html5Qrcode.getCameras();
      if (cameras && cameras.length > 0) {
        const camId = cameras[cameras.length - 1].id;
        await qr.start(camId, config, onSuccess, onErrorFrame);
      } else {
        throw new Error("No se encontraron cámaras activas en el dispositivo.");
      }
    }
  } catch(e) {
    await detenerQR();
    alert("Error al desplegar la cámara: " + (e.message || e));
  }
}

async function detenerQR() {
  const box = document.getElementById('lectorQR');
  if (qr) {
    try { 
      if (qr.isScanning) {
        await qr.stop(); 
      }
    } catch(e) {}
    try { qr.clear(); } catch(e) {}
    qr = null;
  }
  box.style.display = 'none';
}
</script></body></html>