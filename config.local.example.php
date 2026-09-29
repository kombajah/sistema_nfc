<?php
// Copia este archivo a config.local.php (NO lo subas a git) para probar en tu XAMPP local
// contra la base de datos de Aiven, sin tocar conexion.php ni exponer la clave en el repo.
putenv('DB_HOST=mysql-sistema-nfc-sistema-nfc.d.aivencloud.com');
putenv('DB_PORT=18347');
putenv('DB_NAME=defaultdb');
putenv('DB_USER=avnadmin');
putenv('DB_PASS=PON_AQUI_TU_PASSWORD_REAL');
