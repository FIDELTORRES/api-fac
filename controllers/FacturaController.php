<?php
namespace controllers;

class FacturaController {

    public function emitirFactura() {
        // Obtener los datos enviados en formato JSON
        $input = json_decode(file_get_contents("php://input"), true);

        // Validar que se envió información básica requerida
        if (!isset($input["documento"]) || !isset($input["items"]) || !isset($input["emisor"])) {
            http_response_code(400);
            echo json_encode([
                "estado" => "error",
                "mensaje" => "Datos incompletos: documento, emisor o items faltantes."
            ]);
            return;
        }

        // Incluir la función que genera y envía la factura a SUNAT
        require_once __DIR__ . '/../lib/GenerarFacturaAPI.php';
 
        try {
            $resultado = generarFacturaDesdeApi($input);

            if ($resultado['estado'] === 'aceptado') {
                http_response_code(200);
            } else {
                http_response_code(400);
            }

            echo json_encode($resultado);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "estado" => "error",
                "mensaje" => "Error interno al procesar la factura.",
                "detalle" => $e->getMessage()
            ]);
        }
    }

    public function emitirFacturaDesdeArchivo($nombreArchivo) {
        require_once __DIR__ . "/../db.php";

        $ruta = getPathArchivos() . $nombreArchivo;
        
        if (!file_exists($ruta)) {
            http_response_code(404);
            echo json_encode([
                "estado" => "error",
                "mensaje" => "Archivo no encontrado: {$nombreArchivo}"
            ]);
            return;
        }

    $json = file_get_contents($ruta);
    $datosFactura = json_decode($json, true);

    if (empty($datosFactura)) {
        http_response_code(400);
        echo json_encode([
            "estado" => "error",
            "mensaje" => "El JSON está vacío o mal formado."
        ]);
        return;
    }

    require_once __DIR__ . '/../lib/GenerarFacturaAPI.php';

    try {
        $resultado = generarFacturaDesdeApi($datosFactura);

        if ($resultado['estado'] === 'aceptado') {
            http_response_code(200);
        } else {
            http_response_code(400);
        }

        echo json_encode($resultado);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "estado" => "error",
            "mensaje" => "Error interno al procesar la factura desde archivo.",
            "detalle" => $e->getMessage()
        ]);
    }
    }

}
