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
    return $conexion;
}