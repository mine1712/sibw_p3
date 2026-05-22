<?php
session_start();
require_once "vendor/autoload.php";
include("includes/bd.php");

if (!isset($_SESSION['login']) || !in_array($_SESSION['rol'], ['moderador', 'administrador'])) {
    header("Location: acceso.php");
    exit();
}

$mysqli = conectarBD();
$twig = new \Twig\Environment(new \Twig\Loader\FilesystemLoader('templates'));


$accion = $_GET['accion'] ?? null;

// borrar
if ($accion === 'borrar' && isset($_GET['id'])) {
    $stmt = $mysqli->prepare("DELETE FROM comentarios WHERE id = ?");
    $stmt->bind_param("i", $_GET['id']);
    $stmt->execute();
    header("Location: gestion_comentarios.php");
    exit();
}

// editar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_edit'])) {
    $texto = $_POST['texto'] . (strpos($_POST['texto'], "(Mensaje editado)") === false ? "\n\n(Mensaje editado por el moderador)" : "");
    $stmt = $mysqli->prepare("UPDATE comentarios SET texto = ? WHERE id = ?");
    $stmt->bind_param("si", $texto, $_POST['id_edit']);
    $stmt->execute();
    header("Location: gestion_comentarios.php");
    exit();
}

// insertar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'insertar') {
    $stmt = $mysqli->prepare("INSERT INTO comentarios (id_noticia, nombre, email, texto, fecha) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("isss", $_POST['id_noticia'], $_POST['nombre'], $_POST['email'], $_POST['texto']);
    $stmt->execute();
    header("Location: gestion_comentarios.php");
    exit();
}

// listado
$busqueda = $_GET['q'] ?? '';
$sql = "SELECT c.*, n.titulo as noticia_titulo FROM comentarios c JOIN noticia n ON c.id_noticia = n.id";
if ($busqueda) $sql .= " WHERE c.texto LIKE ? OR c.nombre LIKE ? OR n.titulo LIKE ?";
$sql .= " ORDER BY c.fecha DESC";

$stmt = $mysqli->prepare($sql);
if ($busqueda) {
    $like = "%$busqueda%";
    $stmt->bind_param("sss", $like, $like, $like);
}
$stmt->execute();
$comentarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);


echo $twig->render('gestion_comentarios.twig', [
    'comentarios' => $comentarios,
    'busqueda' => $busqueda,
    'session' => $_SESSION,
    'noticias_list' => $mysqli->query("SELECT id, titulo FROM noticia ORDER BY fecha DESC")->fetch_all(MYSQLI_ASSOC)
]);