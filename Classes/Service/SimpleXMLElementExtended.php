<?php

declare(strict_types=1);

namespace Ayacoo\Xliff\Service;

// https://stackoverflow.com/questions/6260224/how-to-write-cdata-using-simplexmlelement
use SimpleXMLElement;

class SimpleXMLElementExtended extends SimpleXMLElement
{
    /**
     * Adds a child with $value inside CDATA
     * @param string $name
     * @param string $value
     * @return SimpleXMLElementExtended|null
     */
    public function addChildWithCDATA(string $name, string $value = ''): ?SimpleXMLElementExtended
    {
        $new_child = $this->addChild($name);
        if ($new_child !== NULL) {
            $node = dom_import_simplexml($new_child);
            $no = $node->ownerDocument;
            $node->appendChild($no->createCDATASection($value));
        }

        return $new_child;
    }
}
