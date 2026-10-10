<?php
require_once  'xml/FacturaXML.php';
require_once  'xml/FirmarXML.php';
require_once  'xml/ServicioSunat.php';

function generarFacturaDesdeApi($datosFactura) {
    $directorio = 'data/';
    if (!is_dir($directorio)) {
        mkdir($directorio, 0777, true);
    }

    $ruc = $datosFactura['emisor']['documento']['numero'];
    $tipoDoc = $datosFactura['documento']['tipo_factura']; // 01 o 03
    $serieNumero = $datosFactura['documento']['numero'];

    $fileName = "{$ruc}-{$tipoDoc}-{$serieNumero}";

    // Crear XML
    $xml = new FacturaXML($datosFactura, true);
    $xml->dom->formatOutput = true;

    // Firma
    $claveCertificate = "Tablazo1967*"; // Clave del certificado de prueba
    $pathCertificate = "certificado/LLAMA-PE-CERTIFICADO-DEMO-10413820532.pfx"; // Certificado PFX

    $signer = new XmlSign();
    $signer->setPassword($claveCertificate);
    $signer->setCertificate($pathCertificate);
    $signedXml = $signer->Sign($xml->dom->saveXML());

    // Guardar XML firmado
    $xmlPath = $directorio . $fileName . '.xml';
    file_put_contents($xmlPath, $signedXml);

    // Comprimir a ZIP
    $zipPath = $directorio . $fileName . '.zip';
    $zip = new ZipArchive;
    if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
        $zip->addFile($xmlPath, $fileName . '.xml');
        $zip->close();
    } else {
        return ["estado" => "error", "mensaje" => "No se pudo crear archivo ZIP."];
    }

    // Datos SUNAT
    $modo = 'BETA'; // Cambiar a PRODUCCION si corresponde
    if ($modo === 'BETA') {
        $userSunat = 'MODDATOS';
        $passSunat = 'MODDATOS';
        $wsdlURL = 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService?wsdl'; //SOAP
		$wsdlURL = 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService'; 		//CURL
    } else {
        // PRODUCCION: reemplaza con tus credenciales reales y WSDL local
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
        return ["estado" => "error", "mensaje" => "No se recibió respuesta de SUNAT."];
    }

    // Guardar CDR
    $cdrZipPath = $directorio . "R-{$fileName}.zip";
    file_put_contents($cdrZipPath, $result->zip);

    $zip = new ZipArchive();
    $zip->open($cdrZipPath);
    $zip->extractTo($directorio);
    $zip->close();

    $cdrXmlPath = $directorio . "R-{$fileName}.xml";
    if (!file_exists($cdrXmlPath)) {
        return ["estado" => "error", "mensaje" => "No se encontró XML del CDR."];
    }

    $dom = new DOMDocument();
    $dom->load($cdrXmlPath);

    $hash = $dom->getElementsByTagName("DigestValue")->item(0)->nodeValue;
    $descripcion = $dom->getElementsByTagName("Description")->item(0)->nodeValue;
    $codigoRespuesta = $dom->getElementsByTagName("ResponseCode")->item(0)->nodeValue;

    // Limpiar archivos temporales
    unlink($xmlPath);
    unlink($zipPath);
    unlink($cdrZipPath);
    unlink($cdrXmlPath);

    return [
        "estado" => $codigoRespuesta === '0' ? "aceptado" : "rechazado",
        "mensaje" => $descripcion,
        "hash" => $hash,
        "codigo_respuesta" => $codigoRespuesta
    ];
}
