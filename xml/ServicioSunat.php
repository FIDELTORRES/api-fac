<?php
/**
 * Clase extendida de SoapClient para enviar XML firmado a SUNAT
 */
class ServicioSunat extends SoapClient
{
    private string $XMLStr = "";

    public function setXMLStr(string $value): void
    {
        $this->XMLStr = $value;
    }

    public function getXMLStr(): string
    {
        return $this->XMLStr;
    }

    #[\ReturnTypeWillChange]
    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        $request = $this->XMLStr;

        $dom = new DOMDocument('1.0');
        try {
            $dom->loadXML($request);
        } catch (DOMException $e) {
            die("Error al cargar XML: " . $e->getMessage());
        }

        $request = $dom->saveXML();
        return parent::__doRequest($request, $location, $action, $version, $one_way);
    }

    public function SoapClientCall(string $SOAPXML): void
    {
        $this->setXMLStr($SOAPXML);
    }
}

/**
 * Clase para enviar documentos a SUNAT
 */
class Sunat
{
    private string $wsdlURL;
    private string $fileName;
    private string $fileZip;
    private string $username;
    private string $password;

    public function setWsdl(string $wsdl): void { $this->wsdlURL = $wsdl; }
    public function setFileName(string $fileName): void { $this->fileName = $fileName; }
    public function setZip(string $fileZip): void { $this->fileZip = $fileZip; }
    public function setUsername(string $username): void { $this->username = $username; }
    public function setPassword(string $password): void { $this->password = $password; }

    private function curlCall(string $XMLString): ?string
    {
        $headers = [
            "Content-type: text/xml;charset=\"utf-8\"",
            "Accept: text/xml",
            "Content-length: " . strlen($XMLString),
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->wsdlURL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPAUTH => CURLAUTH_ANY,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $XMLString,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpcode === 200 ? $response : null;
    }

    public function sendBill()
    {
        $XMLString = $this->buildEnvelope("sendBill");

        $result = $this->curlCall($XMLString);
        if (!$result) {
            throw new Exception("Error al conectar con SUNAT.");
        }

        $this->validateSoapFault($result);
        return $this->getResponseZip($result);
    }

    public function sendSummary()
    {
        $XMLString = $this->buildEnvelope("sendSummary");

        $result = $this->curlCall($XMLString);
        if (!$result) {
            throw new Exception("Error al conectar con SUNAT.");
        }

        $this->validateSoapFault($result);
        return $this->getResponseTicket($result);
    }

    public function getStatus(string $ticket)
    {
        $XMLString = $this->buildEnvelope("getStatus", $ticket);

        $result = $this->curlCall($XMLString);
        if (!$result) {
            throw new Exception("Error al conectar con SUNAT.");
        }

        $this->validateSoapFault($result);
        return $this->getResponseSummaryZip($result);
    }

    private function buildEnvelope(string $action, string $ticket = ""): string
    {
        $bodyContent = match ($action) {
            "sendBill" => "
                <ser:sendBill>
                    <fileName>{$this->fileName}.zip</fileName>
                    <contentFile>" . base64_encode($this->fileZip) . "</contentFile>
                </ser:sendBill>",
            "sendSummary" => "
                <ser:sendSummary>
                    <fileName>{$this->fileName}.zip</fileName>
                    <contentFile>" . base64_encode($this->fileZip) . "</contentFile>
                </ser:sendSummary>",
            "getStatus" => "
                <ser:getStatus>
                    <ticket>{$ticket}</ticket>
                </ser:getStatus>",
            default => throw new Exception("Acción no válida: $action")
        };

        return '<?xml version="1.0" encoding="UTF-8"?>
            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                              xmlns:ser="http://service.sunat.gob.pe"
                              xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
                <soapenv:Header>
                    <wsse:Security>
                        <wsse:UsernameToken Id="ABC-123">
                            <wsse:Username>' . $this->username . '</wsse:Username>
                            <wsse:Password>' . $this->password . '</wsse:Password>
                        </wsse:UsernameToken>
                    </wsse:Security>
                </soapenv:Header>
                <soapenv:Body>' . $bodyContent . '</soapenv:Body>
            </soapenv:Envelope>';
    }

    private function validateSoapFault(string $response): void
    {
        $xml = new DOMDocument();
        $xml->loadXML($response);
        $xpath = new DOMXPath($xml);
        $fault = $xpath->query("//faultstring");

        if ($fault->length > 0) {
            $message = $fault->item(0)->nodeValue;
            $code = $xpath->query("//faultcode")->item(0)?->nodeValue ?? "SOAP-ERROR";
            throw new Exception("$code - $message");
        }
    }

    private function getResponseZip(string $xmlContent): ?object
    {
        $xml = new DOMDocument();
        $xml->loadXML($xmlContent);
        $xpath = new DOMXPath($xml);
        $res = $xpath->query("//applicationResponse");
        if ($res->length !== 1) return null;

        return (object)[
            'zip' => base64_decode($res->item(0)->nodeValue)
        ];
    }

    private function getResponseTicket(string $xmlContent): ?object
    {
        $xml = new DOMDocument();
        $xml->loadXML($xmlContent);
        $xpath = new DOMXPath($xml);
        $res = $xpath->query("//ticket");
        if ($res->length !== 1) return null;

        return (object)[
            'ticket' => $res->item(0)->nodeValue
        ];
    }

    private function getResponseSummaryZip(string $xmlContent): ?object
    {
        $xml = new DOMDocument();
        $xml->loadXML($xmlContent);
        $xpath = new DOMXPath($xml);
        $res = $xpath->query("//content");
        if ($res->length !== 1) return null;

        return (object)[
            'zip' => base64_decode($res->item(0)->nodeValue),
            'statusCode' => $xml->getElementsByTagName('statusCode')->item(0)?->nodeValue ?? ""
        ];
    }
}
