<?php

namespace Drupal\ezac_vba\Model;

use Drupal\ezac\Model\EzacStorage;

/**
 * @file Ezac/EzacBevoegdheidLid.php
 * The EZAC class definitions for the vba bevoegdheden status table
 *
 * @author Evert Fekkes evert@efekkes.nl
 */

/**
 * Provides the implementation of the EzacVbaBevoegdheden class
 */
class EzacVbaBevoegdheid extends EzacStorage
{
    //Define vba bevoegdheden fields
    public static $fields = array(
      'id' => 'Record ID (uniek, auto_increment)',
      'afkorting' => 'Afkorting',
      'bevoegdheid' => 'Bevoegdheid',
      'onderdeel' => 'Onderdeel',
      'datum_aan' => 'Datum vanaf',
      'datum_uit' => 'Datum tot',
      'actief' => 'Actief',
      'instructeur' => 'Instructeur',
      'opmerking' => 'Opmerking',
      'mutatie' => 'Mutatie',
    );

    // define the fields for the vba bevoegdheden lid table
    public $id = 0;
    public $afkorting = '';
    public $onderdeel = '';
    public $bevoegdheid = '';
    public $datum_aan = '';
    public $datum_uit = '';
    public $actief = 0;
    public $instructeur = '';
    public $opmerking = '';
    public $mutatie = 0;

    /**
     * constructor for vba_bevoegdheden
     * @param null $id
     */
    public function __construct($id = NULL)
    {
        if (isset($id)) {
            $this->id = $id;
            $this->ezacRead('vba_bevoegdheden');
        }
        return $this;
    }

    /**
     * create - Create vba record
     *
     * @return int
     *   ID of record created
     */
    public function create(): ?int {

        $this->id = $this->ezacCreate('vba_bevoegdheden');
        return $this->id;
    }

    /**
     * read - Reads record from the vba table
     *
     * @param int id
     * @return object DloStart
     */
    public function read($id = NULL)
    {
      if (isset($id)) {
        $this->id = $id;
        //@todo className parameter is overbodig
        $this->ezacRead('vba_bevoegdheden');
        if ($this->id == null) {
          // read failed
          return null;
        }
        // object is put in $this
      }
      else return null;
    }

    /**
     * update - Updates record in the vba table
     *
     * @return int
     *   records_updated
     */
    public function update(): int {
        // build $condition
        return $this->ezacUpdate('vba_bevoegdheden');
    }

    /**
     * delete - Deletes records from the vba table
     *
     * @return int
     *   records_deleted
     */
    public function delete(): int {
        return $this->ezacDelete('vba_bevoegdheden');
    }

    /***
     * counter - Counts the number of vba records
     *
     * @param array
     *   $condition
     * @return int
     *   number of records
     */
    public static function counter($condition): int {
      return EzacStorage::ezacCount('vba_bevoegdheden', $condition);
    }

    /***
     * index - Gets the index of vba records
     *
     * @param null $condition
     * @param string $field
     * @param string $sortkey
     * @param string $sortdir
     * @param $from
     * @param $range
     * @param bool $unique
     * @return array of id values
     */
    public static function index($condition = NULL, $field = 'id', $sortkey = 'datum_aan', $sortdir = 'ASC', $from = NULL, $range = NULL, $unique = FALSE): array {
        return EzacStorage::ezacIndex('vba_bevoegdheden', $condition, $field, $sortkey, $sortdir, $from, $range, $unique);
    }

}
  
