<?php
// --- Enrutador para Vercel Serverless (evita error 403 y límite de funciones) ---
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$route = ltrim($uri, '/');

// Si se solicita reporte.php, cargarlo directamente sin redirigir
if ($route === 'reporte.php') {
    require_once __DIR__ . '/reporte.php';
    exit;
}

// Cargar la conexión e iniciar sesión
require_once 'conexion.php';
iniciar_sesion();

// Si ya hay una sesión activa, redirigir al dashboard
if (!empty($_SESSION['maestro'])) { 
    header("Location: reporte.php"); 
    exit; 
}

$error = '';

// Procesar el formulario de Login
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $u = $_POST['usuario'] ?? '';
    $p = $_POST['password'] ?? '';
    
    // Consulta con PDO nativo
    $s = $conexion->prepare("SELECT id, password, rol FROM maestros WHERE usuario = ?");
    $s->execute([$u]);
    $r = $s->fetch(PDO::FETCH_ASSOC);

    if ($r && password_verify($p, $r['password'])) {
        session_regenerate_id(true);
        $_SESSION['maestro'] = $u; 
        $_SESSION['id']      = $r['id']; 
        $_SESSION['rol']     = $r['rol'];
        
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
  <?php if (file_exists('head.php')) include_once 'head.php'; ?>
</head>
<body class="bg-light d-flex align-items-center vh-100">
<div class="container text-center" style="max-width:400px">
  <h2 class="mb-4 text-primary">Login Docente</h2>
  <form method="POST" class="card p-4 shadow-sm">
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>
    <input type="text" name="usuario" class="form-control mb-3" placeholder="Usuario" required>
    <input type="password" name="password" class="form-control mb-3" placeholder="Contraseña" required>
    <button type="submit" class="btn btn-primary w-100">Ingresar</button>
  </form>
</div>
</body>
</html>