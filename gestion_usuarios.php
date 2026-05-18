<?php
session_start();
require_once "vendor/autoload.php";
include("includes/bd.php");

// 1. Portero: Solo el Superusuario gestiona esta tabla
if (!isset($_SESSION['login']) || $_SESSION['rol'] !== 'superusuario') {
    header("Location: usuarios.php"); // Si no es súper, lo mandamos a su panel personal
    exit();
}

$mysqli = conectarBD();
$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);

$mensaje = "";

// 2. Acciones de Administración
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Cambiar Rol
    if (isset($_POST['accion']) && $_POST['accion'] === 'cambiar_rol') {
        $stmt = $mysqli->prepare("UPDATE usuarios SET rol = ? WHERE id = ?");
        $stmt->bind_param("si", $_POST['nuevo_rol'], $_POST['id_usuario']);
        $stmt->execute();
        $mensaje = "Rol actualizado correctamente.";
    }

    // Borrar Usuario
    if (isset($_POST['accion']) && $_POST['accion'] === 'borrar_usuario') {
        $id_borrar = intval($_POST['id_usuario']);
        // Evitamos que el súper se borre a sí mismo por error aquí
        if ($id_borrar !== $_SESSION['id_user']) {
            $stmt = $mysqli->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->bind_param("i", $id_borrar);
            $stmt->execute();
            $mensaje = "Usuario eliminado.";
        }
    }
}

// 3. Obtener lista completa para la tabla
$res = $mysqli->query("SELECT id, email, rol FROM usuarios ORDER BY id ASC");
$listaUsuarios = $res->fetch_all(MYSQLI_ASSOC);

echo $twig->render('gestion_usuarios.twig', [
    'usuarios' => $listaUsuarios,
    'mensaje'  => $mensaje,
    'session'  => $_SESSION,
    'roles_disponibles' => ['anonimo', 'usuario', 'moderador', 'gestor', 'superusuario']
]);