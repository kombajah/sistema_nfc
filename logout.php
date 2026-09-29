<?php
require 'conexion.php';
iniciar_sesion();
$_SESSION = [];
session_destroy();
header("Location: index.php");
exit;
