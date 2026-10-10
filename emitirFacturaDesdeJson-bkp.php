<?php
// Mostrar errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cabeceras para JSON y CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Tomar el parámetro 'archivo' ya sea por POST o GET
$archivo = $_POST['archivo'] ?? $_GET['archivo'] ?? '';

if (empty($archivo)) {
    http_response_code(400);
    echo json_encode(["estado" => "error", "mensaje" => "Parámetro 'archivo' no especificado."]);
    exit;
}

// Evitar rutas externas
$archivoJson = basename($archivo);
//$pathJson = __DIR__ . '/data/' . $archivoJson;

$archivoJson = basename($archivo);
$pathJson = getPathJson() . DIRECTORY_SEPARATOR . $archivoJson;

// Solo para depuración:
echo "Ruta completa que se va a leer: " . $pathJson;
exit; // detener el script para que veas solo esto


// Verificar que el archivo exista
if (!file_exists($pathJson)) {
    http_response_code(404);
    echo json_encode(["estado" => "error", "mensaje" => "Archivo JSON no encontrado en /data."]);
    exit;
}

// Leer y decodificar JSON
$contenido = file_get_contents($pathJson);
$datosFactura = json_decode($contenido, true);

if ($datosFactura === null) {
    http_response_code(400);
    echo json_encode(["estado" => "error", "mensaje" => "JSON inválido o mal formado."]);
    exit;
}

// Requiere el archivo que contiene la lógica de emisión
require_once __DIR__ . '/lib/GenerarFacturaAPI.php';

// Llamar a la función real
$resultado = generarFacturaDesdeApi($datosFactura);

// Mostrar resultado (respuesta SUNAT o error)
echo json_encode($resultado);
