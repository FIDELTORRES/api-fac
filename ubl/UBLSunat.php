<?php
/**
 *
 * Clase  abstracta UBLSunat
 */

abstract class UBLSunat
{
    public $dom = null;
    public $root;
    public $current;
    public $stack = array();
    public $recent;
    public $file_name;
    public $namespaces;

    public function attribute($name, $value)
    {
        $this->recent->setAttribute($name, $value);
        return $this;
    }

    public function append_fix_date($element, $value = null, $cdata = false)
    {
        $value1  = $value;
        $matches = null;
        if (preg_match('@^(..)/(..)/(....)$@', $value, $matches)) {
            $value1 = "$matches[3]-$matches[2]-$matches[1]";
        }
        $this->append($element, $value1, $cdata);
        return $this;
    }

    public function append_fix_document_number($element, $value = null, $cdata = false)
    {
        $value1  = $value;
        $matches = null;
        if (preg_match('@^(.+)-(.+)$@', $value, $matches)) {
            $serie  = $matches[1];
            $numero = $matches[2];
            while (strlen($serie) < 4) {
                $serie = '0' . $serie;
            }
            $value1 = "$serie-$numero";
        }
        $this->append($element, $value1, $cdata);
        return $this;
    }


    public function append($element, $value = null, $cdata = false)
    {
        $node = null;

        // Asegurarse de que $value nunca sea null (convertir a string vacío)
        $safeValue = $value ?? '';

        if ($cdata) {
            $node = $this->dom->createElement($element);
            $data = $this->dom->createCDATASection($safeValue);
            $node->appendChild($data);
        } else {
            $node = $this->dom->createElement($element, $safeValue);
        }

        $this->current->appendChild($node);
        $this->recent = $node;
        return $this;
    }

    public function append_fw($element, $value = null, $cdata = false)
    {
        $this->append($element, $value, $cdata);
        $this->push();
        return $this;
    }

    public function append_nnv($element, $value = null, $cdata = false)
    {
        // Sugerenia de IA
        //if (!is_null($value) && $value !== '') {
        //    $this->append($element, $value, $cdata);
        //}
        if (!!$value) {
            $this->append($element, $value, $cdata);
        }
        return $this;
    }

    public function push()
    {
        $this->stack[] = $this->current;
        $this->current = $this->recent;
    }

    public function pop($times = 1)
    {
        for ($i = 0; $i < $times; $i++) {
            $this->current = array_pop($this->stack);
        }
        return $this;
    }

    public function reset()
    {
        $this->stack   = array();
        $this->current = $this->root;
        return $this;
    }

    public function save($file = null)
    {
        if (!$file) {
            return $this->dom->saveXML();
        } else {
            return $this->dom->save($file);
        }
    }

    //abstract public static function InvoiceSunat($data, $signature = false);
}
