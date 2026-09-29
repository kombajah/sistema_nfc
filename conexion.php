<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// --- Credenciales: se leen de variables de entorno (configúralas en Vercel > Settings >
// Environment Variables). Nunca dejes la contraseña real escrita en este archivo ni en git. ---
// Para pruebas locales, copia config.local.example.php a config.local.php (no se sube a git)
// y completa ahí tus datos; se carga automáticamente si existe.
if (file_exists(__DIR__ . '/config.local.php')) require __DIR__ . '/config.local.php';

$DB_HOST = getenv('DB_HOST') ?: 'mysql-sistema-nfc-sistema-nfc.d.aivencloud.com';
$DB_PORT = (int)(getenv('DB_PORT') ?: 18347);
$DB_NAME = getenv('DB_NAME') ?: 'defaultdb';
$DB_USER = getenv('DB_USER') ?: 'avnadmin';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_CA   = __DIR__ . '/certs/ca.pem';

try {
  $conn = mysqli_init();
  $conn->ssl_set(null, null, $DB_CA, null, null);
  $conn->real_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT, null, MYSQLI_CLIENT_SSL);
  $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
  http_response_code(500);
  die("No se pudo conectar a la base de datos. Revisa las variables de entorno DB_HOST/DB_PORT/DB_USER/DB_PASS/DB_NAME y que certs/ca.pem esté presente.");
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
