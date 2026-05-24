<?php
session_start();
require_once "vendor/autoload.php"; 
include("includes/bd.php");

// Seguridad: Solo gestores o administradores
if (!isset($_SESSION['login']) || !isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['gestor', 'administrador'])) {
    header("Location: login.php");
    exit();
}

$mysqli = conectarBD();
$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);

$id_noticia = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : null;

/* --- LÓGICA DE BORRADO Y GALERÍA --- */
if (isset($_GET['accion']) && $_GET['accion'] === 'borrar' && isset($_GET['id'])) {
    $id_borrar = intval($_GET['id']);
    
    // 1. Borrar imágenes físicas
    $res = $mysqli->query("SELECT ruta_archivo FROM imagenes WHERE id_noticia = $id_borrar");
    while ($row = $res->fetch_assoc()) {
        $ruta = "img/" . $row['ruta_archivo'];
        if (file_exists($ruta)) @unlink($ruta);
    }
    
    // 2. Borrar datos de BD
    $mysqli->query("DELETE FROM imagenes WHERE id_noticia = $id_borrar");
    $mysqli->query("DELETE FROM comentarios WHERE id_noticia = $id_borrar");
    $mysqli->query("DELETE FROM noticia WHERE id = $id_borrar");
    
    header("Location: gestion_noticia.php");
    exit();
}

if (isset($_GET['hacer_principal'])) {
    $id_img = intval($_GET['hacer_principal']);
    $res = $mysqli->query("SELECT id_noticia FROM imagenes WHERE id = $id_img")->fetch_assoc();
    if ($res) {
        $id_n = $res['id_noticia'];
        $mysqli->query("UPDATE imagenes SET principal = 0 WHERE id_noticia = $id_n");
        $mysqli->query("UPDATE imagenes SET principal = 1 WHERE id = $id_img");
        header("Location: gestion_noticia.php?id=$id_n#formulario");
        exit();
    }
}

if (isset($_GET['borrar_img']) && $id_noticia) {
    $id_img = intval($_GET['borrar_img']);
    $mysqli->query("DELETE FROM imagenes WHERE id = $id_img");
    header("Location: gestion_noticia.php?id=$id_noticia#formulario");
    exit();
}

/* --- PROCESAR FORMULARIO (POST) --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_noticia = !empty($_POST['id']) ? intval($_POST['id']) : null;
    
    $titulo = $_POST['titulo'] ?? '';
    $fecha = $_POST['fecha'] ?? date('Y-m-d H:i:s');
    $tipo = $_POST['tipo'] ?? '';
    $concejalia = $_POST['concejalia'] ?? '';
    $personas = $_POST['personas'] ?? '';
    $lugar_id = !empty($_POST['lugar_id']) ? intval($_POST['lugar_id']) : null;
    $descripcion = $_POST['descripcion'] ?? '';
    $gravedad = $_POST['gravedad'] ?? 'Normal';

    // Guardar/Actualizar noticia
    if ($id_noticia) {
        $stmt = $mysqli->prepare("UPDATE noticia SET titulo=?, fecha=?, tipo=?, concejalia=?, personas=?, lugar_id=?, descripcion=?, gravedad=? WHERE id=?");
        $stmt->bind_param("sssssissi", $titulo, $fecha, $tipo, $concejalia, $personas, $lugar_id, $descripcion, $gravedad, $id_noticia);
        $stmt->execute();
    } else {
        $stmt = $mysqli->prepare("INSERT INTO noticia (titulo, fecha, tipo, concejalia, personas, lugar_id, descripcion, gravedad) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssiss", $titulo, $fecha, $tipo, $concejalia, $personas, $lugar_id, $descripcion, $gravedad);
        $stmt->execute();
        $id_noticia = $stmt->insert_id;
    }

    // Gestionar Hashtags
    if ($id_noticia) {
        $mysqli->query("DELETE FROM noticia_hashtag WHERE id_noticia = $id_noticia");
        
        if (!empty($_POST['tags_existentes'])) {
            $stmt = $mysqli->prepare("INSERT INTO noticia_hashtag (id_noticia, id_hashtag) VALUES (?, ?)");
            foreach ($_POST['tags_existentes'] as $id_tag) {
                $stmt->bind_param("ii", $id_noticia, $id_tag);
                $stmt->execute();
            }
        }
        
        if (!empty($_POST['nuevos_tags'])) {
            $lista = explode(',', $_POST['nuevos_tags']);
            foreach ($lista as $nombre) {
                $nombre = trim($nombre);
                if (empty($nombre)) continue;
                
                
                $mysqli->query("INSERT IGNORE INTO hashtags (nombre) VALUES ('$nombre')");
                $id_tag = $mysqli->query("SELECT id FROM hashtags WHERE nombre = '$nombre'")->fetch_assoc()['id'];
                
                $mysqli->query("INSERT IGNORE INTO noticia_hashtag (id_noticia, id_hashtag) VALUES ($id_noticia, $id_tag)");
            }
        }
    }

    // Gestionar Imágenes
    if ($id_noticia && isset($_POST['desc_imgs'])) {
        foreach ($_POST['desc_imgs'] as $img_id => $texto) {
            $stmtI = $mysqli->prepare("UPDATE imagenes SET descripcion_alt = ? WHERE id = ?");
            $stmtI->bind_param("si", $texto, $img_id);
            $stmtI->execute();
        }
    }
    
    $nueva_img_ruta = trim($_POST['nueva_imagen_ruta'] ?? '');
    if ($id_noticia && !empty($nueva_img_ruta)) {
        $res_check = $mysqli->query("SELECT COUNT(*) as total FROM imagenes WHERE id_noticia = $id_noticia")->fetch_assoc();
        $principal = ($res_check['total'] == 0) ? 1 : 0;
        $stmt_img = $mysqli->prepare("INSERT INTO imagenes (id_noticia, ruta_archivo, principal) VALUES (?, ?, ?)");
        $stmt_img->bind_param("isi", $id_noticia, $nueva_img_ruta, $principal);
        $stmt_img->execute();
    }

    header("Location: gestion_noticia.php" . ($id_noticia ? "?id=$id_noticia" : ""));
    exit();
}

/* --- CONSULTAS DE LECTURA --- */
$busqueda = $_GET['q'] ?? '';
$campo = $_GET['campo'] ?? 'titulo';

$todosLosTags = $mysqli->query("SELECT * FROM hashtags ORDER BY nombre ASC")->fetch_all(MYSQLI_ASSOC);
$tagsDeLaNoticia = [];
$noticia = null;
$imagenes = [];

if ($campo === 'hashtag' && !empty($busqueda)) {
    $like = "%$busqueda%";
    $stmt = $mysqli->prepare("SELECT n.id, n.titulo, n.fecha FROM noticia n 
                              JOIN noticia_hashtag nh ON n.id = nh.id_noticia 
                              JOIN hashtags h ON nh.id_hashtag = h.id 
                              WHERE h.nombre LIKE ? ORDER BY n.fecha DESC");
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $noticias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $noticias = $mysqli->query("SELECT id, titulo, fecha FROM noticia ORDER BY fecha DESC")->fetch_all(MYSQLI_ASSOC);
}

if ($id_noticia) {
    $noticia = $mysqli->query("SELECT * FROM noticia WHERE id = $id_noticia")->fetch_assoc();
    $imagenes = $mysqli->query("SELECT * FROM imagenes WHERE id_noticia = $id_noticia ORDER BY principal DESC")->fetch_all(MYSQLI_ASSOC);
    
    $res = $mysqli->prepare("SELECT id_hashtag FROM noticia_hashtag WHERE id_noticia = ?");
    $res->bind_param("i", $id_noticia);
    $res->execute();
    $resultado = $res->get_result();
    while ($fila = $resultado->fetch_row()) {
        $tagsDeLaNoticia[] = $fila[0];
    }
    $res->close();
}

echo $twig->render('gestion_noticia.twig', [
    'session'          => $_SESSION,
    'noticias'         => $noticias,
    'noticia'          => $noticia,
    'imagenes'         => $imagenes,
    'busqueda'         => $busqueda,
    'campo'            => $campo,
    'todosLosTags'     => $todosLosTags,
    'tagsDeLaNoticia'  => $tagsDeLaNoticia
]);