<?php
require_once 'conexion.php';
requiere_login();
$mensaje = ''; $error = '';
function tasa($conn){ $r=$conn->query("SELECT valor FROM config WHERE clave='tasa_canje'")->fetch_assoc(); return max(1,(int)($r['valor']??10)); }
function alumno_permitido($conn,$id){
  $s=$conn->prepare("SELECT c.docente_id FROM alumnos a JOIN cursos c ON c.id=a.curso_id WHERE a.id=?"); $s->bind_param("i",$id); $s->execute();
  $r=$s->get_result()->fetch_assoc(); return $r && (es_admin() || (int)$r['docente_id']===docente_id());
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if (isset($_POST['guardar_tasa'])) {
    $t = max(1,(int)$_POST['tasa']);
    $s = $conn->prepare("INSERT INTO config (clave,valor) VALUES ('tasa_canje',?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)");
    $v = (string)$t; $s->bind_param("s",$v); $s->execute(); $mensaje = "Tasa actualizada.";
  } elseif (isset($_POST['canjear'])) {
    $alumno = (int)$_POST['alumno_id']; $base = (int)$_POST['puntos_base'];
    $obs = mb_substr(trim($_POST['observacion'] ?? ''), 0, 200);
    if (!alumno_permitido($conn,$alumno)) $error = "Alumno no válido.";
    elseif ($base < 1 || $base > 100) $error = "Puntos base inválidos (1 a 100).";
    else {
      $tasa = tasa($conn); $costo = $base * $tasa;
      $s = $conn->prepare("INSERT INTO canjes (alumno_id,puntos_virtuales,puntos_base,observacion)
        SELECT a.id,?,?,? FROM alumnos a WHERE a.id=?
        AND (COALESCE((SELECT SUM(puntos) FROM registro_puntos WHERE alumno_id=a.id),0)
           - COALESCE((SELECT SUM(puntos_virtuales) FROM canjes WHERE alumno_id=a.id),0)) >= ?");
      $s->bind_param("iisii", $costo, $base, $obs, $alumno, $costo); $s->execute();
      if ($s->affected_rows > 0) $mensaje = "Canje registrado: -$costo pts virtuales → +$base pt(s) base.";
      else $error = "Saldo insuficiente (se necesitan $costo pts virtuales).";
    }
  }
}
$tasa = tasa($conn);
$filtro = (int)($_GET['curso'] ?? 0);
$types=''; $vals=[];
$sqlC = "SELECT c.id, c.nombre FROM cursos c WHERE 1=1"; filtro_docente($sqlC,$types,$vals,'c');
$s=$conn->prepare($sqlC." ORDER BY c.nombre"); if($vals) $s->bind_param($types,...$vals); $s->execute();
$cursos = $s->get_result()->fetch_all(MYSQLI_ASSOC);

$types=''; $vals=[];
$sql = "SELECT a.id, a.nombre, c.nombre curso, ag.nombre asignatura,
  COALESCE((SELECT SUM(puntos) FROM registro_puntos WHERE alumno_id=a.id),0) ganados,
  COALESCE((SELECT SUM(puntos_virtuales) FROM canjes WHERE alumno_id=a.id),0) canjeados,
  COALESCE((SELECT SUM(puntos_base) FROM canjes WHERE alumno_id=a.id),0) base
  FROM alumnos a JOIN cursos c ON a.curso_id=c.id JOIN asignaturas ag ON ag.id=c.asignatura_id WHERE 1=1";
filtro_docente($sql,$types,$vals,'c');
if ($filtro) { $sql .= " AND c.id=?"; $types.='i'; $vals[]=$filtro; }
$sql .= " ORDER BY c.nombre, a.nombre";
$s = $conn->prepare($sql); if ($vals) $s->bind_param($types,...$vals); $s->execute();
$alumnos = $s->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html><html lang="es"><head><title>Canje de puntos</title><?php include 'head.php'; ?></head>
<body class="bg-light">
<?php include 'menu.php'; ?>
<div class="container mt-2">
  <?php if($mensaje) echo "<div class='alert alert-success'>".h($mensaje)."</div>"; ?>
  <?php if($error) echo "<div class='alert alert-danger'>".h($error)."</div>"; ?>
  <div class="row">
    <div class="col-md-7 mb-4"><div class="card p-4 shadow-sm">
      <h4 class="text-primary mb-3">Canjear puntos</h4>
      <form method="POST">
        <select name="alumno_id" class="form-select mb-3" required>
          <option value="" disabled selected>Elige un alumno...</option>
          <?php foreach($alumnos as $a): $saldo=$a['ganados']-$a['canjeados']; ?>
            <option value="<?= (int)$a['id'] ?>"><?= h($a['curso']) ?> · <?= h($a['asignatura']) ?> — <?= h($a['nombre']) ?> (saldo: <?= $saldo ?>)</option>
          <?php endforeach; ?>
        </select>
        <label class="form-label">Puntos base a otorgar (1 pt base = <?= $tasa ?> pts virtuales)</label>
        <input type="number" name="puntos_base" id="pb" class="form-control mb-1" min="1" max="100" value="1" required oninput="cst()">
        <small class="text-muted d-block mb-3">Costo: <strong id="costo"><?= $tasa ?></strong> pts virtuales</small>
        <input type="text" name="observacion" class="form-control mb-3" maxlength="200" placeholder="Observación (ej: Prueba de Matemática)">
        <button name="canjear" class="btn btn-primary w-100" onclick="return confirm('¿Confirmar canje?')">Confirmar canje</button>
      </form></div></div>
    <div class="col-md-5 mb-4"><div class="card p-4 shadow-sm">
      <h5 class="mb-3">Tasa de canje</h5>
      <form method="POST" class="d-flex gap-2">
        <input type="number" name="tasa" class="form-control" min="1" value="<?= $tasa ?>" required>
        <button name="guardar_tasa" class="btn btn-outline-primary">Guardar</button>
      </form>
      <small class="text-muted mt-2">Tasa global para todos los docentes. No altera canjes pasados.</small>
    </div></div>
  </div>
  <div class="card p-3 shadow-sm table-responsive">
    <form method="GET" class="mb-3"><select name="curso" class="form-select w-auto" onchange="this.form.submit()">
      <option value="0">Todos mis cursos</option>
      <?php foreach($cursos as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $filtro==$c['id']?'selected':'' ?>><?= h($c['nombre']) ?></option><?php endforeach; ?>
    </select></form>
    <table class="table table-striped align-middle">
      <thead><tr><th>Curso</th><th>Asignatura</th><th>Alumno</th><th class="text-center">Ganados</th><th class="text-center">Canjeados</th><th class="text-center">Saldo</th><th class="text-center">Pts base obtenidos</th></tr></thead>
      <tbody>
      <?php foreach($alumnos as $a): ?>
        <tr><td><?= h($a['curso']) ?></td><td><?= h($a['asignatura']) ?></td><td><?= h($a['nombre']) ?></td>
          <td class="text-center"><?= (int)$a['ganados'] ?></td><td class="text-center"><?= (int)$a['canjeados'] ?></td>
          <td class="text-center"><strong><?= $a['ganados']-$a['canjeados'] ?></strong></td><td class="text-center"><?= (int)$a['base'] ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
  </div>
</div>
<script>const T=<?= $tasa ?>;function cst(){document.getElementById('costo').innerText=(parseInt(document.getElementById('pb').value)||0)*T;}</script>
</body></html>
