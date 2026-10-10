<?php
/**
 
 * Clase para Generar Nota de Crédito en Formato XML
 */
//require_once 'ubl/UBLNamespaces.php';
//require_once 'ubl/UBLSunat.php';
 
$dir = dirname(dirname(__FILE__));
require_once $dir.'/ubl/UBLNamespaces.php';
require_once $dir.'/ubl/UBLSunat.php';



class NotaCreditoXML extends UBLSunat
{

    public function __construct($data, $dsSignature = false)
    {
        $notas     = $data['notas'];
        $impuestos = $data['impuestos'];
        $items     = $data['items'];
        $facturas  = $data['facturas'];

        $this->namespaces = UBLNamespaces::Note();

        $this->dom = new DOMDocument('1.0', 'iso-8859-1');

        $this->root = $this->dom->createElementNS(UBLNamespaces::$xmlnsCreditNote, 'CreditNote');

        foreach ($this->namespaces as $pfx => $namespace) {
            $this->root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . $pfx, $namespace);
        }

        $this->dom->appendChild($this->root);
        $this->current = $this->root;
        $this->append_fw('ext:UBLExtensions');

        // En esta zona va el certificado digital  - (Firma Digital).
        if ($dsSignature) {
            $this
                ->append_fw('ext:UBLExtension')
                    ->append_fw('ext:ExtensionContent');
        }

        $this
            ->reset()
            //cabecera UBL
            ->append('cbc:UBLVersionID', '2.1')
            ->append('cbc:CustomizationID', '2.0')
            //datos del documento
            ->append('cbc:ID', $data['documento']['numero'])
            ->append('cbc:IssueDate', $data['documento']['fecha_emision'])
            ->append('cbc:IssueTime' , $data['documento']['hora_emision']);
        foreach ($notas as $nota) {
            $this
            ->append('cbc:Note' , $nota['valor'])
                ->attribute('languageLocaleID' , $nota['codigo']);
        }
        $this
            ->append('cbc:DocumentCurrencyCode', $data['documento']['moneda']);

        foreach ($facturas as $idx => $factura) {
            $this
                ->append_fw('cac:DiscrepancyResponse')
                    ->append('cbc:ReferenceID', $factura['serie_numero'])
                    ->append('cbc:ResponseCode', $factura['motivo']['codigo'])
                    ->append('cbc:Description', $factura['motivo']['descripcion'], true)
                ->pop();
        }

        foreach ($facturas as $idx => $factura) {
            $this
                ->append_fw('cac:BillingReference')
                    ->append_fw('cac:InvoiceDocumentReference')
                        ->append('cbc:ID', $factura['serie_numero'])
                        ->append('cbc:DocumentTypeCode', $factura['tipo'])
                    ->pop()
                ->pop();
        }

        $this
        //datos del firmante
        ->append_fw('cac:Signature')
            ->append('cbc:ID', 'IDSignKG')
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
        // ->append('cbc:CustomerAssignedAccountID', $data['emisor']['documento']['numero'])
        // ->append('cbc:AdditionalAccountID', $data['emisor']['documento']['tipo'])
            ->append_fw('cac:Party')
                ->append_fw('cac:PartyIdentification')
                    ->append('cbc:ID' , $data['emisor']['documento']['numero'])
                        ->attribute('schemeID' , $data['emisor']['documento']['tipo'])
                        ->attribute('schemeName' , 'Documento de Identidad')
                        ->attribute('schemeAgencyName' , 'PE:SUNAT')
                        ->attribute('schemeURI' , 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06')
                ->pop();
                if (isset($data['emisor']['datos']['nombre_comercial']) && !empty($data['emisor']['datos']['nombre_comercial']))
                {
                    $this
                        ->append_fw('cac:PartyName')
                        ->append('cbc:Name', $data['emisor']['datos']['nombre_comercial'], true)
                        ->pop();
                }
                $this
                    ->append_fw('cac:PartyLegalEntity')
                        ->append('cbc:RegistrationName', $data['emisor']['datos']['razon_social'], true)
                        ->append_fw('cac:RegistrationAddress')
                            ->append('cbc:AddressTypeCode' , $data['emisor']['ubicacion']['anexo'])
                        ->pop()
                    ->pop()
                ->pop()
            ->pop()
        // ->pop()
        ->reset()
        ->append_fw('cac:AccountingCustomerParty')
            ->append_fw('cac:Party')
                ->append_fw('cac:PartyIdentification')
                    ->append('cbc:ID' , $data['cliente']['documento']['numero'])
                        ->attribute('schemeID' , $data['cliente']['documento']['tipo'])
                        ->attribute('schemeName' , 'Documento de Identidad')
                        ->attribute('schemeAgencyName' , 'PE:SUNAT')
                        ->attribute('schemeURI' , 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06')
                ->pop();
                if (isset($data['cliente']['datos']['nombre_comercial']) && !empty($data['cliente']['datos']['nombre_comercial']))
                {
                    $this
                        ->append_fw('cac:PartyName')
                        ->append('cbc:Name', $data['cliente']['datos']['nombre_comercial'], true)
                        ->pop();
                }
                $this
                    ->append_fw('cac:PartyLegalEntity')
                        ->append('cbc:RegistrationName', $data['cliente']['datos']['razon_social'], true)
                        // ->append_fw('cac:RegistrationAddress')
                        //     ->append('cbc:AddressTypeCode' , '0001')
                        // ->pop()
                    ->pop()
                ->pop()
            ->pop()
        ->reset();
        // ->pop();

        //AGREGAR DATOS DE ANTICIPOS DEVENGADOS
        $this
            ->append_fw('cac:TaxTotal')
                ->append('cbc:TaxAmount' , $data['total']['total_impuestos'])
                    ->attribute('currencyID', $data['documento']['moneda']);
        if (isset($impuestos['total_ope_gravadas']) && $impuestos['total_ope_gravadas'] > 0)
        {
            $this
                ->append_fw('cac:TaxSubtotal')
                    ->append('cbc:TaxableAmount' , $impuestos['total_ope_gravadas'])
                        ->attribute('currencyID', $data['documento']['moneda'])
                    ->append('cbc:TaxAmount', $impuestos['total_igv_gravadas'])
                        ->attribute('currencyID', $data['documento']['moneda'])
                    ->append_fw('cac:TaxCategory')
                        ->append('cbc:ID' , 'S')
                        ->append_fw('cac:TaxScheme')
                            ->append('cbc:ID', '1000')
                                ->attribute('schemeID' , 'UN/ECE 5153')
                                ->attribute('schemeAgencyID' , '6')
                            ->append('cbc:Name', 'IGV')
                            ->append('cbc:TaxTypeCode', 'VAT')
                        ->pop()
                    ->pop()
                ->pop();
        }
		
		// INI FTI 21-06-2021
		if (isset($impuestos['total_ope_icbper']) && $impuestos['total_ope_icbper'] > 0)
        {
			$this
				->append_fw('cac:TaxSubtotal')
					//->append('cbc:TaxableAmount', $impuestos['total_ope_icbper'])
					//    ->attribute('currencyID', $data['documento']['moneda'])
	
					->append('cbc:TaxAmount', $impuestos['total_ope_icbper'])
						->attribute('currencyID', $data['documento']['moneda'])
					->append_fw('cac:TaxCategory')
						->append('cbc:ID' , 'S')
							->attribute('schemeID' , 'UN/ECE 5305')
							->attribute('schemeName','Tax Category Identifier')
							->attribute('schemeAgencyName','United Nations Economic Commission for Europe')
						->append_fw('cac:TaxScheme')
							->append('cbc:ID', '7152')
								->attribute('schemeID','UN/ECE 5153')->attribute('schemeAgencyID','6')
							->append('cbc:Name', 'ICBPER')
							->append('cbc:TaxTypeCode', 'OTH')
						->pop()
					->pop()
				->pop();
        }
		// FIN FTI 21-06-2021	
		
        if ($impuestos['total_ope_inafectas'] > 0)
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
                            ->append('cbc:Name', 'INA')
                            ->append('cbc:TaxTypeCode', 'FRE')
                        ->pop()
                    ->pop()
                ->pop();
        }
        if ($impuestos['total_ope_gratuitas'] > 0)
        {
            $this
                ->append_fw('cac:TaxSubtotal')
                    ->append('cbc:TaxableAmount', $impuestos['total_ope_gratuitas'])
                        ->attribute('currencyID', $data['documento']['moneda'])
                    ->append('cbc:TaxAmount', '0.00')
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
        if ($impuestos['total_ope_exoneradas'] > 0)
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
                ->append_fw('cac:CreditNoteLine');

            // DATOS DEL ITEM
            // IDENTIFICADOR
            $this->append('cbc:ID', $item['item'])
            // CANTIDAD DE ARTICULOS Y UNIDAD DE MEDIDA
                ->append('cbc:CreditedQuantity', $item['cantidad'])
                    ->attribute('unitCode', $item['unidad_medida'])
                // VALOR DE VENTA SIN IMPUESTOS CONSIDERANDO DESCUENTO
                ->append('cbc:LineExtensionAmount', $item['valor_venta'])
                    ->attribute('currencyID', $data['documento']['moneda']);

            // PRECIOS UNITARIOS
            $this->append_fw('cac:PricingReference');

            // CODIGO 01 - Valor de venta unitario
            if (isset($item['precio_unitario']) && $item['precio_unitario'] > 0)
            {
                $this->append_fw('cac:AlternativeConditionPrice')
                    ->append('cbc:PriceAmount', $item['precio_unitario'])
                        ->attribute('currencyID', $data['documento']['moneda'])
                    ->append('cbc:PriceTypeCode', '01')
                    ->pop();
            }
            // CODIGO 02 - Valor de mercado/referencial en caso de operaciones no onerosas
            if (isset($item['precio_referencial']) && $item['precio_referencial'] > 0)
            {
                $this->append_fw('cac:AlternativeConditionPrice')
                    ->append('cbc:PriceAmount', $item['precio_referencial'])
                        ->attribute('currencyID', $data['documento']['moneda'])
                    ->append('cbc:PriceTypeCode', '02')
                    ->pop();
            }
            $this->pop();

            // IMPUESTOS
            // IGV CODIGO 1000, VAT
            if (isset($item['codigo_afectacion']) && $item['codigo_afectacion'] == 10)
            {
                $this->append_fw('cac:TaxTotal')
                    ->append('cbc:TaxAmount', $item['total_igv'])
                        ->attribute('currencyID', $data['documento']['moneda'])
                    ->append_fw('cac:TaxSubtotal')
                        ->append('cbc:TaxableAmount' , $item['valor_venta'])
                            ->attribute('currencyID', $data['documento']['moneda'])
                        ->append('cbc:TaxAmount', $item['total_igv'])
                            ->attribute('currencyID', $data['documento']['moneda'])
                        ->append_fw('cac:TaxCategory')
                            ->append('cbc:Percent', $item['porcentaje_igv'])
                            ->append('cbc:TaxExemptionReasonCode', $item['codigo_afectacion'])
                            ->append_fw('cac:TaxScheme')
                                ->append('cbc:ID', '1000')
                                ->append('cbc:Name', 'IGV')
                                ->append('cbc:TaxTypeCode', 'VAT')
                            ->pop()
                        ->pop()
                    ->pop();
					// Inicio fidel 21-06-2021
						if(isset($item['total_icbper']) && $item['total_icbper'] > 0)
						{
						$this
						->append_fw('cac:TaxSubtotal')
                            ->append('cbc:TaxAmount', $item['total_icbper'])
                                ->attribute('currencyID', $data['documento']['moneda'])
							->append('cbc:BaseUnitMeasure', number_format($item['cantidad']))
									->attribute('unitCode',$item['unidad_medida'])
                            ->append_fw('cac:TaxCategory')
                                ->append('cbc:PerUnitAmount', $item['tasa_icbper']) //Por definir
								->attribute('currencyID',$data['documento']['moneda'])
                                ->append_fw('cac:TaxScheme')
                                    ->append('cbc:ID', '7152')
									    ->attribute('schemeName','Codigo de tributos')
                                        ->attribute('schemeAgencyName','PE:SUNAT')
                                        ->attribute('schemeURI','urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo05')
                                    ->append('cbc:Name', 'ICBPER')
                                    ->append('cbc:TaxTypeCode', 'OTH')
                                ->pop()
                            ->pop()
                        ->pop();
						}
						// Fin fidel 21-06-2021
               $this->pop();
            }

            if (isset($item['codigo_afectacion']) && $item['codigo_afectacion'] == 20)
            {
                $this->append_fw('cac:TaxTotal')
                    ->append('cbc:TaxAmount', $item['total_igv'])
                        ->attribute('currencyID', $data['documento']['moneda'])
                    ->append_fw('cac:TaxSubtotal')
                        ->append('cbc:TaxableAmount' , $item['valor_venta'])
                            ->attribute('currencyID', $data['documento']['moneda'])
                        ->append('cbc:TaxAmount', $item['total_igv'])
                            ->attribute('currencyID', $data['documento']['moneda'])
                        ->append_fw('cac:TaxCategory')
                            ->append('cbc:Percent', $item['porcentaje_igv'])
                            ->append('cbc:TaxExemptionReasonCode', $item['codigo_afectacion'])
                            ->append_fw('cac:TaxScheme')
                                ->append('cbc:ID', '9997')
                                ->append('cbc:Name', 'EXO')
                                ->append('cbc:TaxTypeCode', 'VAT')
                            ->pop()
                        ->pop()
                    ->pop()
                ->pop();
            }

            $arrayGratuitas = [11, 12, 13, 14, 15, 16, 21, 31, 32, 33, 34, 35, 36];
            if(isset($item['codigo_afectacion']) && in_array($item['codigo_afectacion'], $arrayGratuitas))
            {
                $this->append_fw('cac:TaxTotal')
                    ->append('cbc:TaxAmount', $item['total_igv'])
                        ->attribute('currencyID', $data['documento']['moneda'])
                    ->append_fw('cac:TaxSubtotal')
                        ->append('cbc:TaxableAmount' , $item['valor_venta'])
                            ->attribute('currencyID', $data['documento']['moneda'])
                        ->append('cbc:TaxAmount', $item['total_igv'])
                            ->attribute('currencyID', $data['documento']['moneda'])
                        ->append_fw('cac:TaxCategory')
                            ->append('cbc:Percent', $item['porcentaje_igv'])
                            ->append('cbc:TaxExemptionReasonCode', $item['codigo_afectacion'])
                            ->append_fw('cac:TaxScheme')
                                ->append('cbc:ID', '9996')
                                ->append('cbc:Name', 'GRA')
                                ->append('cbc:TaxTypeCode', 'FRE')
                            ->pop()
                        ->pop()
                    ->pop()
                ->pop();
            }

            if (isset($item['codigo_afectacion']) && $item['codigo_afectacion'] == 30)
            {
                $this->append_fw('cac:TaxTotal')
                    ->append('cbc:TaxAmount', $item['total_igv'])
                        ->attribute('currencyID', $data['documento']['moneda'])
                    ->append_fw('cac:TaxSubtotal')
                        ->append('cbc:TaxableAmount' , $item['valor_venta'])
                            ->attribute('currencyID', $data['documento']['moneda'])
                        ->append('cbc:TaxAmount', $item['total_igv'])
                            ->attribute('currencyID', $data['documento']['moneda'])
                        ->append_fw('cac:TaxCategory')
                            ->append('cbc:Percent', $item['porcentaje_igv'])
                            ->append('cbc:TaxExemptionReasonCode', $item['codigo_afectacion'])
                            ->append_fw('cac:TaxScheme')
                                ->append('cbc:ID', '9998')
                                ->append('cbc:Name', 'INA')
                                ->append('cbc:TaxTypeCode', 'FRE')
                            ->pop()
                        ->pop()
                    ->pop()
                ->pop();
            }

            // ISC CODIGO 2000,
            if (isset($item['isc']['monto']))
            {
                $this
                    ->append_fw('cac:TaxTotal')
                        ->append('cbc:TaxAmount', $item['isc']['monto'])
                            ->attribute('currencyID', $data['documento']['moneda'])
                        ->append_fw('cac:TaxSubtotal')
                            ->append('cbc:TaxAmount', $item['isc']['monto'])
                                ->attribute('currencyID', $data['documento']['moneda'])
                            ->append_fw('cac:TaxCategory')
                                ->append('cbc:TierRange', $item['isc']['codigo'])
                                ->append_fw('cac:TaxScheme')
                                    ->append('cbc:ID', '2000')
                                    ->append('cbc:Name', 'ISC')
                                    ->append('cbc:TaxTypeCode', 'EXC')
                                ->pop()
                            ->pop()
                        ->pop()
                    ->pop();
            }

            //DATOS DEL ITEM
            $this
                ->append_fw('cac:Item') //NUMERACION
                    ->append('cbc:Description', $item['nombre_producto']) // DESCRIPCION DETALLADA
                    ->append_fw('cac:SellersItemIdentification') // CODIGO DEL ARTICULO
                        ->append('cbc:ID', $item['codigo_producto'])
                    ->pop()
                 ->pop()
                // PRECIO UNITARIO SIN CONSIDERAR IGV NI DESCUENTOS
                ->append_fw('cac:Price')
                    ->append('cbc:PriceAmount', $item['valor_unitario'])
                        ->attribute('currencyID', $data['documento']['moneda'])
                ->pop();
        }
    }
}
