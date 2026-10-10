<?php
/**
 * Desarrollado por: Juan Vásquez Ventura
 * E-mail: juanvasquezventura@gmail.com
 *
 * Clase para Generar Factura en Formato XML
 */
 
$dir = dirname(dirname(__FILE__));
require_once $dir.'/ubl/UBLNamespaces.php';
require_once $dir.'/ubl/UBLSunat.php';

class FacturaXML extends UBLSunat
{

    public function __construct($data, $dsSignature = false)
    {
        $this->namespaces = UBLNamespaces::Invoice();

        $notas     = $data['notas'];
        $impuestos = $data['impuestos'];
        $items     = $data['items'];

        $this->dom  = new DOMDocument('1.0', 'UTF-8');
        $this->root = $this->dom->createElementNS(UBLNamespaces::$xmlnsInvoice, 'Invoice');

        foreach ($this->namespaces as $pfx => $namespace) {
            $this->root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . $pfx, $namespace);
        }

        $this->dom->appendChild($this->root);
        $this->current = $this->root;

        // En esta zona va el certificado digital  - (Firma Digital).
        if ($dsSignature) {
            $this
                ->append_fw('ext:UBLExtensions')
                    ->append_fw('ext:UBLExtension')
                        ->append_fw('ext:ExtensionContent');
        }

        $this
            ->reset()
            //cabecera UBL
            ->append('cbc:UBLVersionID', '2.1')
            ->append('cbc:CustomizationID', '2.0')
            ->append('cbc:ProfileID' , $data['documento']['tipo_transaccion'])
                ->attribute('schemeName' , 'SUNAT:Identificador de Tipo de Operación')
                ->attribute('schemeAgencyName','PE:SUNAT')
                ->attribute('schemeURI' , 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo17')

            //datos del documento
            ->append('cbc:ID', $data['documento']['numero'])
            ->append('cbc:IssueDate', $data['documento']['fecha_emision'])
            ->append('cbc:IssueTime' , $data['documento']['hora_emision'])
            ->append('cbc:InvoiceTypeCode', $data['documento']['tipo_factura'])
                ->attribute('listAgencyName','PE:SUNAT')
                ->attribute('listName','Tipo de Documento')//SUNAT: Identificador de Tipo de Documento
                ->attribute('listURI' , 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo01')
                ->attribute('listID', $data['documento']['tipo_transaccion']);
            foreach ($notas as $nota)
            {
                $this
                    ->append('cbc:Note', $nota['valor'])
                        ->attribute('languageLocaleID',$nota['id']);
            }
            $this
            ->append('cbc:DocumentCurrencyCode', $data['documento']['moneda'])
                ->attribute('listID' , 'ISO 4217 Alpha')
                ->attribute('listAgencyName','United Nations Economic Commission for Europe')
                ->attribute('listName','Currency')
            //Cantidad de Items de la factura
            ->append('cbc:LineCountNumeric',$data['documento']['numero_items'])

            //datos del firmante
            ->append_fw('cac:Signature')
                ->append('cbc:ID', $data['emisor']['documento']['numero'])//'IDSignKG'
                ->append_fw('cac:SignatoryParty')
                    ->append_fw('cac:PartyIdentification')
                        ->append('cbc:ID', $data['emisor']['documento']['numero'])
                    ->pop()
                    ->append_fw('cac:PartyName')
                        ->append('cbc:Name', $data['emisor']['datos']['razon_social'], true)
                    ->pop()
                ->pop()
                ->append_fw('cac:DigitalSignatureAttachment')
                    ->append_fw('cac:ExternalReference')
                        ->append('cbc:URI', '#signatureKG')
                    ->pop(2)
            ->reset()

            //datos del emisor
            ->append_fw('cac:AccountingSupplierParty')
                ->append_fw('cac:Party')
                    ->append_fw('cac:PartyIdentification') //Identificación del Emisor electrónico
                        ->append('cbc:ID' , $data['emisor']['documento']['numero'])
                        ->attribute('schemeID',$data['emisor']['documento']['tipo'])
                        ->attribute('schemeName','Documento de Identidad')
                        ->attribute('schemeAgencyName' , 'PE:SUNAT')
                        ->attribute('schemeURI', 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06')
                    ->pop();
                if (isset($data['emisor']['datos']['nombre_comercial']) && !empty($data['emisor']['datos']['nombre_comercial'])) {
                    $this
                    ->append_fw('cac:PartyName')
                    ->append('cbc:Name', $data['emisor']['datos']['nombre_comercial'], true)
                    ->pop();
                }

            if(isset($data['emisor']['datos']['razon_social']) && !empty($data['emisor']['datos']['razon_social']))
            {
                $this
                    ->append_fw('cac:PartyTaxScheme')
                        ->append('cbc:RegistrationName' , $data['emisor']['datos']['razon_social'] , true)
                        ->append('cbc:CompanyID',$data['emisor']['documento']['numero'])
                            ->attribute('schemeAgencyName','PE:SUNAT')
                            ->attribute('schemeID',$data['emisor']['documento']['tipo'])
                            ->attribute('schemeName','SUNAT: Identificador de Documento de Identidad')
                            ->attribute('schemeURI','urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06')
                        ->append_fw('cac:TaxScheme')
                            ->append('cbc:ID',$data['emisor']['documento']['numero'])
                            ->attribute('schemeID' , $data['emisor']['documento']['tipo'])
                            ->attribute('schemeName' , 'SUNAT:Identificador de Documento de Identidad')
                            ->attribute('schemeAgencyName' , 'PE:SUNAT')
                            ->attribute('schemeURI' , 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06')
                        ->pop()
                    ->pop();
            }

        $this
            ->append_fw('cac:PartyLegalEntity')
                ->append('cbc:RegistrationName', $data['emisor']['datos']['razon_social'] , true);
                $this->append_fw('cac:RegistrationAddress');
                    if(isset($data['emisor']['ubicacion']['ubigeo']) && !empty($data['emisor']['ubicacion']['ubigeo']))
                    {
                        $this->append('cbc:ID',$data['emisor']['ubicacion']['ubigeo']) //Ubigeo
                            ->attribute('schemeName','Ubigeos')
                            ->attribute('schemeAgencyName','PE:INEI');
                    }
                    if (isset($data['emisor']['ubicacion']['anexo']) && !empty($data['emisor']['ubicacion']['anexo']))
                    {
                        //Código del domicilio fiscal o de local anexo del emisor
                        $this->append('cbc:AddressTypeCode',$data['emisor']['ubicacion']['anexo'])
                            ->attribute('listAgencyName','PE:SUNAT')
                            ->attribute('listName','Establecimientos anexos');
                    }
                    if(isset($data['emisor']['ubicacion']['ubigeo']) && !empty($data['emisor']['ubicacion']['ubigeo']))
                    {
                        $this->append('cbc:CityName',$data['emisor']['ubicacion']['provincia']) //Provincia
                        ->append('cbc:CountrySubentity',$data['emisor']['ubicacion']['departamento']) //Departamento
                        ->append('cbc:District',$data['emisor']['ubicacion']['distrito']) //Distrito
                        ->append_fw('cac:AddressLine')
                            ->append('cbc:Line', $data['emisor']['ubicacion']['direccion'])
                        ->pop()
                        ->append_fw('cac:Country')
                            ->append('cbc:IdentificationCode',$data['emisor']['ubicacion']['pais'])
                            ->attribute('listID','ISO 3166-1')
                            ->attribute('listAgencyName','United Nations Economic Commission for Europe')
                            ->attribute('listName','Country')
                        ->pop();
                    }
                $this->pop();
            $this->pop();

        $this
            ->pop()
            ->reset()
            ->append_fw('cac:AccountingCustomerParty')
                ->append_fw('cac:Party')
                    ->append_fw('cac:PartyIdentification') //Identificación del Emisor electrónico
                        ->append('cbc:ID' , $data['cliente']['documento']['numero'])
                        ->attribute('schemeID', $data['cliente']['documento']['tipo'])
                        ->attribute('schemeName','Documento de Identidad')
                        ->attribute('schemeAgencyName' , 'PE:SUNAT')
                        ->attribute('schemeURI', 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06')
                    ->pop();

                if (isset($data['cliente']['datos']['nombre_comercial']) && !empty($data['cliente']['datos']['nombre_comercial'])) {
                    $this
                        ->append_fw('cac:PartyName')
                            ->append('cbc:Name', $data['cliente']['datos']['nombre_comercial'], true)
                        ->pop();
                }

            if (isset($data['cliente']['datos']['razon_social']) && !empty($data['cliente']['datos']['razon_social']))
            {
                $this
                    ->append_fw('cac:PartyTaxScheme')
                        ->append('cbc:RegistrationName' , $data['cliente']['datos']['razon_social'] , true)
                        ->append('cbc:CompanyID',$data['cliente']['documento']['numero'])
                            ->attribute('schemeAgencyName','PE:SUNAT')
                            ->attribute('schemeID',$data['cliente']['documento']['tipo'])
                            ->attribute('schemeName','SUNAT: Identificador de Documento de Identidad')
                            ->attribute('schemeURI','urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06')
                        ->append_fw('cac:TaxScheme')
                            ->append('cbc:ID',$data['cliente']['documento']['numero'])
                                ->attribute('schemeID' , $data['cliente']['documento']['tipo'])
                                ->attribute('schemeName' , 'SUNAT:Identificador de Documento de Identidad')
                                ->attribute('schemeAgencyName' , 'PE:SUNAT')
                                ->attribute('schemeURI' , 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06')
                        ->pop()
                    ->pop();
            }
            $this
                ->append_fw('cac:PartyLegalEntity')
                    ->append('cbc:RegistrationName', $data['cliente']['datos']['razon_social'] , true)
                        ->append_fw('cac:RegistrationAddress');
                        if (isset($data['cliente']['ubicacion']['ubigeo']) && !empty($data['cliente']['ubicacion']['ubigeo']))
                        {
                            $this
                            ->append('cbc:ID', $data['cliente']['ubicacion']['ubigeo'])//Código Ubigeo
                                ->attribute('schemeName','Ubigeos')
                                ->attribute('schemeAgencyName','PE:INEI')
                            ->append('cbc:CityName', $data['cliente']['ubicacion']['provincia'])
                            ->append('cbc:CountrySubentity', $data['cliente']['ubicacion']['departamento'])
                            ->append('cbc:District', $data['cliente']['ubicacion']['distrito']);
                        }
                        $this
                            ->append_fw('cac:AddressLine')
                                ->append('cbc:Line', $data['cliente']['ubicacion']['direccion'])
                            ->pop()
                            ->append_fw('cac:Country')
                                ->append('cbc:IdentificationCode',$data['cliente']['ubicacion']['pais'])
                                    ->attribute('listID','ISO 3166-1')
                                    ->attribute('listAgencyName','United Nations Economic Commission for Europe')
                                    ->attribute('listName','Country')
                            ->pop()
                        ->pop()
                ->pop()
            ->pop()
        ->pop()->reset();

        /* DESCUENTOS GLOBALES */
        if (isset($data['total']['descuentoGlobal']) && $data['total']['descuentoGlobal'] > 0)
        {
            $this
                ->append_fw('cac:AllowanceCharge') 
                    ->append('cbc:ChargeIndicator','false')//Indicador del cargo/descuento global ("true"/"false")
                    //Código del motivo del cargo/descuento global - Catálogo 53
                    ->append('cbc:AllowanceChargeReasonCode', $data['total']['motivoDsctoGlobal'])
                        ->attribute('listName','Cargo/descuento')
                        ->attribute('listAgencyName','PE:SUNAT')
                        ->attribute('listURI','urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo53')
                    ->append('cbc:MultiplierFactorNumeric','0.00') //Factor del cargo/descuento del ítem 
                    ->append('cbc:Amount', $data['total']['descuentoGlobal']) //Monto del cargo/descuento global 
                        ->attribute('currencyID',$data['documento']['moneda'])
                    ->append('cbc:BaseAmount','0.00') //Monto de base de cargo/descuento global
                        ->attribute('currencyID',$data['documento']['moneda'])
                ->pop();
        }

        //TRIBUTOS
        $this
            ->append_fw('cac:TaxTotal')
                ->append('cbc:TaxAmount' , $data['total']['total_impuestos'])  //SUMATORIA DE IMPUESTOS (IGV + ISC + IVAP)
                    ->attribute('currencyID', $data['documento']['moneda']);
            if (isset($impuestos['total_ope_gravadas']) && $impuestos['total_ope_gravadas'] > 0)
            {
                $this
                    ->append_fw('cac:TaxSubtotal')
                        ->append('cbc:TaxableAmount', $impuestos['total_ope_gravadas'])
                            ->attribute('currencyID', $data['documento']['moneda'])
                        ->append('cbc:TaxAmount', $impuestos['total_igv_gravadas'])
                            ->attribute('currencyID', $data['documento']['moneda'])
                        ->append_fw('cac:TaxCategory')
                            ->append('cbc:ID' , 'S')
                                ->attribute('schemeID' , 'UN/ECE 5305')
                                ->attribute('schemeName','Tax Category Identifier')
                                ->attribute('schemeAgencyName','United Nations Economic Commission for Europe')
                            ->append_fw('cac:TaxScheme')
                                ->append('cbc:ID', '1000')
                                    ->attribute('schemeID','UN/ECE 5153')->attribute('schemeAgencyID','6')
                                ->append('cbc:Name', 'IGV')
                                ->append('cbc:TaxTypeCode', 'VAT')
                            ->pop()
                        ->pop()
                    ->pop();
            }
            if (isset($impuestos['total_ope_inafectas']) && $impuestos['total_ope_inafectas'] > 0)
            {
                $this
                    ->append_fw('cac:TaxSubtotal')
                        ->append('cbc:TaxableAmount', $impuestos['total_ope_inafectas'])
                            ->attribute('currencyID', $data['documento']['moneda'])
                        ->append('cbc:TaxAmount', '0.00')
                            ->attribute('currencyID', $data['documento']['moneda'])
                        ->append_fw('cac:TaxCategory')
                            ->append('cbc:ID' , 'O')
                                ->attribute('schemeID' , 'UN/ECE 5305')
                                ->attribute('schemeName','Tax Category Identifier')
                                ->attribute('schemeAgencyName','United Nations Economic Commission for Europe')
                            ->append_fw('cac:TaxScheme')
                                ->append('cbc:ID', '9998')
                                    ->attribute('schemeID','UN/ECE 5153')->attribute('schemeAgencyID','6')
                                ->append('cbc:Name', 'INAFECTO')
                                ->append('cbc:TaxTypeCode', 'FRE')
                            ->pop()
                        ->pop()
                    ->pop();
            }
            if (isset($impuestos['total_ope_gratuitas']) && $impuestos['total_ope_gratuitas'] > 0)
            {
                $this
                    ->append_fw('cac:TaxSubtotal')
                        ->append('cbc:TaxableAmount', $impuestos['total_ope_gratuitas'])
                            ->attribute('currencyID', $data['documento']['moneda'])
                        ->append('cbc:TaxAmount', $impuestos['total_igv_gratuitas'])
                            ->attribute('currencyID', $data['documento']['moneda'])
                        ->append_fw('cac:TaxCategory')
                            ->append('cbc:ID' , 'Z')
                                ->attribute('schemeID' , 'UN/ECE 5305')
                                ->attribute('schemeName','Tax Category Identifier')
                                ->attribute('schemeAgencyName','United Nations Economic Commission for Europe')
                            ->append_fw('cac:TaxScheme')
                                ->append('cbc:ID', '9996')
                                    ->attribute('schemeID','UN/ECE 5153')->attribute('schemeAgencyID','6')
                                ->append('cbc:Name', 'GRA')
                                ->append('cbc:TaxTypeCode', 'FRE')
                            ->pop()
                        ->pop()
                    ->pop();
            }
            if (isset($impuestos['total_ope_exoneradas']) && $impuestos['total_ope_exoneradas'] > 0)
            {
                $this
                    ->append_fw('cac:TaxSubtotal')
                        ->append('cbc:TaxableAmount', $impuestos['total_ope_exoneradas'])
                            ->attribute('currencyID', $data['documento']['moneda'])
                        ->append('cbc:TaxAmount', '0.00')
                            ->attribute('currencyID', $data['documento']['moneda'])
                        ->append_fw('cac:TaxCategory')
                            ->append('cbc:ID' , 'E')
                                ->attribute('schemeID' , 'UN/ECE 5305')
                                ->attribute('schemeName','Tax Category Identifier')
                                ->attribute('schemeAgencyName','United Nations Economic Commission for Europe')
                            ->append_fw('cac:TaxScheme')
                                ->append('cbc:ID', '9997')
                                    ->attribute('schemeID','UN/ECE 5153')->attribute('schemeAgencyID','6')
                                ->append('cbc:Name', 'EXO')
                                ->append('cbc:TaxTypeCode', 'VAT')
                            ->pop()
                        ->pop()
                    ->pop();
            }
        $this->reset();

        $this->append_fw('cac:LegalMonetaryTotal');
        if (isset($data['total']['lineas'])) {
            $this->append('cbc:LineExtensionAmount', $data['total']['lineas'])
                ->attribute('currencyID', $data['documento']['moneda']);
        }
        //TOTAL PRECIO VENTA (INCLUYE IMPUESTOS)
        // if (isset($data['total']['totalPrecioVenta'])) {
        //     $this->append('cbc:TaxExclusiveAmount', $data['total']['totalPrecioVenta'])
        //         ->attribute('currencyID', $data['documento']['moneda']);
        // }
        if (isset($data['total']['pagable'])) {
            $this->append('cbc:TaxInclusiveAmount', $data['total']['pagable'])
                ->attribute('currencyID', $data['documento']['moneda']);
        }
        if (isset($data['total']['descuento'])) {
            $this->append('cbc:AllowanceTotalAmount', $data['total']['descuento'])
                ->attribute('currencyID', $data['documento']['moneda']);
        }

        if (isset($data['total']['cargo'])) {
            $this->append('cbc:ChargeTotalAmount', $data['total']['cargo'])
            ->attribute('currencyID', $data['documento']['moneda']);
        }

        if (isset($data['total']['prepagado'])) {
            $this->append('cbc:PrepaidAmount', $data['total']['prepagado'])
            ->attribute('currencyID', $data['documento']['moneda']);
        }

        if (isset($data['total']['pagable'])) {
            $this->append('cbc:PayableAmount', $data['total']['pagable'])
            ->attribute('currencyID', $data['documento']['moneda']);
        }

        foreach ($items as $idx => $item)
        {
            $this->reset()
                ->append_fw('cac:InvoiceLine');

            // DATOS DEL ITEM
            // IDENTIFICADOR
            $this->append('cbc:ID', $item['item'])
            // CANTIDAD DE ARTICULOS Y UNIDAD DE MEDIDA
                ->append('cbc:InvoicedQuantity', $item['cantidad'])
                    ->attribute('unitCode', $item['unidad_medida'])
                    ->attribute('unitCodeListAgencyID' , 'United Nations Economic Commission for Europe')
                    ->attribute('unitCodeListID' , 'UN/ECE rec 20')
            // VALOR DE VENTA SIN IMPUESTOS CONSIDERANDO DESCUENTO
                ->append('cbc:LineExtensionAmount', $item['valor_venta'])
                    ->attribute('currencyID', $data['documento']['moneda']);

            // PRECIOS UNITARIOS
            if (isset($item['precio_unitario']) && $item['precio_unitario'] > 0)
            {
                $this->
                    append_fw('cac:PricingReference')
                        // CODIGO 01 - Valor de venta unitario
                        ->append_fw('cac:AlternativeConditionPrice')
                            ->append('cbc:PriceAmount', $item['precio_unitario'])
                                ->attribute('currencyID', $data['documento']['moneda'])
                            ->append('cbc:PriceTypeCode', '01')
                                ->attribute('listAgencyName','PE:SUNAT')
                                ->attribute('listName','Tipo de Precio')//SUNAT:Indicador de Tipo de Precio
                                ->attribute('listURI','urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo16')
                        ->pop()
                    ->pop();
            }

            // CODIGO 02 - Valor de mercado/referencial en caso de operaciones no onerosas
            if (isset($item['precio_referencial']) && $item['precio_referencial'] > 0)
            {
                $this->
                    append_fw('cac:PricingReference')
                        ->append_fw('cac:AlternativeConditionPrice')
                            ->append('cbc:PriceAmount', $item['precio_referencial'])
                                ->attribute('currencyID', $data['documento']['moneda'])
                            ->append('cbc:PriceTypeCode', '02')
                                ->attribute('listAgencyName','PE:SUNAT')
                                ->attribute('listName','Tipo de Precio')//SUNAT:Indicador de Tipo de Precio
                                ->attribute('listURI','urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo16')
                        ->pop()
                    ->pop();
            }

            // DESCUENTOS DEL ITEM
            if (isset($item['descuento']['importe']) && $item['descuento']['importe'] > 0)
            {
                $this
                    ->append_fw('cac:AllowanceCharge')
                    ->append('cbc:ChargeIndicator', "false") //Indicador del cargo/descuento del ítem
                    ->append('cbc:AllowanceChargeReasonCode', $item['descuento']['codigo']) //Código del cargo/descuento del ítem (Catálogo 53)
                    ->append('cbc:MultiplierFactorNumeric', $item['descuento']['factor']) //Factor del cargo/descuento del ítem
                    ->append('cbc:Amount', $item['descuento']['importe']) //Monto del cargo/descuento del ítem
                        ->attribute('currencyID', $data['documento']['moneda'])
                    ->append('cbc:BaseAmount', $item['descuento']['total_base_igv']) //Monto de base del cargo/descuento del ítem
                        ->attribute('currencyID', $data['documento']['moneda'])
                    ->pop();
            }

            // IMPUESTOS
            if(isset($item['codigo_afectacion']) && $item['codigo_afectacion'] == 10)
            {
                // IGV CODIGO 1000, VAT
                $this->append_fw('cac:TaxTotal')
                    ->append('cbc:TaxAmount', $item['total_igv'])
                        ->attribute('currencyID', $data['documento']['moneda'])
                        ->append_fw('cac:TaxSubtotal')
                            ->append('cbc:TaxableAmount', $item['valor_venta'])
                                ->attribute('currencyID', $data['documento']['moneda'])
                            ->append('cbc:TaxAmount', $item['total_igv'])
                                ->attribute('currencyID', $data['documento']['moneda'])
                            ->append_fw('cac:TaxCategory')
                                ->append('cbc:ID' , 'S')
                                    ->attribute('schemeAgencyName','United Nations Economic Commission for Europe')
                                    ->attribute('schemeID' , 'UN/ECE 5305')
                                    ->attribute('schemeName','Tax Category Identifier')
                                ->append('cbc:Percent', $item['porcentaje_igv'])
                                ->append('cbc:TaxExemptionReasonCode', $item['codigo_afectacion'])
                                    ->attribute('listAgencyName','PE:SUNAT')
                                    ->attribute('listName','Afectacion del IGV')//SUNAT:Codigo de Tipo de Afectación del IGV
                                    ->attribute('listURI','urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo07')
                                ->append_fw('cac:TaxScheme')
                                    ->append('cbc:ID', '1000')
                                        ->attribute('schemeName','Codigo de tributos')
                                        ->attribute('schemeAgencyName','PE:SUNAT')
                                        ->attribute('schemeURI','urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo05')
                                    ->append('cbc:Name', 'IGV')
                                    ->append('cbc:TaxTypeCode', 'VAT')
                                ->pop()
                            ->pop()
                        ->pop()
                    ->pop();
            }
            // ISC CODIGO 2000,
            if (isset($item['total_isc']) && $item['total_isc'] > 0)
            {
                $this
                    ->append_fw('cac:TaxTotal')
                        ->append('cbc:TaxAmount', $item['total_isc'])
                            ->attribute('currencyID', $data['documento']['moneda'])
                        ->append_fw('cac:TaxSubtotal')
                            ->append('cbc:TaxAmount', $item['total_isc'])
                                ->attribute('currencyID', $data['documento']['moneda'])
                            ->append_fw('cac:TaxCategory')
                                ->append('cbc:TierRange', $item['isc']['codigo']) //Por definir
                                ->append_fw('cac:TaxScheme')
                                    ->append('cbc:ID', '2000')
                                    ->append('cbc:Name', 'ISC')
                                    ->append('cbc:TaxTypeCode', 'EXC')
                                ->pop()
                            ->pop()
                        ->pop()
                    ->pop();
            }
            //TOTAL GRATUITAS
            $arrayGratuitas = [11, 12, 13, 14, 15, 16, 21, 31, 32, 33, 34, 35, 36];
            if(isset($item['codigo_afectacion']) && in_array($item['codigo_afectacion'], $arrayGratuitas))
            {
                // IGV CODIGO 1000, VAT
                $this->append_fw('cac:TaxTotal')
                    ->append('cbc:TaxAmount', $item['total_igv'])
                        ->attribute('currencyID', $data['documento']['moneda'])
                        ->append_fw('cac:TaxSubtotal')
                            ->append('cbc:TaxableAmount', $item['valor_venta'])
                                ->attribute('currencyID', $data['documento']['moneda'])
                            ->append('cbc:TaxAmount', $item['total_igv'])
                                ->attribute('currencyID', $data['documento']['moneda'])
                            ->append_fw('cac:TaxCategory')
                                ->append('cbc:ID' , 'Z')
                                    ->attribute('schemeAgencyName','United Nations Economic Commission for Europe')
                                    ->attribute('schemeID' , 'UN/ECE 5305')
                                    ->attribute('schemeName','Tax Category Identifier')
                                ->append('cbc:Percent', $item['porcentaje_igv'])
                                ->append('cbc:TaxExemptionReasonCode', $item['codigo_afectacion'])
                                    ->attribute('listAgencyName','PE:SUNAT')
                                    ->attribute('listName','Afectacion del IGV')//SUNAT:Codigo de Tipo de Afectación del IGV
                                    ->attribute('listURI','urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo07')
                                ->append_fw('cac:TaxScheme')
                                    ->append('cbc:ID', '9996')
                                        ->attribute('schemeName','Codigo de tributos')
                                        ->attribute('schemeAgencyName','PE:SUNAT')
                                        ->attribute('schemeURI','urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo05')
                                    ->append('cbc:Name', 'GRA')
                                    ->append('cbc:TaxTypeCode', 'FRE')
                                ->pop()
                            ->pop()
                        ->pop()
                    ->pop();
            }
            if(isset($item['codigo_afectacion']) && $item['codigo_afectacion'] == 20)
            {
                // IGV CODIGO 1000, VAT
                $this->append_fw('cac:TaxTotal')
                    ->append('cbc:TaxAmount', $item['total_igv'])
                        ->attribute('currencyID', $data['documento']['moneda'])
                        ->append_fw('cac:TaxSubtotal')
                            ->append('cbc:TaxableAmount', $item['valor_venta'])
                                ->attribute('currencyID', $data['documento']['moneda'])
                            ->append('cbc:TaxAmount', $item['total_igv'])
                                ->attribute('currencyID', $data['documento']['moneda'])
                            ->append_fw('cac:TaxCategory')
                                ->append('cbc:ID' , 'E')
                                    ->attribute('schemeAgencyName','United Nations Economic Commission for Europe')
                                    ->attribute('schemeID' , 'UN/ECE 5305')
                                    ->attribute('schemeName','Tax Category Identifier')
                                ->append('cbc:Percent', $item['porcentaje_igv'])
                                ->append('cbc:TaxExemptionReasonCode', $item['codigo_afectacion'])
                                    ->attribute('listAgencyName','PE:SUNAT')
                                    ->attribute('listName','Afectacion del IGV')//SUNAT:Codigo de Tipo de Afectación del IGV
                                    ->attribute('listURI','urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo07')
                                ->append_fw('cac:TaxScheme')
                                    ->append('cbc:ID', '9997')
                                        ->attribute('schemeName','Codigo de tributos')
                                        ->attribute('schemeAgencyName','PE:SUNAT')
                                        ->attribute('schemeURI','urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo05')
                                    ->append('cbc:Name', 'EXO')
                                    ->append('cbc:TaxTypeCode', 'VAT')
                                ->pop()
                            ->pop()
                        ->pop()
                    ->pop();
            }
            if(isset($item['codigo_afectacion']) && $item['codigo_afectacion'] == 30)
            {
                // IGV CODIGO 1000, VAT
                $this->append_fw('cac:TaxTotal')
                    ->append('cbc:TaxAmount', $item['total_igv'])
                        ->attribute('currencyID', $data['documento']['moneda'])
                        ->append_fw('cac:TaxSubtotal')
                            ->append('cbc:TaxableAmount', $item['valor_venta'])
                                ->attribute('currencyID', $data['documento']['moneda'])
                            ->append('cbc:TaxAmount', $item['total_igv'])
                                ->attribute('currencyID', $data['documento']['moneda'])
                            ->append_fw('cac:TaxCategory')
                                ->append('cbc:ID' , 'O')
                                    ->attribute('schemeAgencyName','United Nations Economic Commission for Europe')
                                    ->attribute('schemeID' , 'UN/ECE 5305')
                                    ->attribute('schemeName','Tax Category Identifier')
                                ->append('cbc:Percent', $item['porcentaje_igv'])
                                ->append('cbc:TaxExemptionReasonCode', $item['codigo_afectacion'])
                                    ->attribute('listAgencyName','PE:SUNAT')
                                    ->attribute('listName','Afectacion del IGV')//SUNAT:Codigo de Tipo de Afectación del IGV
                                    ->attribute('listURI','urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo07')
                                ->append_fw('cac:TaxScheme')
                                    ->append('cbc:ID', '9998')
                                        ->attribute('schemeName','Codigo de tributos')
                                        ->attribute('schemeAgencyName','PE:SUNAT')
                                        ->attribute('schemeURI','urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo05')
                                    ->append('cbc:Name', 'INA')
                                    ->append('cbc:TaxTypeCode', 'FRE')
                                ->pop()
                            ->pop()
                        ->pop()
                    ->pop();
            }
            // DATOS DEL ITEM
            $this->append_fw('cac:Item');

            // DESCRIPCION DETALLADA
            $this->append('cbc:Description', $item['nombre_producto']);

            if (isset($item['codigo_producto'])) {
                // CODIGO DEL ARTICULO
                $this->append_fw('cac:SellersItemIdentification')
                    ->append('cbc:ID', $item['codigo_producto'])
                    ->pop();
            }
            $this->pop();

            // PRECIO UNITARIO SIN CONSIDERAR IGV NI DESCUENTOS
            $this->append_fw('cac:Price')
                    ->append('cbc:PriceAmount', $item['valor_unitario'])
                        ->attribute('currencyID', $data['documento']['moneda'])
                    ->pop()
                ->pop();
        }
    }

}
