<?php
function conectarBD() {
    $host = "db";        // Nombre del servicio en el docker-compose
    $user = "user_p4";   // El que pusiste en MYSQL_USER
    $pass = "root";      // El que pusiste en MYSQL_PASSWORD
    $db   = "sibw_p4";   // El que pusiste en MYSQL_DATABASE

    // Usamos el constructor con control de errores
    $conexion = new mysqli($host, $user, $pass, $db);

    if ($conexion->connect_error) {
        die("Error de conexión: " . $conexion->connect_error);
    }

    $conexion->set_charset("utf8mb4");

    // Verificación dinámica de la columna hashtags en la tabla noticia
    $check = $conexion->query("SHOW COLUMNS FROM noticia LIKE 'hashtags'");
    if ($check && $check->num_rows == 0) {
        $conexion->query("ALTER TABLE noticia ADD COLUMN hashtags VARCHAR(255) DEFAULT NULL");
    }

    return $conexion;
}