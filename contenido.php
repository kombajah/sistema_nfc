<?php
require_once 'conexion.php'; requiere_login();
$mensaje=''; $error='';
function nuevo_qr(){ return bin2hex(random_bytes(6)); }
function es_mio_curso($conn,$cid){
  $s=$conn->prepare("SELECT docente_id FROM cursos WHERE id=?"); $s->bind_param("i",$cid); $s->execute();
  $r=$s->get_result()->fetch_assoc();
  return $r && (es_admin() || (int)$r['docente_id']===docente_id());
}
function cupo($conn,$cid,$excluir=0){
  $s=$conn->prepare("SELECT COUNT(*) t FROM alumnos WHERE curso_id=? AND id<>?"); $s->bind_param("ii",$cid,$excluir); $s->execute();
  return $s->get_result()->fetch_assoc()['t'] < 50;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  try {
    if (isset($_POST['crear_asignatura'])) {
      $n = trim($_POST['nombre_asignatura']);
      $s = $conn->prepare("INSERT INTO asignaturas (nombre,docente_id) VALUES (?,?)");
      $did = docente_id(); $s->bind_param("si",$n,$did); $s->execute(); $mensaje = "Asignatura creada.";

    } elseif (isset($_POST['crear_curso'])) {
      $n = trim($_POST['nombre_curso']); $aid = (int)$_POST['asignatura_id'];
      // el docente solo puede usar sus propias asignaturas (el admin, cualquiera con dueño válido)
      $s=$conn->prepare("SELECT docente_id FROM asignaturas WHERE id=?"); $s->bind_param("i",$aid); $s->execute();
      $asig = $s->get_result()->fetch_assoc();
      if (!$asig) $error = "Asignatura no válida.";
      else {
        $did = es_admin() ? (int)$asig['docente_id'] : docente_id();
        if (!es_admin() && (int)$asig['docente_id'] !== docente_id()) $error = "Esa asignatura no te pertenece.";
        else {
          $s = $conn->prepare("INSERT INTO cursos (nombre,docente_id,asignatura_id) VALUES (?,?,?)");
          $s->bind_param("sii",$n,$did,$aid); $s->execute(); $mensaje = "Curso creado.";
        }
      }

    } elseif (isset($_POST['crear_alumno'])) {
      $cid=(int)$_POST['curso_id']; $n=trim($_POST['nombre_alumno']); $uid=trim($_POST['nfc_uid']) ?: null; $qr=nuevo_qr();
      if (!es_mio_curso($conn,$cid)) $error = "Ese curso no te pertenece.";
      elseif (!cupo($conn,$cid)) $error = "El curso ya tiene el máximo de 50 alumnos.";
      else { $s=$conn->prepare("INSERT INTO alumnos (curso_id,nombre,nfc_uid,qr_code) VALUES (?,?,?,?)");
             $s->bind_param("isss",$cid,$n,$uid,$qr); $s->execute(); $mensaje="Alumno registrado (QR generado automáticamente)."; }

    } elseif (isset($_POST['editar_alumno'])) {
      $id=(int)$_POST['id']; $cid=(int)$_POST['curso_id']; $n=trim($_POST['nombre_alumno']); $uid=trim($_POST['nfc_uid']) ?: null;
      $s=$conn->prepare("SELECT curso_id FROM alumnos WHERE id=?"); $s->bind_param("i",$id); $s->execute();
      $act=$s->get_result()->fetch_assoc();
      if (!$act || !es_mio_curso($conn,$act['curso_id']) || !es_mio_curso($conn,$cid)) $error = "No tienes permiso sobre ese alumno o curso.";
      elseif (!cupo($conn,$cid,$id)) $error = "El curso destino ya tiene 50 alumnos.";
      else {
        $s=$conn->prepare("UPDATE alumnos SET curso_id=?, nombre=?, nfc_uid=? WHERE id=?"); $s->bind_param("issi",$cid,$n,$uid,$id); $s->execute();
        if (!empty($_POST['regen_qr'])) { $q=nuevo_qr(); $s=$conn->prepare("UPDATE alumnos SET qr_code=? WHERE id=?"); $s->bind_param("si",$q,$id); $s->execute(); }
        $mensaje = "Alumno actualizado.";
      }

    } elseif (isset($_POST['borrar_alumno'])) {
      $id=(int)$_POST['id'];
      $s=$conn->prepare("SELECT curso_id FROM alumnos WHERE id=?"); $s->bind_param("i",$id); $s->execute(); $a=$s->get_result()->fetch_assoc();
      if (!$a || !es_mio_curso($conn,$a['curso_id'])) $error = "No tienes permiso sobre ese alumno.";
      else { $s=$conn->prepare("DELETE FROM alumnos WHERE id=?"); $s->bind_param("i",$id); $s->execute(); $mensaje="Alumno eliminado."; }

    } elseif (isset($_POST['borrar_curso'])) {
      $id=(int)$_POST['id'];
      if (!es_mio_curso($conn,$id)) $error = "No tienes permiso sobre ese curso.";
      else { $s=$conn->prepare("DELETE FROM cursos WHERE id=?"); $s->bind_param("i",$id); $s->execute(); $mensaje="Curso eliminado."; }

    } elseif (isset($_POST['borrar_asignatura'])) {
      $id=(int)$_POST['id'];
      $s=$conn->prepare("SELECT docente_id FROM asignaturas WHERE id=?"); $s->bind_param("i",$id); $s->execute(); $g=$s->get_result()->fetch_assoc();
      if (!$g || (!es_admin() && (int)$g['docente_id']!==docente_id())) $error = "No tienes permiso sobre esa asignatura.";
      else { $s=$conn->prepare("DELETE FROM asignaturas WHERE id=?"); $s->bind_param("i",$id); $s->execute(); $mensaje="Asignatura eliminada (y sus cursos)."; }
    }
  } catch (mysqli_sql_exception $e) { $error = "No se pudo guardar (¿tarjeta NFC ya asignada a otro alumno?)."; }
}

// --- Listados, filtrados por docente salvo que sea admin ---
$types=''; $vals=[];
$sqlA = "SELECT ag.*, m.usuario docente FROM asignaturas ag JOIN maestros m ON m.id=ag.docente_id WHERE 1=1";
filtro_docente($sqlA,$types,$vals,'ag');
$s=$conn->prepare($sqlA." ORDER BY ag.nombre"); if($vals) $s->bind_param($types,...$vals); $s->execute();
$asignaturas = $s->get_result()->fetch_all(MYSQLI_ASSOC);

$types=''; $vals=[];
$sqlC = "SELECT c.*, m.usuario docente, ag.nombre asignatura, (SELECT COUNT(*) FROM alumnos WHERE curso_id=c.id) tot
         FROM cursos c JOIN maestros m ON m.id=c.docente_id JOIN asignaturas ag ON ag.id=c.asignatura_id WHERE 1=1";
filtro_docente($sqlC,$types,$vals,'c');
$s=$conn->prepare($sqlC." ORDER BY c.nombre"); if($vals) $s->bind_param($types,...$vals); $s->execute();
$cursos = $s->get_result()->fetch_all(MYSQLI_ASSOC);

$idsCursos = array_column($cursos,'id');
$alumnos = [];
if ($idsCursos) {
  $ph = implode(',', array_fill(0,count($idsCursos),'?'));
  $s = $conn->prepare("SELECT * FROM alumnos WHERE curso_id IN ($ph) ORDER BY nombre");
  $s->bind_param(str_repeat('i',count($idsCursos)), ...$idsCursos); $s->execute();
  $alumnos = $s->get_result()->fetch_all(MYSQLI_ASSOC);
}
$ed = (int)($_GET['editar'] ?? 0);
?>
<!DOCTYPE html><html lang="es"><head><title>Contenido</title><?php include 'head.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script></head>
<body class="bg-light">
<?php include 'menu.php'; ?>
<div class="container mt-2">
  <?php if($mensaje) echo "<div class='alert alert-success'>".h($mensaje)."</div>"; ?>
  <?php if($error) echo "<div class='alert alert-danger'>".h($error)."</div>"; ?>

  <div class="row">
    <div class="col-md-4 mb-4"><div class="card p-4 shadow-sm h-100">
      <h4 class="text-primary mb-3">Agregar Asignatura</h4>
      <form method="POST">
        <input type="text" name="nombre_asignatura" class="form-control mb-3" required placeholder="Ej: Lenguaje">
        <button name="crear_asignatura" class="btn btn-primary w-100">Crear Asignatura</button>
      </form>
      <?php if (es_admin()): ?><small class="text-muted d-block mt-2">Como administrador, la asignatura queda asociada a tu propio usuario.</small><?php endif; ?>
    </div></div>

    <div class="col-md-4 mb-4"><div class="card p-4 shadow-sm h-100">
      <h4 class="text-primary mb-3">Agregar Curso</h4>
      <form method="POST">
        <input type="text" name="nombre_curso" class="form-control mb-3" required placeholder="Ej: 1ro Básico A">
        <select name="asignatura_id" class="form-select mb-3" required>
          <option value="" disabled selected>Elige una asignatura...</option>
          <?php foreach($asignaturas as $a): ?><option value="<?= (int)$a['id'] ?>"><?= h($a['nombre']) ?><?= es_admin()?' — '.h($a['docente']):'' ?></option><?php endforeach; ?>
        </select>
        <button name="crear_curso" class="btn btn-primary w-100" <?= $asignaturas?'':'disabled' ?>>Crear Curso</button>
      </form>
      <?php if(!$asignaturas): ?><small class="text-danger d-block mt-2">Primero crea una asignatura.</small><?php endif; ?>
      <small class="text-muted d-block mt-2">El mismo nombre de curso puede repetirse en otra asignatura o con otro docente: quedan separados.</small>
    </div></div>

    <div class="col-md-4 mb-4"><div class="card p-4 shadow-sm h-100">
      <h4 class="text-primary mb-3">Registrar Alumno</h4>
      <form method="POST">
        <select name="curso_id" class="form-select mb-3" required>
          <option value="" disabled selected>Elige un curso...</option>
          <?php foreach($cursos as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h($c['nombre']) ?> · <?= h($c['asignatura']) ?><?= es_admin()?' — '.h($c['docente']):'' ?> (<?= (int)$c['tot'] ?>/50)</option><?php endforeach; ?>
        </select>
        <input type="text" name="nombre_alumno" class="form-control mb-2" required placeholder="Nombre del alumno" <?= $cursos?'':'disabled' ?>>
        <div class="input-group mb-1">
          <input type="text" name="nfc_uid" id="nfc_uid" class="form-control" placeholder="Tarjeta NFC (opcional)">
          <button class="btn btn-dark" type="button" onclick="leerNFC('nfc_uid')">Escanear NFC</button>
        </div>
        <small class="text-muted d-block mb-3">Sin tarjeta NFC, el alumno se identifica con su <b>código QR</b> (se genera solo al guardar).</small>
        <button name="crear_alumno" class="btn btn-success w-100" <?= $cursos?'':'disabled' ?>>Guardar Alumno</button>
      </form>
      <?php if(!$cursos): ?><small class="text-danger d-block mt-2">Primero crea un curso.</small><?php endif; ?>
    </div></div>
  </div>

  <?php foreach($cursos as $c): ?>
  <div class="card p-3 shadow-sm mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <h5 class="mb-0"><?= h($c['nombre']) ?> <span class="badge bg-primary-subtle text-primary"><?= h($c['asignatura']) ?></span>
        <?php if(es_admin()): ?><span class="badge bg-secondary"><?= h($c['docente']) ?></span><?php endif; ?>
        <small class="text-muted">(<?= (int)$c['tot'] ?>/50)</small></h5>
      <div class="d-flex gap-2">
        <a href="qr.php?curso=<?= (int)$c['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">🖨️ Imprimir QR</a>
        <form method="POST" onsubmit="return confirm('¿Eliminar curso y sus alumnos?')">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button name="borrar_curso" class="btn btn-sm btn-outline-danger">Eliminar curso</button></form>
      </div>
    </div>
    <div class="mt-2">
    <?php foreach($alumnos as $a): if($a['curso_id']==$c['id']): ?>
      <?php if($ed==$a['id']): ?>
      <form method="POST" class="border rounded p-2 mb-2">
        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
        <input type="text" name="nombre_alumno" class="form-control mb-2" value="<?= h($a['nombre']) ?>" required>
        <select name="curso_id" class="form-select mb-2"><?php foreach($cursos as $c2): ?><option value="<?= (int)$c2['id'] ?>" <?= $c2['id']==$a['curso_id']?'selected':'' ?>><?= h($c2['nombre']) ?> · <?= h($c2['asignatura']) ?></option><?php endforeach; ?></select>
        <div class="input-group mb-2"><input type="text" name="nfc_uid" id="nfc_ed" class="form-control" value="<?= h($a['nfc_uid']) ?>" placeholder="Sin tarjeta NFC">
          <button class="btn btn-dark" type="button" onclick="leerNFC('nfc_ed')">Escanear NFC</button></div>
        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="regen_qr" value="1" id="rq"><label class="form-check-label" for="rq">Generar un QR nuevo (el anterior deja de funcionar)</label></div>
        <button name="editar_alumno" class="btn btn-sm btn-primary">Guardar</button> <a href="contenido.php" class="btn btn-sm btn-light">Cancelar</a>
      </form>
      <?php else: ?>
      <div class="d-flex justify-content-between align-items-center border-top py-2 flex-wrap gap-2">
        <div><?= h($a['nombre']) ?>
          <span class="badge <?= $a['nfc_uid']?'bg-success':'bg-secondary' ?>">NFC <?= $a['nfc_uid']?'✓':'—' ?></span>
          <span class="badge bg-info text-dark">QR ✓</span></div>
        <div class="d-flex gap-1">
          <button class="btn btn-sm btn-outline-secondary" onclick="verQR('<?= h($a['qr_code']) ?>','<?= h(addslashes($a['nombre'])) ?>')">Ver QR</button>
          <a href="?editar=<?= (int)$a['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
          <form method="POST" onsubmit="return confirm('¿Eliminar alumno?')"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button name="borrar_alumno" class="btn btn-sm btn-outline-danger">✕</button></form>
        </div>
      </div>
      <?php endif; ?>
    <?php endif; endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if(!$cursos): ?><p class="text-muted text-center">Aún no tienes cursos. Crea una asignatura y luego un curso.</p><?php endif; ?>

  <?php if($asignaturas): ?>
  <div class="card p-3 shadow-sm mt-4">
    <h5>Tus asignaturas</h5>
    <?php foreach($asignaturas as $a): ?>
    <div class="d-flex justify-content-between align-items-center border-top py-2">
      <div><?= h($a['nombre']) ?> <?php if(es_admin()): ?><span class="badge bg-secondary"><?= h($a['docente']) ?></span><?php endif; ?></div>
      <form method="POST" onsubmit="return confirm('¿Eliminar asignatura y todos sus cursos?')"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button name="borrar_asignatura" class="btn btn-sm btn-outline-danger">✕</button></form>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="modal fade" id="mqr" tabindex="-1"><div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content text-center p-3">
  <h6 id="qrNombre"></h6><div id="qrBox" class="d-flex justify-content-center bg-white p-2"></div>
  <button class="btn btn-light mt-2" data-bs-dismiss="modal">Cerrar</button></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let ndef = null, destino = null;
async function leerNFC(idCampo){
  destino = idCampo;
  if (!("NDEFReader" in window)) { alert("Web NFC no disponible (requiere Chrome en Android y HTTPS). Puedes escribir el UID a mano."); return; }
  try {
    if (!ndef) { ndef = new NDEFReader();
      ndef.addEventListener("reading", ({serialNumber}) => { document.getElementById(destino).value = serialNumber; alert("¡Tarjeta detectada!"); }); }
    await ndef.scan(); alert("Acerca la tarjeta al dispositivo...");
  } catch(e){ alert("Error NFC: " + e); }
}
function verQR(code, nombre){
  document.getElementById('qrNombre').innerText = nombre;
  const b = document.getElementById('qrBox'); b.innerHTML = '';
  new QRCode(b, {text: code, width: 200, height: 200});
  new bootstrap.Modal(document.getElementById('mqr')).show();
}
</script></body></html>
