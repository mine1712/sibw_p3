<?php

function conectarBD() {
    $host = "p3-db";
    $user = "usuario_p3"; 
    $pass = "root";       
    $db   = "p3_sibw"; 

    $conexion = new mysqli($host, $user, $pass, $db);

    if ($conexion->connect_error) {
        die("Error de conexión: " . $conexion->connect_error);
    }

    $conexion->set_charset("utf8mb4");
    return $conexion;
}
