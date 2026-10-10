<?php
// Permitir solicitudes desde cualquier origen (para pruebas locales y CORS)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Mostrar errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");

// Responder inmediatamente si es una solicitud OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Procesar rutas
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Requiere el controlador de factura
require_once __DIR__ . '/controllers/FacturaController.php';

use controllers\FacturaController;

$controller = new FacturaController();

// Ruta POST para emitir factura desde JSON enviado
if ($uri === '/api/factura' && $method === 'POST') {
    $controller->emitirFactura();
    exit;
}

// Nueva Ruta GET para emitir factura desde archivo JSON guardado
if ($uri === '/api/emitirFacturaArchivo' && $method === 'GET') {
    if (!isset($_GET['archivo'])) {
        http_response_code(400);
        echo json_encode(["estado" => "error", "mensaje" => "Falta el parámetro 'archivo'."]);
        exit;
    }

    $archivo = $_GET['archivo'];
    $controller->emitirFacturaDesdeArchivo($archivo);
    exit;
}

// Ruta de prueba para verificar si la API está activa
if ($uri === '/api/factura' && $method === 'GET') {
    echo json_encode(["status" => "API activa. Use POST para enviar datos."]);
    exit;
}

// Ruta no encontrada
http_response_code(404);
echo json_encode(["error" => "Ruta no encontrada"]);
