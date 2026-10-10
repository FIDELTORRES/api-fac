<?php
/**
 * Clase para Firmar Documentos Electrónicos - SUNAT
 */
 $dir = dirname(dirname(__FILE__));
require_once $dir.'/lib/robrichards/xmlseclibs.php';

class XmlSign
{
    protected $privateKey;
    protected $publicKey;

    private $password;

    public function setPassword($password){
        $this->password = $password;
    }

    public function setCertificate($filename)
    {
        if (!file_exists($filename)) {
            throw new \Exception('Certificate File not found');
        }

        $this->extractOpenSSL(file_get_contents($filename));
    }

    private function extractOpenSSL($pfx)
    {
        $key = array();
        if (openssl_pkcs12_read($pfx, $key, $this->password)){
            $this->publicKey  = $key["cert"];
            $this->privateKey = $key["pkey"];
        }

        if (is_null($this->publicKey) || is_null($this->privateKey)) return $this->dom->saveXML();
    }

    public function Sign($XMLString)
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadXML($XMLString);

        $objSign = new XMLSecurityDSig();
        $objSign->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $objSign->addReference(
            $dom,
            XMLSecurityDSig::SHA1,
            array('http://www.w3.org/2000/09/xmldsig#enveloped-signature'),
            $options = array('force_uri' => true)
        );

        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type' => 'private'));
        $objKey->loadKey($this->privateKey);

        // Firmar el archivo XML
        $objSign->sign($objKey, $dom->getElementsByTagName("ExtensionContent")->item(0));
        
        // Agregue la clave pública asociada a la firma
        $objSign->add509Cert($this->publicKey);

        // Retornamos nuestro XML firmafo
        return $dom->saveXML();
    }
}
