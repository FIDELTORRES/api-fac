<?php
require_once 'db.php';
require_once 'xml/FacturaXML.php';
require_once 'xml/FirmarXML.php';
require_once 'xml/ServicioSunat.php';

function generarFacturaDesdeApi($datosFactura) {
    $directorio = getPathJson();
    if (!is_dir($directorio)) {
        mkdir($directorio, 0777, true);
        apiLog('INFO', "Se creó directorio de facturas", ["directorio" => $directorio]);
    }

    $ruc = $datosFactura['emisor']['documento']['numero'];
    $tipoDoc = $datosFactura['documento']['tipo_factura']; // 01 o 03
    $serieNumero = $datosFactura['documento']['numero'];

    $fileName = "{$ruc}-{$tipoDoc}-{$serieNumero}";
    $xmlPath = $directorio . DIRECTORY_SEPARATOR . $fileName . '.xml';
    $zipPath = $directorio . DIRECTORY_SEPARATOR . $fileName . '.zip';
    $cdrZipPath = $directorio . DIRECTORY_SEPARATOR . "R-{$fileName}.zip";
    $cdrXmlPath = $directorio . DIRECTORY_SEPARATOR . "R-{$fileName}.xml";

    try {
        // Crear XML
        $xml = new FacturaXML($datosFactura, true);
        $xml->dom->formatOutput = true;

        // Firmar XML
        $claveCertificate = "Tablazo1967*";
        $pathCertificate = "certificado/LLAMA-PE-CERTIFICADO-DEMO-10413820532.pfx";

        $signer = new XmlSign();
        $signer->setPassword($claveCertificate);
        $signer->setCertificate($pathCertificate);
        $signedXml = $signer->Sign($xml->dom->saveXML());

        file_put_contents($xmlPath, $signedXml);
        apiLog('INFO', "XML firmado generado", ["xml" => $xmlPath]);

        // Comprimir a ZIP
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            $zip->addFile($xmlPath, $fileName . '.xml');
            $zip->close();
            apiLog('INFO', "ZIP creado", ["zip" => $zipPath]);
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
        apiLog('INFO', "CDR guardado y descomprimido", ["cdr_zip" => $cdrZipPath, "cdr_xml" => $cdrXmlPath]);

        if (!file_exists($cdrXmlPath)) {
            throw new Exception("No se encontró XML del CDR.");
        }

        // Cargar CDR y extraer información
        $dom = new DOMDocument();
        $dom->load($cdrXmlPath);

        $hash = $dom->getElementsByTagName("DigestValue")->item(0)->nodeValue;
        $descripcion = $dom->getElementsByTagName("Description")->item(0)->nodeValue;
        $codigoRespuesta = $dom->getElementsByTagName("ResponseCode")->item(0)->nodeValue;

        // Nombres de archivos
        $nombreXml = basename($xmlPath);
        $nombreCdr = basename($cdrZipPath);

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
                    $nombreXml,
                    $nombreCdr,
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


        // Limpiar archivos solo si hubo error
        if ($codigoRespuesta !== '0') {
            @unlink($xmlPath);
            @unlink($zipPath);
            @unlink($cdrZipPath);
            @unlink($cdrXmlPath);
            apiLog('ERROR', "Error SUNAT, archivos eliminados", ["codigoRespuesta" => $codigoRespuesta]);
        }

        return [
            "estado"           => $codigoRespuesta,
            "mensaje"          => $descripcion,
            "hash"             => $hash,
            "codigo_respuesta" => $codigoRespuesta,
            "nombreXml"        => $nombreXml,
            "nombreCdr"        => $nombreCdr,
            "archivos" => [
                "xml"     => $xmlPath,
                "zip"     => $zipPath,
                "cdr_zip" => $cdrZipPath,
                "cdr_xml" => $cdrXmlPath
            ]
        ];

    } catch (Exception $e) {
        apiLog('ERROR', "Excepción al generar factura", ["error" => $e->getMessage()]);
        return ["estado" => "error", "mensaje" => $e->getMessage()];
    }
}
