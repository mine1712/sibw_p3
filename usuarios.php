<?php
session_start();
require_once "vendor/autoload.php";
include("includes/bd.php");

// 1. Portero: Verificamos login Y existencia de ID en la sesión
if (!isset($_SESSION['login']) || !isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit();
}

$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);
$mysqli = conectarBD();

// Aseguramos que el ID sea un entero desde el principio
$id_propio = (int)$_SESSION['id_user'];
$mensaje = "";

// 2. Acciones de Gestión (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- AUTOGESTIÓN ---
    if (isset($_POST['accion']) && $_POST['accion'] === 'actualizar_perfil') {
        $nuevo_email = $_POST['email'];
        
        if (!empty($_POST['password'])) {
            $pass_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare("UPDATE usuarios SET email = ?, password = ? WHERE id = ?");
            $stmt->bind_param("ssi", $nuevo_email, $pass_hash, $id_propio);
        } else {
            $stmt = $mysqli->prepare("UPDATE usuarios SET email = ? WHERE id = ?");
            $stmt->bind_param("si", $nuevo_email, $id_propio);
        }
        
        if ($stmt->execute()) {
            $_SESSION['user'] = $nuevo_email;
            $mensaje = "Datos actualizados correctamente.";
        }
    }

    if (isset($_POST['accion']) && $_POST['accion'] === 'auto_eliminar') {
        $mysqli->query("DELETE FROM usuarios WHERE id = $id_propio");
        session_destroy();
        header("Location: index.php");
        exit();
    }

    // --- ADMINISTRACIÓN (Superusuario) ---
    if ($_SESSION['rol'] === 'superusuario') {
        if (isset($_POST['nuevo_rol'], $_POST['id_usuario'])) {
            $stmt = $mysqli->prepare("UPDATE usuarios SET rol = ? WHERE id = ?");
            $stmt->bind_param("si", $_POST['nuevo_rol'], $_POST['id_usuario']);
            $stmt->execute();
        }
        if (isset($_POST['accion']) && $_POST['accion'] === 'borrar_usuario') {
            $id_borrar = (int)$_POST['id_usuario'];
            if ($id_borrar !== $id_propio) { // No borrarse a sí mismo por aquí
                $stmt = $mysqli->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmt->bind_param("i", $id_borrar);
                $stmt->execute();
            }
        }
    }

    // --- GESTIÓN DE NOTICIAS ---
    if (in_array($_SESSION['rol'], ['gestor', 'superusuario'])) {
        if (isset($_POST['accion']) && $_POST['accion'] === 'borrar_noticia') {
            $stmt = $mysqli->prepare("DELETE FROM noticia WHERE id = ?");
            $id_not = (int)$_POST['id_noticia'];
            $stmt->bind_param("i", $id_not);
            $stmt->execute();
        }
    }
}

// 3. Obtener Datos
$listaUsuarios = [];
if ($_SESSION['rol'] === 'superusuario') {
    $res = $mysqli->query("SELECT id, email, rol FROM usuarios");
    $listaUsuarios = $res->fetch_all(MYSQLI_ASSOC);
}

// OBTENCIÓN SEGURA: Aquí fallaba antes.
$user_data = ['email' => '']; // Valor por defecto
$res_user = $mysqli->query("SELECT email FROM usuarios WHERE id = $id_propio");
if ($res_user && $res_user->num_rows > 0) {
    $user_data = $res_user->fetch_assoc();
}

$busqueda = $_GET['q'] ?? '';
$noticias = [];

if (in_array($_SESSION['rol'], ['gestor', 'superusuario'])) {
    if (!empty($busqueda)) {
        $stmt = $mysqli->prepare("SELECT id, titulo, fecha, hashtags FROM noticia WHERE titulo LIKE ? OR descripcion LIKE ? ORDER BY fecha DESC");
        $like = "%$busqueda%";
        $stmt->bind_param("ss", $like, $like);
        $stmt->execute();
        $noticias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $res = $mysqli->query("SELECT id, titulo, fecha, hashtags FROM noticia ORDER BY fecha DESC");
        $noticias = $res->fetch_all(MYSQLI_ASSOC);
    }
}

// 4. Renderizar
echo $twig->render('usuarios.twig', [
    'usuarios'          => $listaUsuarios,
    'noticias'          => $noticias,
    'user_data'         => $user_data,
    'session'           => $_SESSION,
    'q'                 => $busqueda,
    'mensaje'           => $mensaje,
    'roles_disponibles' => ['anonimo', 'usuario', 'moderador', 'gestor', 'superusuario']
]);