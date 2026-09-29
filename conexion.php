<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli("localhost", "root", "", "sistema_nfc");
$conn->set_charset("utf8mb4");
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function requiere_login(){
  if (session_status() === PHP_SESSION_NONE) session_start();
  if (!isset($_SESSION['maestro'])) { header("Location: index.php"); exit; }
}
function es_admin(){ return ($_SESSION['rol'] ?? '') === 'admin'; }
function docente_id(){ return (int)($_SESSION['id'] ?? 0); }
// Añade "AND alias.docente_id = ?" a $sql si el usuario no es admin, y agrega el valor a $vals/$types.
function filtro_docente(&$sql, &$types, &$vals, $alias='c'){
  if (!es_admin()) { $sql .= " AND $alias.docente_id = ?"; $types .= 'i'; $vals[] = docente_id(); }
}
