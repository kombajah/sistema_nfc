<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// --- Credenciales: se leen de variables de entorno (configúralas en Vercel > Settings >
// Environment Variables). Nunca dejes la contraseña real escrita en este archivo ni en git. ---
// Para pruebas locales, copia config.local.example.php a config.local.php (no se sube a git)
// y completa ahí tus datos; se carga automáticamente si existe.
if (file_exists(__DIR__ . '/config.local.php')) require __DIR__ . '/config.local.php';

// Obtener variables de entorno (soporta getenv y $_ENV)
$host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '');
$port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '18347');
$db   = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? '');
$user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? '');
$pass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? '');

try {
    $options = [
        // Habilita SSL obligatorio requerido por Aiven
        PDO::MYSQL_ATTR_SSL_CA => true,
        // Evita que busque una ruta de certificado estricta local
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];

    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $conexion = new PDO($dsn, $user, $pass, $options);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("No se pudo conectar a la base de datos: " . $e->getMessage());
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --- Sesiones guardadas en la base de datos (no en archivos), porque en Vercel cada
// solicitud puede atenderla un contenedor distinto y perdería la sesión. ---
class SesionBD implements SessionHandlerInterface {
  private $conn;
  function __construct($conn){ $this->conn = $conn; }
  function open($path, $name): bool { return true; }
  function close(): bool { return true; }
  function read($id): string|false {
    $s = $this->conn->prepare("SELECT datos FROM sesiones WHERE id=? AND expira > NOW()");
    $s->bind_param("s", $id); $s->execute();
    $r = $s->get_result()->fetch_assoc();
    return $r ? $r['datos'] : '';
  }
  function write($id, $datos): bool {
    $exp = date('Y-m-d H:i:s', time() + 7200); // 2 horas de inactividad
    $s = $this->conn->prepare("INSERT INTO sesiones (id,datos,expira) VALUES (?,?,?) ON DUPLICATE KEY UPDATE datos=VALUES(datos), expira=VALUES(expira)");
    $s->bind_param("sss", $id, $datos, $exp);
    return $s->execute();
  }
  function destroy($id): bool {
    $s = $this->conn->prepare("DELETE FROM sesiones WHERE id=?"); $s->bind_param("s", $id);
    return $s->execute();
  }
  function gc($max_lifetime): int|false {
    $this->conn->query("DELETE FROM sesiones WHERE expira < NOW()");
    return 0;
  }
}
function iniciar_sesion(){
  global $conn;
  if (session_status() === PHP_SESSION_NONE) {
    session_set_save_handler(new SesionBD($conn), true);
    session_start();
  }
}

function requiere_login(){
  iniciar_sesion();
  if (!isset($_SESSION['maestro'])) { header("Location: index.php"); exit; }
}
function es_admin(){ return ($_SESSION['rol'] ?? '') === 'admin'; }
function docente_id(){ return (int)($_SESSION['id'] ?? 0); }
function filtro_docente(&$sql, &$types, &$vals, $alias='c'){
  if (!es_admin()) { $sql .= " AND $alias.docente_id = ?"; $types .= 'i'; $vals[] = docente_id(); }
}
