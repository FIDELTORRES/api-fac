<?php
// Habilitar CORS
header("Access-Control-Allow-Origin: *"); // o puedes restringir a 127.0.0.1 si prefieres
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Responder preflight OPTIONS automáticamente
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
require_once __DIR__ . '/api_parametros.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/GenerarFacturaAPI.php';

$input = json_decode(file_get_contents("php://input"), true);

$idpedidoweb = $input['idpedidoweb'] ?? null;
$archivoJson = $input['archivoJson'] ?? null;
$archivoXml = $input['archivoXml'] ?? null;

if (!$archivoJson) {
    echo json_encode(["estado" => "error", "mensaje" => "Falta el nombre del archivo JSON."]);
    exit;
}

$baseDir = getPathArchivos();
$pathJson = $baseDir . $archivoJson;

if (!file_exists($pathJson)) {
    echo json_encode(["estado" => "error", "mensaje" => "No se encontró el archivo JSON en: {$pathJson}"]);
    exit;
}

// Leer y decodificar el JSON
$contenido = file_get_contents($pathJson);
$datosFactura = json_decode($contenido, true);

if ($datosFactura === null) {
    echo json_encode(["estado" => "error", "mensaje" => "El JSON está mal formado."]);
    exit;
}

// Reusar la misma función de emisión
try {
    $resultado = generarFacturaDesdeApi($datosFactura);

    // ✅ Si fue aceptada o reenviada correctamente, actualiza la tabla pedidoweb
    if (isset($resultado['estado']) && $resultado['estado'] === "0" && $idpedidoweb) {
    try {
        $pdo = getPDO();

        // Tomamos directamente el CDR XML que devuelve el proceso
        $xmlsunat = basename($resultado['archivos']['cdr_xml'] ?? '');

        $sql = "UPDATE pedidoweb 
                SET rptasunat     = :estado,
                    obsersunat    = :mensaje,
                    hashsunat     = :hash,
                    xmlsunat      = :xmlsunat,
                    fechamodifica = NOW()
                WHERE idpedidoweb = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':estado', $resultado['codigo_respuesta']);
        $stmt->bindParam(':mensaje', $resultado['mensaje']);
        $stmt->bindParam(':hash', $resultado['hash']);
        $stmt->bindParam(':xmlsunat', $xmlsunat);
        $stmt->bindParam(':id', $idpedidoweb, PDO::PARAM_INT);
        $stmt->execute();

    } catch (PDOException $e) {
        echo json_encode([
            "estado" => "error",
            "mensaje" => "Error al actualizar pedidoweb: " . $e->getMessage()
        ]);
        exit;
    }
}

    echo json_encode($resultado);
} catch (Exception $e) {
    echo json_encode(["estado" => "error", "mensaje" => $e->getMessage()]);
}
