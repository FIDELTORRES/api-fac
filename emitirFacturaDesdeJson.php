<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Incluir db.php correctamente
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/GenerarFacturaAPI.php';

// Tomar parámetro archivo
$archivo = $_POST['archivo'] ?? $_GET['archivo'] ?? '';

if (empty($archivo)) {
    apiLog('ERROR', "Parámetro 'archivo' no especificado");
    http_response_code(400);
    echo json_encode(["estado" => "error", "mensaje" => "Parámetro 'archivo' no especificado."]);
    exit;
}

// Usar la ruta definida en db.php según ambiente
$archivoJson = basename($archivo);
//$pathJson = getPathJson() . DIRECTORY_SEPARATOR . $archivoJson;
//$pathJson = getPathData() . DIRECTORY_SEPARATOR . $archivoJson;
$pathJson = getPathArchivos() .DIRECTORY_SEPARATOR . $archivoJson;
// Verificar si el archivo existe

if (!file_exists($pathJson)) {
    apiLog('ERROR', "Archivo JSON no encontrado", ["archivo" => $archivoJson, "ruta" => $pathJson]);
    http_response_code(404);
    echo json_encode(["estado" => "error", "mensaje" => "Archivo JSON no encontrado en {$pathJson}."]);
    exit;
}
echo json_encode(["estado" => "error", "mensaje" => "existe json {$pathJson}."]);
// Leer contenido del JSON
$contenido = file_get_contents($pathJson);
$datosFactura = json_decode($contenido, true);

if ($datosFactura === null) {
    apiLog('ERROR', "JSON inválido o mal formado", ["archivo" => $archivoJson, "contenido" => $contenido]);
    http_response_code(400);
    echo json_encode(["estado" => "error", "mensaje" => "JSON inválido o mal formado."]);
    exit;
}

try {
    // Generar factura usando la función de GenerarFacturaAPI.php
    $resultado = generarFacturaDesdeApi($datosFactura);
    apiLog('INFO', "Factura procesada correctamente", ["archivo" => $archivoJson, "resultado" => $resultado]);
    echo json_encode($resultado);
} catch (Exception $e) {
    apiLog('ERROR', "Excepción al procesar factura", ["error" => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(["estado" => "error", "mensaje" => "Error al procesar la factura", "detalle" => $e->getMessage()]);
}
