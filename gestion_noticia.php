<?php
// 1. CONTROL DE SESIÓN (Estricto en la línea 1)
session_start();
require_once "vendor/autoload.php"; 
include("includes/bd.php");

// CONTROL DE ACCESO ROBUSTO: Si no es gestor ni superusuario, patada hacia el login/perfil
if (!isset($_SESSION['login']) || !isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['gestor', 'superusuario'])) {
    header("Location: login.php");
    exit();
}

$mysqli = conectarBD();
$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);

$id_noticia = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : null;

/* --- ACCIÓN: CAMBIAR PORTADA --- */
if (isset($_GET['hacer_principal'])) {
    $id_img = intval($_GET['hacer_principal']);
    if (!$id_noticia) {
        // SQL Seguro con prepare
        $stmt_c = $mysqli->prepare("SELECT id_noticia FROM imagenes WHERE id = ?");
        $stmt_c->bind_param("i", $id_img);
        $stmt_c->execute();
        $res = $stmt_c->get_result();
        if ($row = $res->fetch_assoc()) $id_noticia = $row['id_noticia'];
    }
    if ($id_noticia) {
        $stmt_p1 = $mysqli->prepare("UPDATE imagenes SET principal = 0 WHERE id_noticia = ?");
        $stmt_p1->bind_param("i", $id_noticia);
        $stmt_p1->execute();

        $stmt_p2 = $mysqli->prepare("UPDATE imagenes SET principal = 1 WHERE id = ?");
        $stmt_p2->bind_param("i", $id_img);
        $stmt_p2->execute();

        header("Location: gestion_noticia.php?id=$id_noticia");
        exit();
    }
}

/* --- ACCIÓN: BORRAR IMAGEN --- */
if (isset($_GET['borrar_img']) && $id_noticia) {
    $id_img = intval($_GET['borrar_img']);
    
    $stmt_b1 = $mysqli->prepare("SELECT ruta_archivo FROM imagenes WHERE id = ?");
    $stmt_b1->bind_param("i", $id_img);
    $stmt_b1->execute();
    $res = $stmt_b1->get_result();
    
    if ($row = $res->fetch_assoc()) {
        if (file_exists("img/" . $row['ruta_archivo'])) {
            @unlink("img/" . $row['ruta_archivo']);
        }
    }
    
    $stmt_b2 = $mysqli->prepare("DELETE FROM imagenes WHERE id = ?");
    $stmt_b2->bind_param("i", $id_img);
    $stmt_b2->execute();

    header("Location: gestion_noticia.php?id=$id_noticia");
    exit();
}

/* --- ACCIÓN: GUARDAR TODO (POST) --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'] ?? '';
    $fecha = $_POST['fecha'] ?? date('Y-m-d H:i:s');
    $tipo = $_POST['tipo'] ?? '';
    $concejalia = $_POST['concejalia'] ?? '';
    $personas = $_POST['personas'] ?? '';
    $lugar_id = !empty($_POST['lugar_id']) ? intval($_POST['lugar_id']) : null;
    $descripcion = $_POST['descripcion'] ?? '';
    $gravedad = $_POST['gravedad'] ?? 'Normal';
    $hashtags = $_POST['hashtags'] ?? '';

    if ($id_noticia) {
        // Actualizar Tabla NOTICIA de forma segura
        $sql = "UPDATE noticia SET titulo=?, fecha=?, tipo=?, concejalia=?, personas=?, lugar_id=?, descripcion=?, gravedad=?, hashtags=? WHERE id=?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("sssssisssi", $titulo, $fecha, $tipo, $concejalia, $personas, $lugar_id, $descripcion, $gravedad, $hashtags, $id_noticia);
        $stmt->execute();

        // Actualizar Tabla IMAGENES (descripciones ALT)
        if (isset($_POST['desc_imgs'])) {
            foreach ($_POST['desc_imgs'] as $img_id => $texto) {
                $stmtI = $mysqli->prepare("UPDATE imagenes SET descripcion_alt = ? WHERE id = ?");
                $stmtI->bind_param("si", $texto, $img_id);
                $stmtI->execute();
            }
        }
    }

    // Subida de nuevas fotos (.png, .jpg, etc.)
    if (!empty($_FILES['fotos']['name'][0])) {
        foreach ($_FILES['fotos']['tmp_name'] as $k => $tmp) {
            $ext = pathinfo($_FILES['fotos']['name'][$k], PATHINFO_EXTENSION);
            $nombre_f = "noticia_" . $id_noticia . "_" . time() . "_" . $k . "." . $ext;
            if (move_uploaded_file($tmp, "img/" . $nombre_f)) {
                $stmt_img = $mysqli->prepare("INSERT INTO imagenes (id_noticia, ruta_archivo, principal) VALUES (?, ?, 0)");
                $stmt_img->bind_param("is", $id_noticia, $nombre_f);
                $stmt_img->execute();
            }
        }
    }
    header("Location: noticia.php?id=$id_noticia");
    exit();
}

// OBTENEDOR DE DATOS SEGURO (Evitamos inyecciones SQL en consultas GET)
$noticia = null;
$imagenes = [];

if ($id_noticia) {
    $stmt_not = $mysqli->prepare("SELECT * FROM noticia WHERE id = ?");
    $stmt_not->bind_param("i", $id_noticia);
    $stmt_not->execute();
    $noticia = $stmt_not->get_result()->fetch_assoc();

    $stmt_img_list = $mysqli->prepare("SELECT * FROM imagenes WHERE id_noticia = ? ORDER BY principal DESC");
    $stmt_img_list->bind_param("i", $id_noticia);
    $stmt_img_list->execute();
    $imagenes = $stmt_img_list->get_result()->fetch_all(MYSQLI_ASSOC);
}

echo $twig->render('gestion_noticia.twig', [
    'noticia' => $noticia,
    'imagenes' => $imagenes,
    'session' => $_SESSION
]);