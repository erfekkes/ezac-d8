<?php

namespace Drupal\ezac_starts\Model;

use Drupal\ezac\Model\EzacStorage;

/**
 * @file Ezac/EzacStart.php
 * The EZAC class definitions for the starts table
 *
 * @author Evert Fekkes evert@efekkes.nl
 */

/**
 * Provides the implementation of the DloStart class
 */
class EzacStart extends EzacStorage
{

    // Define Starts categories (kat field)
    public static $startSoort = array(
        '' => 'Algemeen',
        'CLUB' => 'Rekening club',
        'PASS' => 'Passagier',
        'DONA' => 'Donateur',
        '2E' => 'Tweede inzittende',
    );

    public static $startMethode = array(
    'L' => 'Lier',
    'M' => 'Motor',
    'S' => 'Sleep',
    'B' => 'Bungee',
    );

    //Define Starts fields
    public static $fields = array(
        'id' => 'Record ID (uniek, auto_increment)',
        'datum' => 'Datum',
        'registratie' => 'Registratie',
        'gezagvoerder' => 'Gezagvoerder',
        'tweede' => 'Tweede inzittende',
        'soort' => 'Soort start',
        'startmethode' => 'Startmethode',
        'start' => 'Start tijd',
        'landing' => 'Landing tijd',
        'duur' => 'Vluchtduur',
        'instructie' => 'Instructie vlucht',
        'opmerking' => 'Opmerking',
    );

    // define the fields for the starts table
    public $id = 0;
    public $datum = '';
    public $registratie = '';
    public $gezagvoerder = '';
    public $tweede = '';
    public $soort = '';
    public $startmethode = '';
    public $start = '';
    public $landing = '';
    public $duur = '';
    public $instructie = FALSE;
    public $opmerking = '';

    /**
     * constructor for EzacStart
     * @param null $id
     */
    public function __construct($id = NULL)
    {
        if (isset($id)) {
            $this->id = $id;
            $this->ezacRead('starts');
        }
    }

    /**
     * create - Create starts record
     *
     * @return int ID of record created
     *   ID of record created
     */
    public function create()
    {
        $this->id = $this->ezacCreate('starts');
        return $this->id;
    }

  /**
   * read - Reads record from the starts table
   *
   * @param int id
   *
   * @return null
   */
    public function read($id = NULL)
    {
      $this->id = $id;
      //@todo className parameter is overbodig
      $this->ezacRead('starts');
      if ($this->id == null) {
        // read failed
        return null;
      }
      // object is put in $this
    }

    /**
     * update - Updates record in the starts table
     *
     * @return int
     *   records_updated
     */
    public function update()
    {
        // build $condition
        return $this->ezacUpdate('starts');
    }

    /**
     * delete - Deletes records from the starts table
     *
     * @return int
     *   records_deleted
     */
    public function delete()
    {
        return $this->ezacDelete('starts');
    }

    /***
     * counter - Counts the number of starts records
     *
     * @param array
     *   $condition
     * @return int
     *   number of records
     */
    public static function counter($condition)
    {
        return EzacStorage::ezacCount("starts", $condition);
    }

    /***
     * index - Gets the index of starts records
     *
     * @param null $condition
     * @param string $field
     * @param string|array $sortkey
     * @param string $sortdir
     * @param $from
     * @param $range
     * @param bool $unique
     * @return array of id values
     */
    public static function index($condition = NULL, $field = 'id', $sortkey = 'datum', $sortdir = 'ASC', $from = NULL, $range = NULL, $unique = FALSE)
    {
        return EzacStorage::ezacIndex('starts', $condition, $field, $sortkey, $sortdir, $from, $range, $unique);
    }

}
  
