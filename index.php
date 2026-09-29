<?php
require 'conexion.php';
iniciar_sesion();

if (isset($_SESSION['maestro'])) { 
    header("Location: reporte.php"); 
    exit; 
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $u = $_POST['usuario'] ?? '';
    
    // Usamos $conexion con sintaxis PDO
    $s = $conexion->prepare("SELECT id, password, rol FROM maestros WHERE usuario = ?");
    $s->execute([$u]);
    $r = $s->fetch(PDO::FETCH_ASSOC);

    if ($r && password_verify($_POST['password'] ?? '', $r['password'])) {
        session_regenerate_id(true);
        $_SESSION['maestro'] = $u; 
        $_SESSION['id'] = $r['id']; 
        $_SESSION['rol'] = $r['rol'];
        header("Location: reporte.php"); 
        exit;
    }
    
    $error = "Usuario o contraseña incorrectos.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <title>Login - Sistema NFC</title>
  <?php include 'head.php'; ?>
</head>
<body class="bg-light d-flex align-items-center vh-100">
<div class="container text-center" style="max-width:400px">
  <h2 class="mb-4 text-primary">Login Docente</h2>
  <form method="POST" class="card p-4 shadow-sm">
    <?php if($error) echo "<div class='alert alert-danger'>".h($error)."</div>"; ?>
    <input type="text" name="usuario" class="form-control mb-3" placeholder="Usuario" required>
    <input type="password" name="password" class="form-control mb-3" placeholder="Contraseña" required>
    <button class="btn btn-primary w-100">Ingresar</button>
  </form>
</div>
</body>
</html>