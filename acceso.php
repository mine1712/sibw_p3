<?php
session_start();
require_once "vendor/autoload.php";

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit();
}

$twig = new \Twig\Environment(new \Twig\Loader\FilesystemLoader('templates'));

echo $twig->render('acceso.twig', [
    'session' => $_SESSION
]);