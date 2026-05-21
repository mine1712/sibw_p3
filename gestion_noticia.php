<?php
// 1. CONTROL DE SESIÓN ESTRICTO
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

// Recuperamos ID si viene por GET/POST (para editar, cambiar portadas, etc.)
$id_noticia = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : null;


/* --- ACCIÓN 1: BORRAR NOTICIA --- */
if (isset($_GET['accion']) && $_GET['accion'] === 'borrar' && isset($_GET['id'])) {
    $id_borrar = intval($_GET['id']);
    
    // 1. Borrar archivos físicos de la carpeta img/
    $res = $mysqli->query("SELECT ruta_archivo FROM imagenes WHERE id_noticia = $id_borrar");
    while ($row = $res->fetch_assoc()) {
        $ruta = "img/" . $row['ruta_archivo'];
        if (file_exists($ruta)) {
            @unlink($ruta);
        }
    }
    
    // 2. Borrar de la BD
    $mysqli->query("DELETE FROM imagenes WHERE id_noticia = $id_borrar");
    $mysqli->query("DELETE FROM comentarios WHERE id_noticia = $id_borrar");
    $mysqli->query("DELETE FROM noticia WHERE id = $id_borrar");
    
    header("Location: gestion_noticia.php");
    exit();
}


/* --- ACCIÓN 2: CAMBIAR PORTADA --- */
if (isset($_GET['hacer_principal'])) {
    $id_img = intval($_GET['hacer_principal']);
    if (!$id_noticia) {
        $stmt_c = $mysqli->prepare("SELECT id_noticia FROM imagenes WHERE id = ?");
        $stmt_c->bind_param("i", $id_img);
        $stmt_c->execute();
        $res = $stmt_c->get_result();
        if ($row = $res->fetch_assoc()) {
            $id_noticia = $row['id_noticia'];
        }
        $stmt_c->close();
    }
    if ($id_noticia) {
        $mysqli->query("UPDATE imagenes SET principal = 0 WHERE id_noticia = $id_noticia");
        $mysqli->query("UPDATE imagenes SET principal = 1 WHERE id = $id_img");
        header("Location: gestion_noticia.php?id=$id_noticia");
        exit();
    }
}


/* --- ACCIÓN 3: BORRAR IMAGEN SUELTA --- */
if (isset($_GET['borrar_img']) && $id_noticia) {
    $id_img = intval($_GET['borrar_img']);
    $mysqli->query("DELETE FROM imagenes WHERE id = $id_img");
    header("Location: gestion_noticia.php?id=$id_noticia");
    exit();
}


/* --- ACCIÓN 4: PROCESAR FORMULARIO (GUARDAR / ACTUALIZAR) --- */
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
    
    $hashtags_raw = $_POST['hashtags'] ?? '';
    $tags_procesados = [];
    if (!empty($hashtags_raw)) {
        $lista_raw = explode(',', $hashtags_raw);
        foreach ($lista_raw as $item) {
            $item = trim($item);
            if (!empty($item)) {
                if (strpos($item, '#') !== 0) $item = '#' . $item;
                $tags_procesados[] = $item;
            }
        }
    }
    $hashtags_final = implode(' ', $tags_procesados);

    if ($id_noticia) {
        $sql = "UPDATE noticia SET titulo=?, fecha=?, tipo=?, concejalia=?, personas=?, lugar_id=?, descripcion=?, gravedad=?, hashtags=? WHERE id=?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("sssssisssi", $titulo, $fecha, $tipo, $concejalia, $personas, $lugar_id, $descripcion, $gravedad, $hashtags_final, $id_noticia);
        $stmt->execute();
        $stmt->close();
    } else {
        $sql = "INSERT INTO noticia (titulo, fecha, tipo, concejalia, personas, lugar_id, descripcion, gravedad, hashtags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("sssssisss", $titulo, $fecha, $tipo, $concejalia, $personas, $lugar_id, $descripcion, $gravedad, $hashtags_final);
        $stmt->execute();
        $id_noticia = $stmt->insert_id;
        $stmt->close();
    }

    foreach ($tags_procesados as $single_tag) {
        $stmt_tag = $mysqli->prepare("INSERT IGNORE INTO hashtags (nombre) VALUES (?)");
        $stmt_tag->bind_param("s", $single_tag);
        $stmt_tag->execute();
        $stmt_tag->close();
    }

    if ($id_noticia && isset($_POST['desc_imgs'])) {
        foreach ($_POST['desc_imgs'] as $img_id => $texto) {
            $stmtI = $mysqli->prepare("UPDATE imagenes SET descripcion_alt = ? WHERE id = ?");
            $stmtI->bind_param("si", $texto, $img_id);
            $stmtI->execute();
            $stmtI->close();
        }
    }

    $nueva_img_ruta = trim($_POST['nueva_imagen_ruta'] ?? '');
    if ($id_noticia && !empty($nueva_img_ruta)) {
        $res_check = $mysqli->query("SELECT COUNT(*) as total FROM imagenes WHERE id_noticia = $id_noticia")->fetch_assoc();
        $principal = ($res_check['total'] == 0) ? 1 : 0;

        $stmt_img = $mysqli->prepare("INSERT INTO imagenes (id_noticia, ruta_archivo, principal) VALUES (?, ?, ?)");
        $stmt_img->bind_param("isi", $id_noticia, $nueva_img_ruta, $principal);
        $stmt_img->execute();
        $stmt_img->close();
    }

    header("Location: gestion_noticia.php");
    exit();
}


/* --- RECOPILACIÓN PARA LA VISTA (EL BUSCADOR TRABAJA AQUÍ) --- */
$busqueda = $_GET['q'] ?? '';
$campo = $_GET['campo'] ?? 'titulo';
$noticias = [];

if (!empty($busqueda)) {
    $like = "%$busqueda%";
    if ($campo === 'hashtag') {
        $stmt = $mysqli->prepare("SELECT id, titulo, fecha FROM noticia WHERE hashtags LIKE ? ORDER BY fecha DESC");
    } elseif ($campo === 'descripcion') {
        $stmt = $mysqli->prepare("SELECT id, titulo, fecha FROM noticia WHERE descripcion LIKE ? ORDER BY fecha DESC");
    } else {
        $stmt = $mysqli->prepare("SELECT id, titulo, fecha FROM noticia WHERE titulo LIKE ? ORDER BY fecha DESC");
    }
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $noticias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $noticias = $mysqli->query("SELECT id, titulo, fecha FROM noticia ORDER BY fecha DESC")->fetch_all(MYSQLI_ASSOC);
}

// Datos de la noticia seleccionada para editar (coincidiendo con las variables cortas de tu twig)
$noticia = null;
$imagenes = [];
if ($id_noticia) {
    $noticia = $mysqli->query("SELECT * FROM noticia WHERE id = $id_noticia")->fetch_assoc();
    $imagenes = $mysqli->query("SELECT * FROM imagenes WHERE id_noticia = $id_noticia ORDER BY principal DESC")->fetch_all(MYSQLI_ASSOC);
}

echo $twig->render('gestion_noticia.twig', [
    'session'  => $_SESSION,
    'noticias' => $noticias, 
    'noticia'  => $noticia,   // Rellena la estructura {{ noticia.campo }}
    'imagenes' => $imagenes,  // Rellena la estructura {% for img in imagenes %}
    'busqueda' => $busqueda,
    'campo'    => $campo
]);