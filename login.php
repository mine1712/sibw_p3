<?php
session_start();
require_once "vendor/autoload.php";
include("includes/bd.php");

$mysqli = conectarBD();
$twig = new \Twig\Environment(new \Twig\Loader\FilesystemLoader('templates'));

// Si el usuario ya está logueado, lo mandamos al perfil directamente
if (isset($_SESSION['login']) && isset($_SESSION['id_user']) && !isset($_GET['modo'])) {
    header("Location: perfil.php");
    exit();
}

$modo = $_GET['modo'] ?? 'login'; // 'login' por defecto, o 'registro'
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $pass  = $_POST['password'];

    if ($modo === 'login') {
        // --- LÓGICA DE INICIO DE SESIÓN ---
        // CRUCIAL: Seleccionamos también el ID del usuario
        $stmt = $mysqli->prepare("SELECT id, password, rol FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($pass, $user['password'])) {
            // 1. Guardamos TODOS los datos necesarios en la sesión (incluido el id_user)
            $_SESSION['login']   = true;
            $_SESSION['id_user'] = (int)$user['id']; // <-- Esto te faltaba y por eso te echaba
            $_SESSION['user']    = $email;
            $_SESSION['rol']     = $user['rol'];
            
            // 2. Creamos la cookie persistente para que no te vuelva a pedir login en 30 días
            // El "/" final es vital para que funcione en toda la web
            setcookie("usuario_recuerdame", $user['id'], time() + (30 * 24 * 60 * 60), "/");

            // 3. Redirigimos a perfil.php (tu nuevo Panel de Control)
            header("Location: perfil.php");
            exit();
        } else {
            $error = "Email o contraseña incorrectos.";
        }
    } else {
        // --- LÓGICA DE REGISTRO NUEVO ---
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        try {
            $stmt = $mysqli->prepare("INSERT INTO usuarios (email, password, rol) VALUES (?, ?, 'usuario')");
            $stmt->bind_param("ss", $email, $hash);
            if ($stmt->execute()) {
                // Registro éxito: lo mandamos al login para que entre
                header("Location: login.php?modo=login&success=1");
                exit();
            }
        } catch (mysqli_sql_exception $e) {
            $error = ($e->getCode() === 1062) ? "Este correo ya está registrado." : "Error en el sistema.";
        }
    }
}

echo $twig->render('registro.twig', [
    'modo'    => $modo,
    'error'   => $error,
    'session' => $_SESSION
]);