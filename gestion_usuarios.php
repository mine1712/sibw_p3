<?php
session_start();
require_once "vendor/autoload.php";
include("includes/bd.php");

//Solo el Administrador gestiona esta tabla
if (!isset($_SESSION['login']) || $_SESSION['rol'] !== 'administrador') {
    header("Location: acceso.php"); // Si no es administrador, lo mandamos a su panel general
    exit();
}

$mysqli = conectarBD();
$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);

$mensaje = "";

// Acciones de Administración
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Cambiar Rol
    if (isset($_POST['accion']) && $_POST['accion'] === 'cambiar_rol') {
        $id_cambiar = intval($_POST['id_usuario']);
        $nuevo_rol = $_POST['nuevo_rol'];
        
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
            $mensaje = "Rol actualizado correctamente.";
        }
    }

    // Borrar Usuario
    if (isset($_POST['accion']) && $_POST['accion'] === 'borrar_usuario') {
        $id_borrar = intval($_POST['id_usuario']);
        // Evitamos que el admin se borre a sí mismo por error aquí
        if ($id_borrar !== $_SESSION['id_user']) {
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
                $mensaje = "Usuario eliminado.";
            }
        }
    }
}

// Obtener lista completa para la tabla
$res = $mysqli->query("SELECT id, email, rol FROM usuarios ORDER BY id ASC");
$listaUsuarios = $res->fetch_all(MYSQLI_ASSOC);

echo $twig->render('gestion_usuarios.twig', [
    'usuarios' => $listaUsuarios,
    'mensaje'  => $mensaje,
    'session'  => $_SESSION,
    'roles_disponibles' => ['anonimo', 'usuario', 'moderador', 'gestor', 'administrador']
]);