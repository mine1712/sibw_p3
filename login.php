<?php
session_start();
require_once "vendor/autoload.php";
include("includes/bd.php");

$mysqli = conectarBD();
$twig = new \Twig\Environment(new \Twig\Loader\FilesystemLoader('templates'));

// Si ya tiene sesión activa, no necesita loguearse
if (isset($_SESSION['login'])) {
    header("Location: acceso.php");
    exit();
}

$modo = $_GET['modo'] ?? 'login';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $pass  = $_POST['password'];

    if ($modo === 'login') {
        $stmt = $mysqli->prepare("SELECT id, password, rol FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($pass, $user['password'])) {
            // "Llenamos la mochila"
            $_SESSION['login']   = true;
            $_SESSION['id_user'] = (int)$user['id'];
            $_SESSION['user']    = $email;
            $_SESSION['rol']     = $user['rol'];
            
            setcookie("usuario_recuerdame", $user['id'], [
                'expires' => time() + (30 * 24 * 60 * 60),
                'path' => '/',
                'httponly' => true
            ]);

            header("Location: acceso.php");
            exit();
        }
        $error = "Email o contraseña incorrectos.";
    } else {
        // Lógica de registro
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("INSERT INTO usuarios (email, password, rol) VALUES (?, ?, 'usuario')");
        if ($stmt->execute([$email, $hash])) {
            header("Location: login.php?modo=login&success=1");
            exit();
        } else {
            $error = ($mysqli->errno === 1062) ? "Este email ya está registrado." : "Error al registrar.";
        }
    }
}

echo $twig->render('registro.twig', ['modo' => $modo, 'error' => $error]);