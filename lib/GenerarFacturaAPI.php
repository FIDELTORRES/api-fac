<?php
require_once 'db.php';
require_once 'xml/FacturaXML.php';
require_once __DIR__ . '/../xml/FirmarXML.php';  //'xml/FirmarXML.php';
require_once 'xml/ServicioSunat.php';

function generarFacturaDesdeApi($datosFactura) {
    $directorio = getPathArchivos();
    if (!is_dir($directorio)) {
        mkdir($directorio, 0777, true);
        apiLog('INFO', "Se creó directorio de facturas", ["directorio" => $directorio]);
    }
    // Preparar nombres de archivos
    $ruc = $datosFactura['emisor']['documento']['numero'];
    $tipoDoc = $datosFactura['documento']['tipo_factura']; // 01, 03, ticket, etc.
    $serieNumero = $datosFactura['documento']['numero'];
    $fileName = "{$ruc}-{$tipoDoc}-{$serieNumero}";

    $xmlPath = $directorio . DIRECTORY_SEPARATOR . $fileName . '.xml';
    $zipPath = $directorio . DIRECTORY_SEPARATOR . $fileName . '.zip';
    $cdrZipPath = $directorio . DIRECTORY_SEPARATOR . "R-{$fileName}.zip";
    $cdrXmlPath = $directorio . DIRECTORY_SEPARATOR . "R-{$fileName}.xml";

    $hash = null;
    $codigoRespuesta = null;
    $descripcion = null;

    try {
        // Crear XML
        $xml = new FacturaXML($datosFactura, true);
        $xml->dom->formatOutput = true;
        file_put_contents($xmlPath, $xml->dom->saveXML());

        if ($tipoDoc == '01' || $tipoDoc == '03') {
            // === Envío a SUNAT ===
            $claveCertificate = "Tablazo1967*";
            $pathCertificate = "certificado/LLAMA-PE-CERTIFICADO-DEMO-10413820532.pfx";

            $signer = new XmlSign();
            $signer->setPassword($claveCertificate);
            $signer->setCertificate($pathCertificate);
            $signedXml = $signer->Sign($xml->dom->saveXML());
            file_put_contents($xmlPath, $signedXml);

            // Comprimir a ZIP
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
                $zip->addFile($xmlPath, $fileName . '.xml');
                $zip->close();
            } else {
                throw new Exception("No se pudo crear archivo ZIP.");
            }

            // Datos SUNAT
            $modo = 'BETA';
            if ($modo === 'BETA') {
                $userSunat = 'MODDATOS';
                $passSunat = 'MODDATOS';
                $wsdlURL = 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService';
            } else {
                $userSunat = 'USUARIO_SUNAT';
                $passSunat = 'CLAVE_SUNAT';
                $wsdlURL = 'wsdl/billService.wsdl';
            }

            $username = $ruc . $userSunat;
            $fileZipContent = file_get_contents($zipPath);

            $sunat = new Sunat();
            $sunat->setUsername($username);
            $sunat->setPassword($passSunat);
            $sunat->setWsdl($wsdlURL);
            $sunat->setZip($fileZipContent);
            $sunat->setFileName($fileName);
            $result = $sunat->sendBill();

            if (empty($result) || empty($result->zip)) {
                throw new Exception("No se recibió respuesta de SUNAT.");
            }

            // Guardar CDR
            file_put_contents($cdrZipPath, $result->zip);
            $zip = new ZipArchive();
            $zip->open($cdrZipPath);
            $zip->extractTo($directorio);
            $zip->close();

            // Cargar CDR y extraer información
            $dom = new DOMDocument();
            $dom->load($cdrXmlPath);

            $hash = $dom->getElementsByTagName("DigestValue")->item(0)->nodeValue;
            $descripcion = $dom->getElementsByTagName("Description")->item(0)->nodeValue;
            $codigoRespuesta = $dom->getElementsByTagName("ResponseCode")->item(0)->nodeValue;

        } else {
            // === Venta interna ===
            $hash = null;
            $descripcion = "Venta interna, no enviada a SUNAT";
            $codigoRespuesta = '99'; // Código para ventas internas
            apiLog('INFO', "Venta interna registrada", ["tipoDoc" => $tipoDoc, "fileName" => $fileName]);
        }

        // Actualizar pedidoweb
        $idPedido = $datosFactura['idpedidoweb'] ?? null;
        if ($idPedido) {
            try {
                $pdo = getPDO();
                $stmt = $pdo->prepare("
                    UPDATE pedidoweb SET
                        nomxml = ?,
                        xmlsunat = ?,
                        hashsunat = ?,
                        rptasunat = ?,
                        obsersunat = ?
                    WHERE idpedidoweb = ?
                ");
                $stmt->execute([
                    basename($xmlPath),
                    ($tipoDoc == '01' || $tipoDoc == '03') ? basename($cdrZipPath) : null,
                    $hash,
                    $codigoRespuesta,
                    $descripcion,
                    $idPedido
                ]);
                apiLog('INFO', "pedidoweb actualizado", ["idpedidoweb" => $idPedido]);
            } catch (Exception $e) {
                apiLog('ERROR', "No se pudo actualizar pedidoweb", ["error" => $e->getMessage()]);
            }
        }

        return [
            "estado"           => $codigoRespuesta,
            "mensaje"          => $descripcion,
            "hash"             => $hash,
            "codigo_respuesta" => $codigoRespuesta,
            "nombreXml"        => basename($xmlPath),
            "nombreCdr"        => ($tipoDoc == '01' || $tipoDoc == '03') ? basename($cdrZipPath) : null,
            "archivos" => [
                "xml"     => $xmlPath,
                "zip"     => $zipPath,
                "cdr_zip" => ($tipoDoc == '01' || $tipoDoc == '03') ? $cdrZipPath : null,
                "cdr_xml" => ($tipoDoc == '01' || $tipoDoc == '03') ? $cdrXmlPath : null
            ]
        ];

    } catch (Exception $e) {
        apiLog('ERROR', "Excepción al generar factura", ["error" => $e->getMessage()]);
        return ["estado" => "error", "mensaje" => $e->getMessage()];
    }
}
