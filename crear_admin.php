<?php
include("includes/bd.php");

$mysqli = conectarBD();

$email = "admin3@admin.com";
$password = password_hash("1234", PASSWORD_DEFAULT);
$rol = "administrador";

$stmt = $mysqli->prepare("
    INSERT INTO usuarios (email, password, rol)
    VALUES (?, ?, ?)
");

$stmt->bind_param("sss", $email, $password, $rol);
$stmt->execute();

echo "Admin creado";