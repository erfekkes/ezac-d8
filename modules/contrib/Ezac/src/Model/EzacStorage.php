<?php

namespace Drupal\Ezac\Model;

use Drupal;
use Drupal\Core\Database\Database;
use Exception;
use PDO;

// use Drupal\Core\Database\Connection;

// use Drupal\Core\Database\Query\PagerSelectExtender;

/**
 * Class EzacStorage.
 *
 * Generic database commands for Ezac tables
 */

/**
 * Provides the interface for the EzacStorage class
 */
class EzacStorage
{

    // define id property
    public $id = 0;

    //$config = \Drupal::config('Ezac.database');
    //Database::setActiveConnection($config->get('name');

    protected const dbName = 'Ezac';

    /**
     *
     * @param $table
     * @param array $condition
     * @return mixed
     */
    public static function ezacCount($table, $condition = array())
    {

        // use ezacIndex
        return count(self::ezacIndex($table, $condition));

        /*
        // Read all fields from a Ezac table.
        // EZAC database is outside the Drupal structure
        Database::setActiveConnection(self::dbName);
        $db = Database::getConnection();

        $select = $db->select($table); // geen alias gebruikt
        $select->fields($table);

        // Add each field and value as a condition to this query.
        foreach ($condition as $field => $test) {
            if (is_array($test)) {
                $value = $test["value"];
                $operator = $test["operator"];
                $select->condition($field, $value, $operator);
            } else $select->condition($field, $test);
        }
        // Return the result in object format.
        $record_count = $select->countQuery()->execute()->fetchField();

        // return to standard Drupal database
        Database::setActiveConnection();

        return $record_count;
        */
    } // ezacCount

    /**
     * Read index from the database using a filter array.
     *
     * @param string $table
     *   The EZAC table to be used for the database operation
     * @param array $condition
     *   The condition for the update as array (field => value) (=)
     *   alternative: field[value => value, operator => operator]
     * @param string $field
     * @param string $sortkey
     *  the field for which the read should be sorted
     * @param $sortdir string default 'ASC' sort direction
     * @param int $from for range selection default NULL
     * @param int $range default NULL the range for the query
     * @param bool $unique default FALSE, return DISTINCT results
     * @return array
     *   An array of objects containing the loaded entries if found.
     */
    public static function ezacIndex($table, $condition = NULL, $field = 'id', $sortkey = NULL, $sortdir = 'ASC', $from = NULL, $range = NULL, $unique = FALSE)
    {

        // Read unique index from a Ezac table.
        // EZAC database is outside the Drupal structure
        Database::setActiveConnection(self::dbName);
        $db = Database::getConnection();

        $select = $db->select($table, 't');
        $select->addField('t', $field);

        // Add each field and value as a condition to this query.

        /*
         * a condition entry is either a field and value
         * OR a field pointing to an array of value and operator
         * OR an array pointing to an orConditionGroup, by key "OR"
         */

        foreach ((array)$condition as $field => $test) {
            // condition can be a simple field => value pair for EQUALS (default test)
            //   or contain value and operator keys for other tests
            if (is_array($test)) {
              // combined condition with value(s) and operator
              if ($field == 'OR') {
                // test is part of an orGroup
                $orGroup = $select->orConditionGroup();
                foreach ($test as $field2 => $test2) {
                  if (is_array($test2)) {
                    // combined condition
                    $value = $test2["value"];
                    $operator = $test2["operator"];
                    $orGroup->condition($field2, $value, $operator);
                  }
                  else {
                    //single condition
                    $orGroup->condition($field2, $test2, '=');
                  }
                } // orGroup element
                $select->condition($orGroup);
              } //orGroup
              else {
                // combined condition
                $value = $test["value"];
                $operator = $test["operator"];
                $select->condition($field, $value, $operator);
              }
              // simple condition
            } else $select->condition($field, $test);
        }
        // sort the result
        if (isset($sortkey)) {
            $select->orderBy($sortkey, $sortdir);
        }
        // add range for pager
        if (isset($range)) {
            $select->range($from, $range);
        }
        // Return the result in array format.
        if ($unique) $index = $select->distinct()->execute()->fetchCol();
        else $index = $select->execute()->fetchCol();

        // return to standard Drupal database
        Database::setActiveConnection();

        return (array)$index;

    } // ezacIndex

    /**
     * Insert a record in the EZAC database
     *
     * @param string $table
     *   The EZAC table to be used for the database operation
     * @return int
     *   The id of the inserted record
     *
     */
    public function ezacCreate($table)
    {

        // EZAC database is outside the Drupal structure
        // select EZAC database outside Drupal structure
        Database::setActiveConnection(self::dbName);
        $db = Database::getConnection();

        // create record
        $return_value = NULL;
        // build array from object fields
        $entry = get_object_vars($this);
        try {
            $return_value = $db->insert($table)
                ->fields($entry)
                ->execute();
        } catch (Exception $e) {
            $messenger = Drupal::messenger();
            $message = "db_insert failed. Message = " . $e->getMessage();
            $messenger->addMessage($message, $messenger::TYPE_ERROR);
        }
        // set id value
        $entry['id'] = $return_value;


        // return to standard Drupal database
        Database::setActiveConnection();

        return $return_value;

    } // ezacCreate

    /**
     * Read from the database
     * The id of the record to be read is taken from the object->id
     * @param string $table
     *   The EZAC table to be used for the database operation
     * @param string $className default stdClass
     * @return object className
     *   An object containing the loaded entry if found.
     */
    protected function ezacRead($table, $className = "stdClass")
    {

        // define prefix for EZAC tables
        // $table = 'EZAC_' .$table;

        // Read all fields from a Ezac table.
        // select EZAC database outside Drupal structure
        Database::setActiveConnection(self::dbName);
        $db = Database::getConnection();

        $select = $db->select($table); // geen alias gebruikt
        $select->fields($table); // all fields of the table
        $select->condition('id', $this->id); // select this record

        // Return the result as an object
        $select->execute()->setFetchMode(PDO::FETCH_CLASS, $className); //prepare class
        $record = $select->execute()->fetchObject();

        // return to standard Drupal database
        Database::setActiveConnection();

        return $record;

    } //ezacRead

  /**
   * @param $table
   * @param $condition
   * @param string $className
   */
  static public function ezacReadAll($table, $condition, $className = "stdClass")
    {
      // Read all fields from a Ezac table.
      // select EZAC database outside Drupal structure
      Database::setActiveConnection(self::dbName);
      $db = Database::getConnection();

      $select = $db->select($table); // geen alias gebruikt
      $select->fields($table); // all fields of the table

      // Add each field and value as a condition to this query.
      foreach ((array)$condition as $field => $test) {
        // condition can be a simple field => value pair for EQUALS (default test)
        //   or contain value and operator keys for other tests
        if (is_array($test)) {
          $value = $test["value"];
          $operator = $test["operator"];
          $select->condition($field, $value, $operator);
        } else $select->condition($field, $test);
      }

      // Return the result as an object
      $select->execute()->setFetchMode(PDO::FETCH_CLASS, $className); //prepare class
      $records = $select->execute()->fetchAll();

      // return to standard Drupal database
      Database::setActiveConnection();
      return $records;
    } //ezacReadAll

    /**
     * Update an entry in the database.
     *
     * @param string $table
     *   The EZAC table to be used for the database operation
     *
     * @return int
     *   The number of updated rows.
     *
     * @see db_update()
     */
    public function ezacUpdate($table)
    {
        $messenger = Drupal::messenger();

        // EZAC database is outside the Drupal structure
        Database::setActiveConnection(self::dbName);
        $db = Database::getConnection();

        // build array from object fields
        $entry = get_object_vars($this);

        try {
            // db_update()...->execute() returns the number of rows updated.
            $update = $db->update($table)
                ->fields($entry);
            $update->condition('id', $entry['id']);
            $count = $update->execute();
        } catch (Exception $e) {
            $message = 'db_update failed. Message = ' . $e->getMessage();
            $messenger->addMessage($message, $messenger::TYPE_ERROR);
        }

        // return to standard Drupal database
        Database::setActiveConnection();

        /** @var int $count */
        return $count;

    }  // ezacUpdate

    /**
     * Delete an entry from the database.
     *
     * @param string $table
     *   The EZAC table to be used for the database operation
     * @return int
     *   records_deleted
     * @see db_delete()
     */
    public function ezacDelete($table)
    {

        // EZAC database is outside the Drupal structure
        Database::setActiveConnection(self::dbName);
        $db = Database::getConnection();

        // prepare delete
        $select = $db->delete($table);
        $select->condition('id', $this->id);
        $records_deleted = $select->execute();

        // return to standard Drupal database
        Database::setActiveConnection();

        return $records_deleted;

    }  // ezacDelete

} // EzacStorage