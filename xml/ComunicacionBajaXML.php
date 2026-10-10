<?php
/**
 * Clase para Generar Comunicación de Baja en Formato XML
 */
//require_once 'ubl/UBLNamespaces.php';
//require_once 'ubl/UBLSunat.php';

$dir = dirname(dirname(__FILE__));
require_once $dir.'/ubl/UBLNamespaces.php';
require_once $dir.'/ubl/UBLSunat.php';

class ComunicacionBajaXML extends UBLSunat
{

    public function __construct($data, $dsSignature = false)
    {
        $this->namespaces = UBLNamespaces::VoidedDocuments();

        $this->file_name = $data['emisor']['documento']['numero'] . '-RA-' . $data['documento']['tipo'] . '-' . $data['documento']['numero'];

        $this->dom  = new DOMDocument('1.0', 'iso-8859-1');
        $this->root = $this->dom->createElementNS(UBLNamespaces::$xmlnsVoidedDocuments, 'VoidedDocuments');

        foreach ($this->namespaces as $pfx => $namespace) {
            $this->root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . $pfx, $namespace);
        }

        $this->dom->appendChild($this->root);
        $this->current = $this->root;
        $this->append_fw('ext:UBLExtensions');

        // En esta zona va el certificado digital - (Firma Digital).
        if ($dsSignature) {
            $this
                ->append_fw('ext:UBLExtension')
                ->append_fw('ext:ExtensionContent');
        }

        $this
            ->reset()
            //cabecera UBL
            ->append('cbc:UBLVersionID', '2.0')->append('cbc:CustomizationID', '1.0')->reset()
            //datos del documento
            ->append('cbc:ID', $data['documento']['tipo'] . '-' . $data['documento']['numero'])
            ->append_fix_date('cbc:ReferenceDate', $data['documento']['fecha_referencia'])
            ->append_fix_date('cbc:IssueDate', $data['documento']['fecha_emision'])
            //datos del firmante
            ->append_fw('cac:Signature')
            ->append('cbc:ID', 'IDSignKG')
            ->append_fw('cac:SignatoryParty')
            ->append_fw('cac:PartyIdentification')->append('cbc:ID', $data['emisor']['documento']['numero'])->pop()
            ->append_fw('cac:PartyName')->append('cbc:Name', $data['emisor']['datos']['razon_social'], true)->pop()
            ->pop()
            ->append_fw('cac:DigitalSignatureAttachment')->append_fw('cac:ExternalReference')->append('cbc:URI', '#signatureKG')->pop(2)
            ->reset()
            //datos del emisor
            ->append_fw('cac:AccountingSupplierParty')
            ->append('cbc:CustomerAssignedAccountID', $data['emisor']['documento']['numero'])
            ->append('cbc:AdditionalAccountID', $data['emisor']['documento']['tipo'])
            ->append_fw('cac:Party')
            ->append_fw('cac:PartyLegalEntity')
            ->append('cbc:RegistrationName', $data['emisor']['datos']['razon_social'], true)
            ->reset();

        foreach ($data['items'] as $idx => $line) {
            $this
                ->append_fw('sac:VoidedDocumentsLine')
                ->append('cbc:LineID', $line['id'])
                ->append('cbc:DocumentTypeCode', $line['documento']['tipo'])
                ->append('sac:DocumentSerialID', $line['documento']['serie'])
                ->append('sac:DocumentNumberID', $line['documento']['numero'])
                ->append('sac:VoidReasonDescription', $line['motivo'])
                ->reset();
        }
    }
}
