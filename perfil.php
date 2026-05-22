<?php
session_start();
require_once "vendor/autoload.php";
include("includes/bd.php");

$mysqli = conectarBD();

// Si no hay sesión activa pero el navegador conserva la cookie, restauramos la sesión
if (!isset($_SESSION['login']) && isset($_COOKIE['usuario_recuerdame'])) {
    $id_cookie = (int)$_COOKIE['usuario_recuerdame'];
    
    $stmt_cookie = $mysqli->prepare("SELECT email, rol FROM usuarios WHERE id = ?");
    $stmt_cookie->bind_param("i", $id_cookie);
    $stmt_cookie->execute();
    $res_cookie = $stmt_cookie->get_result();
    
    if ($res_cookie && $res_cookie->num_rows > 0) {
        $usuario_auto = $res_cookie->fetch_assoc();
        // Regeneramos las variables de sesión automáticamente
        $_SESSION['login'] = true;
        $_SESSION['id_user'] = $id_cookie;
        $_SESSION['user'] = $usuario_auto['email'];
        $_SESSION['rol'] = $usuario_auto['rol'];
    }
}


// Si después de mirar la cookie sigue sin haber sesión, directos al login
if (!isset($_SESSION['login']) || !isset($_SESSION['id_user'])) {
    header("Location: login.php?modo=login");
    exit();
}

$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);

$id_propio = (int)$_SESSION['id_user'];
$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- ACCIÓN: ACTUALIZAR DATOS PROPIOS ---
    if (isset($_POST['accion']) && $_POST['accion'] === 'actualizar_perfil') {
        $nuevo_email = trim($_POST['email']);
        
        if (!empty($_POST['password'])) {
            // Requisito: Contraseña codificada siempre
            $pass_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare("UPDATE usuarios SET email = ?, password = ? WHERE id = ?");
            $stmt->bind_param("ssi", $nuevo_email, $pass_hash, $id_propio);
        } else {
            $stmt = $mysqli->prepare("UPDATE usuarios SET email = ? WHERE id = ?");
            $stmt->bind_param("si", $nuevo_email, $id_propio);
        }
        
        if ($stmt->execute()) {
            $_SESSION['user'] = $nuevo_email; // Actualizar el email reflejado en la sesión
            $mensaje = "Tus datos personales se han actualizado correctamente.";
        } else {
            $mensaje = "Error al actualizar: el email ya podría estar en uso.";
        }
    }

    // --- ACCIÓN: AUTO-ELIMINACIÓN DE CUENTA ---
    if (isset($_POST['accion']) && $_POST['accion'] === 'auto_eliminar') {
        // Regla del último administrador: no puede quedarse el sistema sin ningún administrador
        $puede_eliminar = true;
        if ($_SESSION['rol'] === 'administrador') {
            $res_count = $mysqli->query("SELECT COUNT(*) as total FROM usuarios WHERE rol = 'administrador'");
            $count_row = $res_count->fetch_assoc();
            if ($count_row['total'] <= 1) {
                $mensaje = "Error: Eres el único administrador del sistema. No puedes darte de baja sin antes asignar a otro usuario como administrador.";
                $puede_eliminar = false;
            }
        }

        if ($puede_eliminar) {
            $stmt = $mysqli->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->bind_param("i", $id_propio);
            
            if ($stmt->execute()) {
                // Si se borra, eliminamos también la cookie de larga duración
                if (isset($_COOKIE['usuario_recuerdame'])) {
                    setcookie("usuario_recuerdame", "", time() - 3600, "/");
                }
                session_destroy();
                header("Location: portada.php");
                exit();
            }
        }
    }

    

}

// Datos del perfil actual del usuario logueado
$user_data = ['email' => ''];
$stmt_user = $mysqli->prepare("SELECT email FROM usuarios WHERE id = ?");
$stmt_user->bind_param("i", $id_propio);
$stmt_user->execute();
$resultado_user = $stmt_user->get_result();
if ($user_data_row = $resultado_user->fetch_assoc()) {
    $user_data = $user_data_row;
}

echo $twig->render('perfil.twig', [
    'session'           => $_SESSION,
    'mensaje'           => $mensaje,
    'user_data'         => $user_data,
    'roles_disponibles' => ['anonimo', 'usuario', 'moderador', 'gestor', 'administrador']
]);