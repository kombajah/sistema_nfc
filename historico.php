<?php
require 'conexion.php'; requiere_login();
$types=''; $vals=[];
$sqlC = "SELECT c.id, c.nombre FROM cursos c WHERE 1=1"; filtro_docente($sqlC,$types,$vals,'c');
$s=$conn->prepare($sqlC." ORDER BY c.nombre"); if($vals) $s->bind_param($types,...$vals); $s->execute();
$cursos = $s->get_result()->fetch_all(MYSQLI_ASSOC);

$fc = (int)($_GET['curso'] ?? 0);
$fa = (int)($_GET['alumno'] ?? 0);
$desde = $_GET['desde'] ?? ''; $hasta = $_GET['hasta'] ?? '';
$okf = fn($d)=>preg_match('/^\d{4}-\d{2}-\d{2}$/',$d);

$types=''; $vals=[];
$sqlAl = "SELECT a.id,a.nombre FROM alumnos a JOIN cursos c ON c.id=a.curso_id WHERE 1=1"; filtro_docente($sqlAl,$types,$vals,'c');
$s=$conn->prepare($sqlAl." ORDER BY a.nombre"); if($vals) $s->bind_param($types,...$vals); $s->execute();
$alumnosSel = $s->get_result()->fetch_all(MYSQLI_ASSOC);

$types=''; $vals=[];
$where = '';
if ($fc) { $where .= " AND c.id=?"; $types.='i'; $vals[]=$fc; }
if ($fa) { $where .= " AND a.id=?"; $types.='i'; $vals[]=$fa; }
$sql = "SELECT m.*, a.nombre alumno, c.nombre curso, ag.nombre asignatura FROM (
   SELECT 'ganado' tipo, r.id, r.alumno_id, r.fecha, r.puntos delta, k.nombre detalle
     FROM registro_puntos r JOIN categorias k ON k.id=r.categoria_id
   UNION ALL
   SELECT 'canje', x.id, x.alumno_id, x.fecha, -x.puntos_virtuales,
     CONCAT('Canje por ', x.puntos_base, ' pt base', IF(x.observacion<>'', CONCAT(': ', x.observacion), ''))
     FROM canjes x) m
  JOIN alumnos a ON a.id=m.alumno_id JOIN cursos c ON c.id=a.curso_id JOIN asignaturas ag ON ag.id=c.asignatura_id
  WHERE 1=1 $where";
filtro_docente($sql,$types,$vals,'c');
$sql .= " ORDER BY m.alumno_id, m.fecha, m.tipo, m.id";
$s = $conn->prepare($sql); if ($vals) $s->bind_param($types, ...$vals); $s->execute();

$saldo = []; $mov = [];
foreach ($s->get_result() as $r) {
  $saldo[$r['alumno_id']] = ($saldo[$r['alumno_id']] ?? 0) + $r['delta'];
  $r['saldo'] = $saldo[$r['alumno_id']];
  $d = substr($r['fecha'],0,10);
  if ($okf($desde) && $d < $desde) continue;
  if ($okf($hasta) && $d > $hasta) continue;
  $mov[] = $r;
}
usort($mov, fn($x,$y)=>[$y['fecha'],$y['id']]<=>[$x['fecha'],$x['id']]);
?>
<!DOCTYPE html><html lang="es"><head><title>Histórico</title><?php include 'head.php'; ?></head>
<body class="bg-light">
<?php include 'menu.php'; ?>
<div class="container mt-2">
  <h3 class="mb-3">Histórico de movimientos</h3>
  <form method="GET" class="card p-3 shadow-sm mb-3 row g-2 flex-row">
    <div class="col-md-3"><select name="curso" class="form-select"><option value="0">Todos mis cursos</option>
      <?php foreach($cursos as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $fc==$c['id']?'selected':'' ?>><?= h($c['nombre']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><select name="alumno" class="form-select"><option value="0">Todos los alumnos</option>
      <?php foreach($alumnosSel as $a): ?><option value="<?= (int)$a['id'] ?>" <?= $fa==$a['id']?'selected':'' ?>><?= h($a['nombre']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><input type="date" name="desde" class="form-control" value="<?= h($desde) ?>"></div>
    <div class="col-md-2"><input type="date" name="hasta" class="form-control" value="<?= h($hasta) ?>"></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">Filtrar</button></div>
  </form>
  <div class="card p-3 shadow-sm table-responsive">
    <table class="table table-striped align-middle">
      <thead><tr><th>Fecha</th><th>Curso</th><th>Asignatura</th><th>Alumno</th><th>Tipo</th><th>Detalle</th><th class="text-center">Puntos</th><th class="text-center">Saldo</th></tr></thead>
      <tbody>
      <?php foreach($mov as $m): ?>
        <tr><td><?= h(date('d/m/Y H:i', strtotime($m['fecha']))) ?></td><td><?= h($m['curso']) ?></td><td><?= h($m['asignatura']) ?></td><td><?= h($m['alumno']) ?></td>
          <td><?= $m['tipo']=='ganado' ? '<span class="badge bg-success">Ganado</span>' : '<span class="badge bg-warning text-dark">Canje</span>' ?></td>
          <td><?= h($m['detalle']) ?></td>
          <td class="text-center <?= $m['delta']>0?'text-success':'text-danger' ?>"><strong><?= $m['delta']>0?'+':'' ?><?= (int)$m['delta'] ?></strong></td>
          <td class="text-center"><?= (int)$m['saldo'] ?></td></tr>
      <?php endforeach; if(!$mov) echo "<tr><td colspan='8' class='text-center text-muted'>Sin movimientos</td></tr>"; ?>
      </tbody></table>
  </div>
  <small class="text-muted">El saldo es acumulado por alumno (incluye movimientos anteriores al rango de fechas).</small>
</div></body></html>
