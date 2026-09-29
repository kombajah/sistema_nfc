<?php
// --- Depuración de errores ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cargar archivo local de pruebas si existe
if (file_exists(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

// Obtener variables de entorno
$host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '');
$port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '18347');
$db   = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? '');
$user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? '');
$pass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? '');

try {
    $options = [
        PDO::MYSQL_ATTR_SSL_CA => true,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];

    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $conexion = new PDO($dsn, $user, $pass, $options);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("No se pudo conectar a la base de datos: " . $e->getMessage());
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --- Manejador de Sesiones en Base de Datos ---
class SesionBD implements SessionHandlerInterface {
    private $pdo;

    public function __construct($pdo){ 
        $this->pdo = $pdo; 
    }

    public function open($path, $name): bool { return true; }
    public function close(): bool { return true; }

    public function read($id): string|false {
        if (!$this->pdo) return '';
        try {
            $s = $this->pdo->prepare("SELECT datos FROM sesiones WHERE id = ? AND expira > NOW()");
            $s->execute([$id]);
            $r = $s->fetch(PDO::FETCH_ASSOC);
            return $r ? (string)$r['datos'] : '';
        } catch (Exception $e) {
            return '';
        }
    }

    public function write($id, $datos): bool {
        if (!$this->pdo) return false;
        try {
            $exp = date('Y-m-d H:i:s', time() + 7200); // 2 horas
            $s = $this->pdo->prepare("INSERT INTO sesiones (id, datos, expira) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE datos = VALUES(datos), expira = VALUES(expira)");
            return $s->execute([$id, $datos, $exp]);
        } catch (Exception $e) {
            return false;
        }
    }

    public function destroy($id): bool {
        if (!$this->pdo) return false;
        try {
            $s = $this->pdo->prepare("DELETE FROM sesiones WHERE id = ?");
            return $s->execute([$id]);
        } catch (Exception $e) {
            return false;
        }
    }

    public function gc($max_lifetime): int|false {
        if (!$this->pdo) return 0;
        try {
            $this->pdo->exec("DELETE FROM sesiones WHERE expira < NOW()");
            return 0;
        } catch (Exception $e) {
            return 0;
        }
    }
}

function iniciar_sesion(){
    global $conexion;
    if (session_status() === PHP_SESSION_NONE) {
        // Configuración de cookies segura para HTTPS / Vercel
        session_set_cookie_params([
            'lifetime' => 7200,
            'path' => '/',
            'domain' => '',
            'secure' => true,      // HTTPS en Vercel
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        if ($conexion) {
            session_set_save_handler(new SesionBD($conexion), true);
        }
        session_start();
    }
}

function requiere_login(){
    iniciar_sesion();
    if (empty($_SESSION['maestro'])) { 
        header("Location: index.php"); 
        exit; 
    }
}

function es_admin(){ 
    return ($_SESSION['rol'] ?? '') === 'admin'; 
}

function docente_id(){ 
    return (int)($_SESSION['id'] ?? 0); 
}

function filtro_docente(&$sql, &$vals, $alias='c'){
    if (!es_admin()) { 
        $sql .= " AND $alias.docente_id = ?"; 
        $vals[] = docente_id(); 
    }
}