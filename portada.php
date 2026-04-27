<?php
require_once 'vendor/autoload.php';
require_once 'includes/bd.php';

$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);
$conexion = conectarBD();


$query = "SELECT n.id, n.titulo, 
          (SELECT ruta_archivo FROM imagenes WHERE id_noticia = n.id LIMIT 1) AS imagen_principal 
          FROM noticia n 
          ORDER BY n.fecha DESC";

$resultado = $conexion->query($query);
$noticias = $resultado->fetch_all(MYSQLI_ASSOC);

echo $twig->render('portada.twig', [
    'noticias' => $noticias
]);