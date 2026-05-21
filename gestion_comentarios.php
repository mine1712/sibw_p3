<?php
session_start();
require_once "vendor/autoload.php";
include("includes/bd.php");

// Seguridad: solo moderadores o administradores
if (!isset($_SESSION['login']) || !in_array($_SESSION['rol'], ['moderador', 'administrador'])) {
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
    if (!in_array($_SESSION['rol'], ['moderador', 'administrador'])) {
        die("No autorizado");
    }
    $mysqli->query("DELETE FROM comentarios WHERE id = $id_comentario");
    header("Location: gestion_comentarios.php");
    exit();
}

// --- ACCIÓN: GUARDAR EDICIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_edit'])) {
    if (!in_array($_SESSION['rol'], ['moderador', 'administrador'])) {
        die("No autorizado");
    }
    $id_edit = intval($_POST['id_edit']);
    $texto = $_POST['texto'];
    
    $nuevo_texto = $texto;
    if (strpos($texto, "(Mensaje editado por el moderador)") === false) {
        $nuevo_texto = $texto . "\n\n(Mensaje editado por el moderador)";
    }
    
    $stmt = $mysqli->prepare("UPDATE comentarios SET texto = ? WHERE id = ?");
    $stmt->bind_param("si", $nuevo_texto, $id_edit);
    $stmt->execute();
    $stmt->close();
    
    header("Location: gestion_comentarios.php");
    exit();
}

// --- ACCIÓN: AÑADIR/INSERTAR COMENTARIO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'insertar') {
    if (!in_array($_SESSION['rol'], ['moderador', 'administrador'])) {
        die("No autorizado");
    }
    $id_noticia = intval($_POST['id_noticia']);
    $nombre = strip_tags(trim($_POST['nombre'] ?? ''));
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $texto = strip_tags(trim($_POST['texto'] ?? ''));

    if ($id_noticia && !empty($nombre) && !empty($texto)) {
        $stmt = $mysqli->prepare("INSERT INTO comentarios (id_noticia, nombre, email, texto, fecha) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("isss", $id_noticia, $nombre, $email, $texto);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: gestion_comentarios.php");
    exit();
}

// --- SOPORTE BÚSQUEDA DINÁMICA (FETCH) ---
if (isset($_GET['ajax_search'])) {
    $busqueda = $_GET['q'] ?? '';
    $sql = "SELECT c.*, n.titulo as noticia_titulo 
            FROM comentarios c 
            JOIN noticia n ON c.id_noticia = n.id";
    
    $params = [];
    $types = "";
    
    if (!empty($busqueda)) {
        $sql .= " WHERE c.texto LIKE ? OR c.nombre LIKE ? OR n.titulo LIKE ?";
        $like_str = "%" . $busqueda . "%";
        $params = [$like_str, $like_str, $like_str];
        $types = "sss";
    }
    $sql .= " ORDER BY c.fecha DESC";
    
    $stmt = $mysqli->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $comentarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    header('Content-Type: application/json');
    echo json_encode($comentarios);
    exit();
}

// --- LÓGICA DE LISTADO Y BÚSQUEDA NORMAL ---
$busqueda = $_GET['q'] ?? '';
$sql = "SELECT c.*, n.titulo as noticia_titulo 
        FROM comentarios c 
        JOIN noticia n ON c.id_noticia = n.id";

$params = [];
$types = "";
if (!empty($busqueda)) {
    $sql .= " WHERE c.texto LIKE ? OR c.nombre LIKE ? OR n.titulo LIKE ?";
    $like_str = "%" . $busqueda . "%";
    $params = [$like_str, $like_str, $like_str];
    $types = "sss";
}
$sql .= " ORDER BY c.fecha DESC";

$stmt = $mysqli->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$comentarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$comentario_editar = null;
if ($accion === 'editar' && $id_comentario) {
    $res_edit = $mysqli->query("SELECT * FROM comentarios WHERE id = $id_comentario");
    $comentario_editar = $res_edit->fetch_assoc();
}

// Obtener todas las noticias para el selector de "Añadir Comentario"
$res_noticias = $mysqli->query("SELECT id, titulo FROM noticia ORDER BY fecha DESC");
$noticias_list = $res_noticias ? $res_noticias->fetch_all(MYSQLI_ASSOC) : [];

echo $twig->render('gestion_comentarios.twig', [
    'comentarios' => $comentarios,
    'busqueda' => $busqueda,
    'session' => $_SESSION,
    'comentario_editar' => $comentario_editar,
    'noticias_list' => $noticias_list
]);