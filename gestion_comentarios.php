<?php
session_start();
require_once "vendor/autoload.php";
include("includes/bd.php");

// Seguridad: solo moderadores o superusuarios
if (!isset($_SESSION['login']) || !in_array($_SESSION['rol'], ['moderador', 'superusuario'])) {
    header("Location: acceso.php");
    exit();
}

$mysqli = conectarBD();
$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);

$accion = $_GET['accion'] ?? null;
$id_comentario = isset($_GET['id']) ? intval($_GET['id']) : null;

// --- ACCIÓN: BORRAR ---
if ($accion === 'borrar' && $id_comentario) {
    $mysqli->query("DELETE FROM comentarios WHERE id = $id_comentario");
    header("Location: gestion_comentarios.php");
    exit();
}

// --- ACCIÓN: GUARDAR EDICIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_edit'])) {
    $id_edit = intval($_POST['id_edit']);
    $texto = $_POST['texto']; // El texto ya viene capitalizado por el JS del navegador
    
    $nuevo_texto = $texto . "\n\n(Mensaje editado por el moderador)";
    
    $stmt = $mysqli->prepare("UPDATE comentarios SET texto = ? WHERE id = ?");
    $stmt->bind_param("si", $nuevo_texto, $id_edit);
    $stmt->execute();
    
    header("Location: gestion_comentarios.php");
    exit();
}

// --- LÓGICA DE LISTADO Y BÚSQUEDA ---
$busqueda = $_GET['q'] ?? '';
$sql = "SELECT c.*, n.titulo as noticia_titulo 
        FROM comentarios c 
        JOIN noticia n ON c.id_noticia = n.id";

if (!empty($busqueda)) {
    $sql .= " WHERE c.texto LIKE '%$busqueda%' OR c.nombre LIKE '%$busqueda%'";
}
$sql .= " ORDER BY c.fecha DESC";

$res = $mysqli->query($sql);
$comentarios = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$comentario_editar = null;
if ($accion === 'editar' && $id_comentario) {
    $res_edit = $mysqli->query("SELECT * FROM comentarios WHERE id = $id_comentario");
    $comentario_editar = $res_edit->fetch_assoc();
}

echo $twig->render('gestion_comentarios.twig', [
    'comentarios' => $comentarios,
    'busqueda' => $busqueda,
    'session' => $_SESSION,
    'comentario_editar' => $comentario_editar
]);