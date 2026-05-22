<?php
session_start(); 

require_once 'vendor/autoload.php';
require_once 'includes/bd.php';

$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id && !isset($_GET['ajax_lugares']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: portada.php");
    exit;
}

$conexion = conectarBD();

if (isset($_GET['ajax_lugares'])) {
    $lugares = $conexion->query("SELECT nombre FROM lugar")->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($lugares);
    exit; 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_noticia = filter_input(INPUT_POST, 'id_noticia', FILTER_VALIDATE_INT);
    $nombre = strip_tags(trim($_POST['nombre'] ?? ''));
    $email  = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $texto  = strip_tags(trim($_POST['comentario'] ?? ''));

    if ($id_noticia && !empty($nombre) && !empty($texto)) {
        try {
            $sentencia = $conexion->prepare("INSERT INTO comentarios (id_noticia, nombre, email, texto, fecha) VALUES (?, ?, ?, ?, NOW())");
            $sentencia->bind_param("isss", $id_noticia, $nombre, $email, $texto);
            $sentencia->execute();
            $sentencia->close();
            
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success']);
            exit; 
        } catch (Exception $e) {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['status' => 'error', 'message' => 'Error en la base de datos']);
            exit;
        }
    }
}

try {
    $sentencia = $conexion->prepare("
        SELECT n.*, l.nombre AS nombre_lugar 
        FROM noticia n 
        LEFT JOIN lugar l ON n.lugar_id = l.id 
        WHERE n.id = ?
    ");
    $sentencia->bind_param("i", $id);
    $sentencia->execute();
    $noticia = $sentencia->get_result()->fetch_assoc();
    $sentencia->close();

    if (!$noticia) { 
        throw new Exception("Noticia no encontrada");
    }

    $sentenciaImg = $conexion->prepare("SELECT ruta_archivo FROM imagenes WHERE id_noticia = ?");
    $sentenciaImg->bind_param("i", $id);
    $sentenciaImg->execute();
    $imagenes = $sentenciaImg->get_result()->fetch_all(MYSQLI_ASSOC);
    $sentenciaImg->close();

    $sentenciaCom = $conexion->prepare("SELECT * FROM comentarios WHERE id_noticia = ? ORDER BY fecha DESC");
    $sentenciaCom->bind_param("i", $id);
    $sentenciaCom->execute();
    $comentarios = $sentenciaCom->get_result()->fetch_all(MYSQLI_ASSOC);
    $sentenciaCom->close();

    $conexion->close();

} catch (Exception $e) {
    echo "Fallo en la conexión: " . $e->getMessage();
    exit;
}

echo $twig->render('noticia.twig', [
    'noticia'     => $noticia,
    'imagenes'    => $imagenes,
    'comentarios' => $comentarios,
    'session'     => $_SESSION 
]);