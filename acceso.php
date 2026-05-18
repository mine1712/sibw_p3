<?php
session_start();
require_once "vendor/autoload.php";
include("includes/bd.php");

// 1. SEGURIDAD: Solo usuarios logueados
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit();
}

$mysqli = conectarBD();
$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);

// 2. LÓGICA DE ACCIONES (Borrar noticia)
if (isset($_GET['accion']) && $_GET['accion'] === 'borrar' && isset($_GET['id'])) {
    $id_borrar = intval($_GET['id']);
    
    // Primero buscamos las imágenes para borrarlas de la carpeta img/
    $res = $mysqli->query("SELECT ruta_archivo FROM imagenes WHERE id_noticia = $id_borrar");
    while ($row = $res->fetch_assoc()) {
        $ruta = "img/" . $row['ruta_archivo'];
        if (file_exists($ruta)) {
            @unlink($ruta);
        }
    }
    
    // Borramos de la BD (primero imágenes por la clave foránea, luego noticia)
    $mysqli->query("DELETE FROM imagenes WHERE id_noticia = $id_borrar");
    $mysqli->query("DELETE FROM noticia WHERE id = $id_borrar");
    
    header("Location: acceso.php?vista=noticias");
    exit();
}

// 3. LÓGICA DE VISTA Y BUSCADOR
$vista = $_GET['vista'] ?? 'menu';
$noticias = [];
$busqueda = $_GET['q'] ?? '';
$campo = $_GET['campo'] ?? 'titulo';

// Si el usuario es gestor o superusuario, cargamos el listado
if ($vista === 'noticias' && in_array($_SESSION['rol'], ['gestor', 'superusuario'])) {
    if (!empty($busqueda)) {
        $like = "%$busqueda%";
        // Validamos que el campo sea seguro para la consulta
        $columna = ($campo === 'descripcion') ? 'descripcion' : 'titulo';
        
        $stmt = $mysqli->prepare("SELECT id, titulo, fecha FROM noticia WHERE $columna LIKE ? ORDER BY fecha DESC");
        $stmt->bind_param("s", $like);
        $stmt->execute();
        $noticias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        // Listado general sin búsqueda
        $noticias = $mysqli->query("SELECT id, titulo, fecha FROM noticia ORDER BY fecha DESC")->fetch_all(MYSQLI_ASSOC);
    }
}

// 4. RENDERIZADO
echo $twig->render('acceso.twig', [
    'session'  => $_SESSION,
    'noticias' => $noticias,
    'vista'    => $vista,
    'busqueda' => $busqueda,
    'campo'    => $campo
]);