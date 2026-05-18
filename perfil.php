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

    // --- ACCIÓN: CAMBIAR ROL DE UN USUARIO (Solo Superusuario) ---
    if ($_SESSION['rol'] === 'superusuario' && isset($_POST['nuevo_rol'], $_POST['id_usuario'])) {
        $id_cambiar = (int)$_POST['id_usuario'];
        $nuevo_rol = $_POST['nuevo_rol'];
        
        if ($id_cambiar !== $id_propio) {
            $stmt = $mysqli->prepare("UPDATE usuarios SET rol = ? WHERE id = ?");
            $stmt->bind_param("si", $nuevo_rol, $id_cambiar);
            $stmt->execute();
            $mensaje = "Rol de usuario modificado con éxito.";
        }
    }

    // --- ACCIÓN: ELIMINAR USUARIO (Solo Superusuario) ---
    if ($_SESSION['rol'] === 'superusuario' && isset($_POST['accion']) && $_POST['accion'] === 'borrar_usuario') {
        $id_borrar = (int)$_POST['id_usuario'];
        
        if ($id_borrar !== $id_propio) {
            $stmt = $mysqli->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->bind_param("i", $id_borrar);
            $stmt->execute();
            $mensaje = "El usuario ha sido eliminado del sistema de forma permanente.";
        }
    }
    
    // --- ACCIÓN: BORRAR NOTICIA (Gestores y Superusuarios) ---
    if (in_array($_SESSION['rol'], ['gestor', 'superusuario']) && isset($_POST['accion']) && $_POST['accion'] === 'borrar_noticia') {
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

// B. Listado y filtrado de noticias (Solo Gestor / Superusuario)
$busqueda = $_GET['q'] ?? '';
$noticias = [];

if (in_array($_SESSION['rol'], ['gestor', 'superusuario'])) {
    if (!empty($busqueda)) {
        $stmt_not = $mysqli->prepare("SELECT id, titulo, fecha FROM noticia WHERE titulo LIKE ? OR descripcion LIKE ? ORDER BY fecha DESC");
        $like_str = "%" . $busqueda . "%";
        $stmt_not->bind_param("ss", $like_str, $like_str);
        $stmt_not->execute();
        $noticias = $stmt_not->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $res_not = $mysqli->query("SELECT id, titulo, fecha FROM noticia ORDER BY fecha DESC");
        $noticias = $res_not->fetch_all(MYSQLI_ASSOC);
    }
}

// C. Lista de todos los usuarios (Solo Superusuario)
$listaUsuarios = [];
if ($_SESSION['rol'] === 'superusuario') {
    $res_users = $mysqli->query("SELECT id, email, rol FROM usuarios ORDER BY id ASC");
    $listaUsuarios = $res_users->fetch_all(MYSQLI_ASSOC);
}

// ==========================================
// 6. ENVIAR TODO A TWIG
// ==========================================
// Recuerda cambiar 'perfil.twig' por el nombre real de tu archivo de plantilla
echo $twig->render('perfil.twig', [
    'session'           => $_SESSION,
    'mensaje'           => $mensaje,
    'user_data'         => $user_data,
    'q'                 => $busqueda,
    'noticias'          => $noticias,
    'usuarios'          => $listaUsuarios,
    'roles_disponibles' => ['anonimo', 'usuario', 'moderador', 'gestor', 'superusuario']
]);