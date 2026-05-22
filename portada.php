<?php
session_start(); // Imprescindible para el menú de gestión
require_once 'vendor/autoload.php';
require_once 'includes/bd.php';

$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);
$conexion = conectarBD();

// Consulta que trae la noticia y su imagen marcada como principal
$query = "SELECT n.id, n.titulo, i.ruta_archivo AS imagen_principal 
          FROM noticia n 
          LEFT JOIN imagenes i ON n.id = i.id_noticia AND i.principal = 1 
          ORDER BY n.fecha DESC";

$resultado = $conexion->query($query);
$noticias = $resultado->fetch_all(MYSQLI_ASSOC);

echo $twig->render('portada.twig', [
    'noticias' => $noticias,
    'session'  => $_SESSION 
]);