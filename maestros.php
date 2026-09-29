<?php
require_once 'conexion.php'; requiere_login();
if (!es_admin()) { http_response_code(403); die("Solo el administrador puede gestionar maestros."); }
$mensaje=''; $error='';
if ($_SERVER["REQUEST_METHOD"]=="POST") {
  try {
    if (isset($_POST['crear'])) {
      $u=trim($_POST['usuario']); $p=$_POST['password']; $rol=$_POST['rol']==='admin'?'admin':'docente';
      if (strlen($p)<6) $error="La contraseña debe tener al menos 6 caracteres.";
      else { $h=password_hash($p,PASSWORD_DEFAULT); $s=$conn->prepare("INSERT INTO maestros (usuario,password,rol) VALUES (?,?,?)"); $s->bind_param("sss",$u,$h,$rol); $s->execute(); $mensaje="Maestro creado."; }
    } elseif (isset($_POST['clave'])) {
      $id=(int)$_POST['id']; $p=$_POST['password'];
      if (strlen($p)<6) $error="La contraseña debe tener al menos 6 caracteres.";
      else { $h=password_hash($p,PASSWORD_DEFAULT); $s=$conn->prepare("UPDATE maestros SET password=? WHERE id=?"); $s->bind_param("si",$h,$id); $s->execute(); $mensaje="Contraseña actualizada."; }
    } elseif (isset($_POST['borrar'])) {
      $id=(int)$_POST['id'];
      $s=$conn->prepare("SELECT usuario FROM maestros WHERE id=?"); $s->bind_param("i",$id); $s->execute(); $r=$s->get_result()->fetch_assoc();
      if (($r['usuario']??'')===$_SESSION['maestro']) $error="No puedes eliminar tu propio usuario.";
      else { $s=$conn->prepare("DELETE FROM maestros WHERE id=?"); $s->bind_param("i",$id); $s->execute(); $mensaje="Maestro eliminado (y sus asignaturas/cursos/alumnos)."; }
    }
  } catch (mysqli_sql_exception $e) { $error="Ese usuario ya existe."; }
}
$m=$conn->query("SELECT id,usuario,rol FROM maestros ORDER BY rol DESC, usuario")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html><html lang="es"><head><title>Maestros</title><?php include 'head.php'; ?></head>
<body class="bg-light"><?php include 'menu.php'; ?>
<div class="container mt-2">
  <?php if($mensaje) echo "<div class='alert alert-success'>".h($mensaje)."</div>"; ?>
  <?php if($error) echo "<div class='alert alert-danger'>".h($error)."</div>"; ?>
  <div class="card p-4 shadow-sm mb-3"><h4 class="text-primary mb-3">Nuevo maestro</h4>
    <form method="POST" class="row g-2">
      <div class="col-md-4"><input name="usuario" class="form-control" placeholder="Usuario" required></div>
      <div class="col-md-4"><input type="password" name="password" class="form-control" placeholder="Contraseña (mín. 6)" required></div>
      <div class="col-md-2"><select name="rol" class="form-select"><option value="docente">Docente</option><option value="admin">Administrador</option></select></div>
      <div class="col-md-2"><button name="crear" class="btn btn-primary w-100">Crear</button></div>
    </form></div>
  <div class="card p-3 shadow-sm"><?php foreach($m as $x): ?>
    <div class="d-flex justify-content-between align-items-center border-bottom py-2 flex-wrap gap-2">
      <div><b><?= h($x['usuario']) ?></b> <span class="badge <?= $x['rol']=='admin'?'bg-dark':'bg-primary' ?>"><?= $x['rol']=='admin'?'Administrador':'Docente' ?></span></div>
      <div class="d-flex gap-2">
        <form method="POST" class="d-flex gap-1"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><input type="password" name="password" class="form-control form-control-sm" placeholder="Nueva clave" required><button name="clave" class="btn btn-sm btn-outline-primary">Cambiar</button></form>
        <form method="POST" onsubmit="return confirm('¿Eliminar maestro y todo su contenido (asignaturas, cursos, alumnos)?')"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><button name="borrar" class="btn btn-sm btn-outline-danger">✕</button></form>
      </div></div><?php endforeach; ?></div>
</div></body></html>
