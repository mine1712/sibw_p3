<?php
require_once 'vendor/autoload.php';
require_once 'includes/bd.php';

$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$noticia = null;
$imagenes = [];

try {
    if ($id <= 0) {
        throw new Exception("ID de noticia no válido.");
    }

    $conexion = conectarBD();

    $sentencia = $conexion->prepare("SELECT * FROM noticia WHERE id = ?");
    $sentencia->bind_param("i", $id);
    $sentencia->execute();
    $noticia = $sentencia->get_result()->fetch_assoc();

    if (!$noticia) {
        throw new Exception("La noticia con ID $id no existe en nuestra base de datos.");
    }

    $sentenciaImg = $conexion->prepare("SELECT ruta_archivo FROM imagenes WHERE id_noticia = ?");
    $sentenciaImg->bind_param("i", $id);
    $sentenciaImg->execute();
    $imagenes = $sentenciaImg->get_result()->fetch_all(MYSQLI_ASSOC);

    $sentencia->close();
    $sentenciaImg->close();
    $conexion->close();

} catch (Exception $e) {
   error_log("Error en noticia_imprimir: " . $e->getMessage());
}


echo $twig->render('noticia_imprimir.twig', [
    'noticia'  => $noticia,
    'imagenes' => $imagenes
]);