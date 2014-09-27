<?php

namespace UniMapper\PlantUml;

use UniMapper\NamingConvention as UNC,
    UniMapper\Reflection;

class Generator
{

    private $entities = [];

    public function add(Reflection\Entity $entity)
    {
        $name = UNC::classToName($entity->getClassName(), UNC::$entityMask);
        $this->entities[$name] = $entity;

        foreach ($entity->getRelated() as $related) {

            $name = UNC::classToName($related->getClassName(), UNC::$entityMask);
            if (!isset($this->entities[$name])) {
                $this->add($related);
            }
        }
    }

    public function generate()
    {
        $code = "";

        foreach ($this->entities as $name => $entity) {

            $code .= "class " . $name . "\n";
            foreach ($entity->getProperties() as $property) {

                if ($property->isAssociation()) {
                    $code .= $name
                        . " --> "
                        . UNC::classToName($property->getAssociation()->getTargetReflection()->getClassName(), UNC::$entityMask)
                        . " : " . (new \ReflectionClass($property->getAssociation()))->getShortName()
                        . "\n";
                }

                if ($property->isTypeBasic() || $property->getType() === Reflection\Entity\Property::TYPE_DATETIME) {
                    $type = $property->getType();
                } else {

                    if ($property->isTypeCollection()) {
                        $type = UNC::classToName($property->getType()->getEntityReflection()->getClassName(), UNC::$entityMask) . "[]";
                    } else {
                        $type = UNC::classToName($property->getType()->getClassName(), UNC::$entityMask);
                    }
                }

                $code .= $name . " : " . $property->getName() . " : " . $type . " \n";
            }
        }

        return $code;
    }

    public function getUrlCode($code)
    {
        return $this->encode64(gzdeflate(utf8_encode($code), 9));
    }

    private function encode6bit($b)
    {
        if ($b < 10) {
            return chr(48 + $b);
        }
        $b -= 10;
        if ($b < 26) {
            return chr(65 + $b);
        }
        $b -= 26;
        if ($b < 26) {
            return chr(97 + $b);
        }
        $b -= 26;
        if ($b == 0) {
            return '-';
        }
        if ($b == 1) {
            return '_';
        }
        return '?';
    }

    private function append3bytes($b1, $b2, $b3)
    {
        $c1 = $b1 >> 2;
        $c2 = (($b1 & 0x3) << 4) | ($b2 >> 4);
        $c3 = (($b2 & 0xF) << 2) | ($b3 >> 6);
        $c4 = $b3 & 0x3F;
        $r = "";
        $r .= $this->encode6bit($c1 & 0x3F);
        $r .= $this->encode6bit($c2 & 0x3F);
        $r .= $this->encode6bit($c3 & 0x3F);
        $r .= $this->encode6bit($c4 & 0x3F);
        return $r;
    }

    private function encode64($c)
    {
        $str = "";
        $len = strlen($c);
        for ($i = 0; $i < $len; $i+=3) {
            if ($i + 2 == $len) {
                $str .= $this->append3bytes(ord(substr($c, $i, 1)), ord(substr($c, $i + 1, 1)), 0);
            } else if ($i + 1 == $len) {
                $str .= $this->append3bytes(ord(substr($c, $i, 1)), 0, 0);
            } else {
                $str .= $this->append3bytes(ord(substr($c, $i, 1)), ord(substr($c, $i + 1, 1)), ord(substr($c, $i + 2, 1)));
            }
        }
        return $str;
    }

}
