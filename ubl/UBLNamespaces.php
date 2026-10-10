<?php

class UBLNamespaces
{

    static $xmlnsRetention        = "urn:sunat:names:specification:ubl:peru:schema:xsd:Retention-1";
    static $xmlnsInvoice          = "urn:oasis:names:specification:ubl:schema:xsd:Invoice-2";
    static $xmlnsCreditNote       = "urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2";
    static $xmlnsDebitNote        = "urn:oasis:names:specification:ubl:schema:xsd:DebitNote-2";
    static $xmlnsVoidedDocuments  = "urn:sunat:names:specification:ubl:peru:schema:xsd:VoidedDocuments-1";
    static $xmlnsSummaryDocuments = "urn:sunat:names:specification:ubl:peru:schema:xsd:SummaryDocuments-1";
    static $xmlnsDespatchAdvice   = 'urn:oasis:names:specification:ubl:schema:xsd:DespatchAdvice-2';

    static $sac        = "urn:sunat:names:specification:ubl:peru:schema:xsd:SunatAggregateComponents-1";
    static $cac        = "urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2";
    static $cbc        = "urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2";
    static $udt        = "urn:un:unece:uncefact:data:specification:UnqualifiedDataTypesSchemaModule:2";
    static $ccts       = "urn:un:unece:uncefact:documentation:2";
    static $ext        = "urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2";
    static $qdt        = "urn:oasis:names:specification:ubl:schema:xsd:QualifiedDatatypes-2";
    static $ds         = "http://www.w3.org/2000/09/xmldsig#";
    static $xsi        = "http://www.w3.org/2001/XMLSchema-instance";
    static $ar         = "urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2";
    static $wssecurity = "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd";
    static $xsd        = "http://www.w3.org/2001/XMLSchema";

    // métodos
    public static function Invoice()
    {
        $namespaces = array(
            'cac'  => self::$cac,
            'cbc'  => self::$cbc,
            'ccts' => self::$ccts,
            'ds'   => self::$ds,
            'ext'  => self::$ext,
            'qdt'  => self::$qdt,
            // 'sac'  => self::$sac,
            'udt'  => self::$udt,
            'xsi'  => self::$xsi,
            // 'xsd'  => self::$xsd
        );

        return $namespaces;
    }

    public static function Note()
    {
        $namespaces = array(
            'cac'  => self::$cac,
            'cbc'  => self::$cbc,
            'ccts' => self::$ccts,
            'ds'   => self::$ds,
            'ext'  => self::$ext,
            'qdt'  => self::$qdt,
            'sac'  => self::$sac,
            'udt'  => self::$udt,
            'xsi'  => self::$xsi,
        );

        return $namespaces;
    }

    public static function Summary()
    {
        $namespaces = array(
            'cac' => self::$cac,
            'cbc' => self::$cbc,
            'ds'  => self::$ds,
            'ext' => self::$ext,
            'sac' => self::$sac,
            'xsi' => self::$xsi,
        );

        return $namespaces;
    }

    public static function VoidedDocuments()
    {
        $namespaces = array(
            'cac' => self::$cac,
            'cbc' => self::$cbc,
            'ds'  => self::$ds,
            'ext' => self::$ext,
            'sac' => self::$sac,
            'xsi' => self::$xsi,
        );

        return $namespaces;
    }

    public static function Retention()
    {
        $namespaces = array(
            'cac'  => self::$cac,
            'cbc'  => self::$cbc,
            'ccts' => self::$ccts,
            'ds'   => self::$ds,
            'ext'  => self::$ext,
            'qdt'  => self::$qdt,
            'sac'  => self::$sac,
            'udt'  => self::$udt,
            'xsi'  => self::$xsi,
        );

        return $namespaces;
    }

    public static function DespatchAdvice()
    {
        $namespaces = array(
            'cac'  => self::$cac,
            'cbc'  => self::$cbc,
            'ds'   => self::$ds,
            'ext'  => self::$ext
            );
        return $namespaces;
    }
}
