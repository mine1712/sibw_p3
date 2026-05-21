<?php
// 1. INICIALIZACIÓN DE SESIÓN (Siempre en la línea 1, sin espacios antes)
session_start();
require_once "vendor/autoload.php";
include("includes/bd.php");

$mysqli = conectarBD();

// ==========================================
// 2. SISTEMA DE AUTOLOGIN POR COOKIES
// ==========================================
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

// ==========================================
// 3. PORTERO DE SEGURIDAD (CONTROL DE ACCESO)
// ==========================================
// Si después de mirar la cookie sigue sin haber sesión, directos al login
if (!isset($_SESSION['login']) || !isset($_SESSION['id_user'])) {
    header("Location: login.php?modo=login");
    exit();
}

// Configuración de Twig
$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);

$id_propio = (int)$_SESSION['id_user'];
$mensaje = "";

// ==========================================
// 4. PROCESAMIENTO DE ACCIONES (POST)
// ==========================================
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

    // --- ACCIÓN: CAMBIAR ROL DE UN USUARIO (Solo Administrador) ---
    if ($_SESSION['rol'] === 'administrador' && isset($_POST['nuevo_rol'], $_POST['id_usuario'])) {
        $id_cambiar = (int)$_POST['id_usuario'];
        $nuevo_rol = $_POST['nuevo_rol'];
        
        if ($id_cambiar !== $id_propio) {
            // Regla del último administrador
            $stmt_check = $mysqli->prepare("SELECT rol FROM usuarios WHERE id = ?");
            $stmt_check->bind_param("i", $id_cambiar);
            $stmt_check->execute();
            $user_check = $stmt_check->get_result()->fetch_assoc();
            $stmt_check->close();

            $puede_cambiar = true;
            if ($user_check && $user_check['rol'] === 'administrador' && $nuevo_rol !== 'administrador') {
                $res_count = $mysqli->query("SELECT COUNT(*) as total FROM usuarios WHERE rol = 'administrador'");
                $count_row = $res_count->fetch_assoc();
                if ($count_row['total'] <= 1) {
                    $mensaje = "Error: No se puede cambiar el rol del único administrador del sistema.";
                    $puede_cambiar = false;
                }
            }

            if ($puede_cambiar) {
                $stmt = $mysqli->prepare("UPDATE usuarios SET rol = ? WHERE id = ?");
                $stmt->bind_param("si", $nuevo_rol, $id_cambiar);
                $stmt->execute();
                $mensaje = "Rol de usuario modificado con éxito.";
            }
        }
    }

    // --- ACCIÓN: ELIMINAR USUARIO (Solo Administrador) ---
    if ($_SESSION['rol'] === 'administrador' && isset($_POST['accion']) && $_POST['accion'] === 'borrar_usuario') {
        $id_borrar = (int)$_POST['id_usuario'];
        
        if ($id_borrar !== $id_propio) {
            // Regla del último administrador
            $stmt_check = $mysqli->prepare("SELECT rol FROM usuarios WHERE id = ?");
            $stmt_check->bind_param("i", $id_borrar);
            $stmt_check->execute();
            $user_check = $stmt_check->get_result()->fetch_assoc();
            $stmt_check->close();

            $puede_borrar = true;
            if ($user_check && $user_check['rol'] === 'administrador') {
                $res_count = $mysqli->query("SELECT COUNT(*) as total FROM usuarios WHERE rol = 'administrador'");
                $count_row = $res_count->fetch_assoc();
                if ($count_row['total'] <= 1) {
                    $mensaje = "Error: No se puede eliminar al único administrador del sistema.";
                    $puede_borrar = false;
                }
            }

            if ($puede_borrar) {
                $stmt = $mysqli->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmt->bind_param("i", $id_borrar);
                $stmt->execute();
                $mensaje = "El usuario ha sido eliminado del sistema de forma permanente.";
            }
        }
    }
    
    // --- ACCIÓN: BORRAR NOTICIA (Gestores y Administradores) ---
    if (in_array($_SESSION['rol'], ['gestor', 'administrador']) && isset($_POST['accion']) && $_POST['accion'] === 'borrar_noticia') {
        $id_noticia = (int)$_POST['id_noticia'];
        $stmt = $mysqli->prepare("DELETE FROM noticia WHERE id = ?");
        $stmt->bind_param("i", $id_noticia);
        $stmt->execute();
        $mensaje = "La noticia ha sido eliminada correctamente.";
    }
}

// ==========================================
// 5. OBTENCIÓN DE DATOS PARA LA PLANTILLA TWIG
// ==========================================

// A. Datos del perfil actual del usuario logueado
$user_data = ['email' => ''];
$stmt_user = $mysqli->prepare("SELECT email FROM usuarios WHERE id = ?");
$stmt_user->bind_param("i", $id_propio);
$stmt_user->execute();
$resultado_user = $stmt_user->get_result();
if ($user_data_row = $resultado_user->fetch_assoc()) {
    $user_data = $user_data_row;
}


// ==========================================
// 6. ENVIAR TODO A TWIG
// ==========================================
echo $twig->render('perfil.twig', [
    'session'           => $_SESSION,
    'mensaje'           => $mensaje,
    'user_data'         => $user_data,
    'roles_disponibles' => ['anonimo', 'usuario', 'moderador', 'gestor', 'administrador']
]);