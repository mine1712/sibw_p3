<?php
require_once 'vendor/autoload.php';
require_once 'includes/bd.php';

$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);


$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header("Location: portada.php");
    exit;
}

$conexion = conectarBD();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = strip_tags(trim($_POST['nombre'] ?? ''));
    $email  = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $texto  = strip_tags(trim($_POST['comentario'] ?? ''));

    if (!empty($nombre) && !empty($texto)) {
        try {
            
            $sentencia = $conexion->prepare("INSERT INTO comentarios (id_noticia, nombre, email, texto, fecha) VALUES (?, ?, ?, ?, NOW())");
            $sentencia->bind_param("isss", $id, $nombre, $email, $texto);
            $sentencia->execute();
            $sentencia->close();
            
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success']);
            exit;
        } catch (Exception $e) {
            error_log($e->getMessage());
            exit;
        }
    }
}


try {
    $sentencia = $conexion->prepare("SELECT * FROM noticia WHERE id = ?");
    $sentencia->bind_param("i", $id);
    $sentencia->execute();
    $noticia = $sentencia->get_result()->fetch_assoc();
    $sentencia->close();

    if (!$noticia) { throw new Exception("Noticia no encontrada");}

    $sentenciaImg = $conexion->prepare("SELECT ruta_archivo FROM imagenes WHERE id_noticia = ?");
    $sentenciaImg->bind_param("i", $id);
    $sentenciaImg->execute();
    $imagenes = $sentenciaImg->get_result()->fetch_all(MYSQLI_ASSOC);
    $sentenciaImg->close();

    $comentarios = $conexion->query("SELECT * FROM comentarios WHERE id_noticia = $id ORDER BY fecha DESC")->fetch_all(MYSQLI_ASSOC);
    $lugares = $conexion->query("SELECT nombre FROM lugar")->fetch_all(MYSQLI_ASSOC);

    $conexion->close();

} catch (Exception $e) {
    echo "Fallo en la conexión";
    exit;
}

echo $twig->render('noticia.twig', [
    'noticia'     => $noticia,
    'imagenes'    => $imagenes,
    'comentarios' => $comentarios,
    'lugares'     => $lugares
]);