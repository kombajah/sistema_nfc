<?php
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

// --- Manejo de sesiones en la base de datos usando PDO ---
class SesionBD implements SessionHandlerInterface {
    private $pdo;

    public function __construct($pdo){ 
        $this->pdo = $pdo; 
    }

    public function open($path, $name): bool { return true; }
    public function close(): bool { return true; }

    public function read($id): string|false {
        if (!$this->pdo) return '';
        $s = $this->pdo->prepare("SELECT datos FROM sesiones WHERE id=? AND expira > NOW()");
        $s->execute([$id]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        return $r ? $r['datos'] : '';
    }

    public function write($id, $datos): bool {
        if (!$this->pdo) return false;
        $exp = date('Y-m-d H:i:s', time() + 7200); // 2 horas de inactividad
        $s = $this->pdo->prepare("INSERT INTO sesiones (id,datos,expira) VALUES (?,?,?) ON DUPLICATE KEY UPDATE datos=VALUES(datos), expira=VALUES(expira)");
        return $s->execute([$id, $datos, $exp]);
    }

    public function destroy($id): bool {
        if (!$this->pdo) return false;
        $s = $this->pdo->prepare("DELETE FROM sesiones WHERE id=?");
        return $s->execute([$id]);
    }

    public function gc($max_lifetime): int|false {
        if (!$this->pdo) return 0;
        $this->pdo->exec("DELETE FROM sesiones WHERE expira < NOW()");
        return 0;
    }
}

function iniciar_sesion(){
    global $conexion;
    if (session_status() === PHP_SESSION_NONE) {
        session_set_save_handler(new SesionBD($conexion), true);
        session_start();
    }
}

function requiere_login(){
    iniciar_sesion();
    if (!isset($_SESSION['maestro'])) { 
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

function filtro_docente(&$sql, &$types, &$vals, $alias='c'){
    if (!es_admin()) { 
        $sql .= " AND $alias.docente_id = ?"; 
        $types .= 'i'; 
        $vals[] = docente_id(); 
    }
}