<?php
require 'conexion.php'; requiere_login();
$types=''; $vals=[];
$sqlC = "SELECT c.id, c.nombre, m.usuario docente FROM cursos c JOIN maestros m ON m.id=c.docente_id WHERE 1=1";
filtro_docente($sqlC,$types,$vals,'c');
$s=$conn->prepare($sqlC." ORDER BY c.nombre"); if($vals) $s->bind_param($types,...$vals); $s->execute();
$cursos = $s->get_result()->fetch_all(MYSQLI_ASSOC);
$cats = $conn->query("SELECT * FROM categorias ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$filtro = (int)($_GET['curso'] ?? 0);

$types=''; $vals=[];
$sql = "SELECT a.id, a.nombre alumno, c.nombre curso, ag.nombre asignatura, r.categoria_id, SUM(r.puntos) pts
        FROM alumnos a JOIN cursos c ON a.curso_id=c.id JOIN asignaturas ag ON ag.id=c.asignatura_id
        LEFT JOIN registro_puntos r ON r.alumno_id=a.id WHERE 1=1";
filtro_docente($sql,$types,$vals,'c');
if ($filtro) { $sql .= " AND c.id=?"; $types.='i'; $vals[]=$filtro; }
$sql .= " GROUP BY a.id, a.nombre, c.nombre, ag.nombre, r.categoria_id ORDER BY c.nombre, a.nombre";
$s = $conn->prepare($sql); if ($vals) $s->bind_param($types, ...$vals); $s->execute();

$filas = [];
foreach ($s->get_result() as $r) {
  $id = $r['id'];
  $filas[$id] ??= ['alumno'=>$r['alumno'],'curso'=>$r['curso'],'asignatura'=>$r['asignatura'],'cat'=>[],'total'=>0];
  if ($r['categoria_id']) { $filas[$id]['cat'][$r['categoria_id']] = (int)$r['pts']; $filas[$id]['total'] += (int)$r['pts']; }
}
$can=[]; foreach($conn->query('SELECT alumno_id, SUM(puntos_virtuales) t FROM canjes GROUP BY alumno_id') as $q) $can[$q['alumno_id']]=(int)$q['t'];
foreach($filas as $id=>&$f) $f['canje']=$can[$id]??0; unset($f);
uasort($filas, fn($x,$y)=>[$x['curso'],-$x['total']]<=>[$y['curso'],-$y['total']]);
?>
<!DOCTYPE html><html lang="es"><head><title>Reportería</title><?php include 'head.php'; ?></head>
<body class="bg-light">
<?php include 'menu.php'; ?>
<div class="container mt-2">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Dashboard de Puntos</h3>
    <form method="GET"><select name="curso" class="form-select" onchange="this.form.submit()">
      <option value="0">Todos mis cursos</option>
      <?php foreach($cursos as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $filtro==$c['id']?'selected':'' ?>><?= h($c['nombre']) ?><?= es_admin()?' — '.h($c['docente']):'' ?></option><?php endforeach; ?>
    </select></form>
  </div>
  <div class="card p-3 shadow-sm table-responsive">
    <table class="table table-striped align-middle">
      <thead><tr><th>Curso</th><th>Asignatura</th><th>Alumno</th>
        <?php foreach($cats as $k): ?><th class="text-center"><?= h($k['nombre']) ?></th><?php endforeach; ?>
        <th class="text-center">Total</th><th class="text-center">Canjeado</th><th class="text-center">Saldo</th></tr></thead>
      <tbody>
      <?php foreach($filas as $f): ?>
        <tr><td><?= h($f['curso']) ?></td><td><?= h($f['asignatura']) ?></td><td><?= h($f['alumno']) ?></td>
          <?php foreach($cats as $k): ?><td class="text-center"><?= $f['cat'][$k['id']] ?? 0 ?></td><?php endforeach; ?>
          <td class="text-center"><strong><?= $f['total'] ?> pts</strong></td>
          <td class="text-center"><?= $f['canje'] ?></td>
          <td class="text-center"><strong><?= $f['total']-$f['canje'] ?></strong></td></tr>
      <?php endforeach; if(!$filas) echo "<tr><td colspan='".(6+count($cats))."' class='text-center text-muted'>Sin datos</td></tr>"; ?>
      </tbody>
    </table>
  </div>
</div></body></html> 
