<?php
namespace Drupal\ezac\Util;

use Drupal\ezacKisten\Model\EzacKist;

class getKisten
{
    /**
     * @file
     * return table with leden names
     * @param array $condition
     * @return array
     */
    public static function getLeden(array $condition = [])
    {
        if ($condition == []) {
            $condition = [
                'actief' => TRUE,
            ];
        }
        $kistenIndex = EzacKist::index($condition,'id','registratie');
        $kisten = [];
        foreach ($kistenIndex as $id) {
            $kist = (new EzacKist)->read($id);
            $kisten[$kist->registratie] = "$kist->registratie $kist->callsign ($kist->inzittenden)";
        }
        $kisten[0] = "Onbekend";
        return $kisten;
    }
}