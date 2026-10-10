<?php
/**
 * Clase para Generar Resumen Diario en Formato XML
 */
//require_once 'ubl/UBLNamespaces.php';
//require_once 'ubl/UBLSunat.php';

$dir = dirname(dirname(__FILE__));
require_once $dir.'/ubl/UBLNamespaces.php';
require_once $dir.'/ubl/UBLSunat.php';

class ResumenDiarioXML extends UBLSunat
{
	public function __construct($data, $dsSignature = false)
	{
		$this->namespaces = UBLNamespaces::Summary();
        $this->file_name  = $data['emisor']['documento']['numero'] . '-RC-' . $data['documento']['numero'];
        $this->dom  = new DOMDocument('1.0', 'iso-8859-1');
        $this->root = $this->dom->createElementNS(UBLNamespaces::$xmlnsSummaryDocuments, 'SummaryDocuments');

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
            //Version del UBL utilizado para establecer el formato XML
            ->append('cbc:UBLVersionID', '2.0')
            //- Version de la estructura del documento
            ->append('cbc:CustomizationID', '1.1')
            ->reset()
            //Identificador del resumen
            ->append('cbc:ID', 'RC-' . $data['documento']['numero'])
            //Fecha de generacion del resumen
            ->append_fix_date('cbc:ReferenceDate', $data['documento']['fecha_referencia'])
            //- Fecha de emision de los documentos
            ->append_fix_date('cbc:IssueDate', $data['documento']['fecha_emision'])
            //datos del firmante
            ->append_fw('cac:Signature')
            ->append('cbc:ID', 'IDSignKG')
            ->append_fw('cac:SignatoryParty')
            ->append_fw('cac:PartyIdentification')
            ->append('cbc:ID', $data['emisor']['documento']['numero'])->pop()
            ->append_fw('cac:PartyName')->append('cbc:Name', $data['emisor']['datos']['razon_social'], true)->pop()
            ->pop()
            ->append_fw('cac:DigitalSignatureAttachment')->append_fw('cac:ExternalReference')->append('cbc:URI', '#signatureKG')->pop(2)
            ->reset()
            //datos del emisor
            ->append_fw('cac:AccountingSupplierParty')
            //Numero de RUC
            ->append('cbc:CustomerAssignedAccountID', $data['emisor']['documento']['numero'])
            //Tipo de documento de identidad - Catalogo No. 06
            ->append('cbc:AdditionalAccountID', $data['emisor']['documento']['tipo'])
            ->append_fw('cac:Party')
            ->append_fw('cac:PartyLegalEntity')
            //Apellidos y nombres o denominacion o razon social
            ->append('cbc:RegistrationName', $data['emisor']['datos']['razon_social'], true)
            ->reset();

            foreach ($data['items'] as $i => $item)
            {
            	$this
            		->append_fw('sac:SummaryDocumentsLine')
            		->append('cbc:LineID', $item['id'])
            		//Tipo de documento - Catalogo No. 01
	                ->append('cbc:DocumentTypeCode', $item['rango']['tipo'])
	                //Serie y número de comprobante 
	                	->append('cbc:ID', $item['rango']['serie'].'-'.$item['rango']['numero'])//'B1A5-1101001'
	                ->append_fw('cac:AccountingCustomerParty')
	                	//Numero de documento de identidad
	                	->append('cbc:CustomerAssignedAccountID', $item['cliente']['numero'])//'10098237761'
	                	//Tipo de documento de identidad - Catalogo No. 06
	                	->append('cbc:AdditionalAccountID', $item['cliente']['tipo'])
	                	->pop();
	                
	                if (isset($item['boletas']) && !empty($item['boletas']))
	                {
						if($item['boletas']['tipo'] != "" && $item['boletas']['numero'] != ""){
	                	$this
		                	->append_fw('cac:BillingReference')
			                	->append_fw('cac:InvoiceDocumentReference')
			                		//Serie y numero de comprobante modificado
			                		->append('cbc:ID', $item['boletas']['serie'].'-'.$item['boletas']['numero'])
			                		//Tipo de comprobante modificado - Catalogo No. 01
			                		->append('cbc:DocumentTypeCode', $item['boletas']['tipo'])
			                	->pop()
			                ->pop();
						}
	                }

				$this
					->append_fw('cac:Status')
	                	//(Codigo de operacion del item - catalogo No. 19)
	                	->append('cbc:ConditionCode', $item['estado']['codigo'])
	                ->pop()
	                //Importe total de la venta, cesion en uso o del servicio prestado
	                // ->append('sac:DocumentSerialID', $item['rango']['serie'])
	                // ->append('sac:StartDocumentNumberID', $item['id'])
	                // ->append('sac:EndDocumentNumberID', $item['id'])
	                ->append('sac:TotalAmount', $item['total']['venta'])->attribute('currencyID', $item['rango']['moneda']);
                //Total valor de venta - operaciones gravadas
                //Total valor de venta - operaciones exoneradas
                //Total valor de venta - operaciones inafectas
                foreach ($item['montos'] as $m => $monto)
                {
	                if (is_null($monto['valor'])) {
	                    continue;
	                }

	                $this
	                    ->append_fw('sac:BillingPayment')
	                    ->append('cbc:PaidAmount', $monto['valor'])->attribute('currencyID', $item['rango']['moneda'])
	                    ->append('cbc:InstructionID', $monto['codigo'])
	                    ->pop();
	            }

	            //Importe total de sumatoria otros cargos del item
	            foreach ($item['cargos'] as $c => $cargo)
	            {
	                if (is_null($cargo['valor'])) {
	                    continue;
	                }

	                $this
	                    ->append_fw('cac:AllowanceCharge')
	                    ->append('cbc:ChargeIndicator', $cargo['indicador'])
	                    ->append('cbc:Amount', $cargo['valor'])->attribute('currencyID', $item['rango']['moneda'])
	                    ->pop();
	            }

	            foreach ($item['impuestos'] as $t => $impuesto)
	            {
	                if (is_null($impuesto['monto'])) {
	                    continue;
	                }

	                $this
	                    ->append_fw('cac:TaxTotal')
	                    ->append('cbc:TaxAmount', $impuesto['monto'])->attribute('currencyID', $item['rango']['moneda'])
	                    ->append_fw('cac:TaxSubtotal')
	                    ->append('cbc:TaxAmount', $impuesto['monto'])->attribute('currencyID', $item['rango']['moneda'])
	                    ->append_fw('cac:TaxCategory')
	                    ->append_fw('cac:TaxScheme')
	                    ->append('cbc:ID', $impuesto['id'])
	                    ->append('cbc:Name', $impuesto['nombre'])
	                    ->append('cbc:TaxTypeCode', $impuesto['codigo'])
	                    ->pop()
	                    ->pop()
	                    ->pop()
	                    ->pop()
	                ;
	            }

				$this->reset();
            }
	}
}
?>