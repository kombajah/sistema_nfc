<?php
require 'conexion.php';
iniciar_sesion();
header('Content-Type: application/json');
if (!isset($_SESSION['maestro'])) { http_response_code(403); echo json_encode(['error'=>'No autorizado']); exit; }
$code = trim($_GET['uid'] ?? '');
$sql = "SELECT a.id, a.nombre, c.nombre AS curso, ag.nombre AS asignatura, c.docente_id
        FROM alumnos a JOIN cursos c ON a.curso_id=c.id JOIN asignaturas ag ON ag.id=c.asignatura_id
        WHERE (a.nfc_uid=? OR a.qr_code=?)";
$s = $conn->prepare($sql); $s->bind_param("ss", $code, $code); $s->execute();
$r = $s->get_result()->fetch_assoc();
if (!$r) { echo json_encode(['error'=>'Tarjeta o QR no registrado']); exit; }
if (!es_admin() && (int)$r['docente_id'] !== docente_id()) { echo json_encode(['error'=>'Ese alumno pertenece a otro docente']); exit; }
unset($r['docente_id']);
echo json_encode($r);
