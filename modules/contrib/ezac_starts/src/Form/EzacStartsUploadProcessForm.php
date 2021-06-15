<?php


namespace Drupal\ezac_starts\Form;


use Drupal;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\Element\Datetime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ezac\Util\EzacUtil;
use Drupal\ezac_kisten\Model\EzacKist;
use Drupal\ezac_leden\Model\EzacLid;
use Drupal\ezac_starts\Model\EzacStart;
use Drupal\file\Entity\File;

class EzacStartsUploadProcessForm extends \Drupal\Core\Form\FormBase {

  /**
   * @inheritDoc
   */
  public function getFormId() {
    return 'ezac_starts_upload_process_form';
  }

  /**
   * @inheritDoc
   * see https://www.drupal.org/node/347251
   */
  public function buildForm(array $form, FormStateInterface $form_state, $file = NULL) {
    // called from EzacStartsUploadForm
    // build form with table for start records from file
    // for invalid records - show error ? select duplicate entries

    //$content = file::load($file[0]);
    //$filename = file_managed_file_save_upload('csv_upload_file', $form_state);
    $destination = file::load($file)->toArray()['uri'][0]['value'];

    $form = array();
    // If this #attribute is not present, upload will fail on submit
    $form['#attributes']['enctype'] = 'multipart/form-data';

    // get temporary file name
    // process each record
    //  validate record
    //  put records in form table for submit processing (ignore | post to starts)

    // see https://drupal.stackexchange.com/questions/247454/import-csv-row-by-row-into-custom-table
    $file = fopen($destination, "r");

    $starts = [];

    // read table headers
    $header = fgetcsv($file, 0,';','"');
    // process records
    while (!feof($file)) {
      $s = fgetcsv($file, 0, ';', '"');
      /*
      $datum = $s[0];
      $start = $s[1];
      $landing = $s[2];
      $soort = $s[4];
      $registratie = $s[5];
      $gezagvoerder = $s[7]; // piloot, piloot_id
      $tweede = $s[9];
      $instructie = ($s[10] == 'J');
      $opmerking = $s[11];
      $methode = $s[12];
      */
      // key [][0] is the record from the logfile, following numbers are from the database when duplicates exist
      // build array of header columns and record values
      $starts[][0] = array_combine($header, $s);
    }
    fclose($file);

    // verify records with database
    // - read matching records
    foreach ($starts as $i => $s) {
      // convert datum from DD-MM-YYYY to YYYY-MM-DD
      $datum = substr($s[0]['datum'],6,4) .'-'
        .substr($s[0]['datum'],3,2) .'-'
        .substr($s[0]['datum'],0,2);
      $s[0]['datum'] = $datum;
      $condition = [
        'datum' => $datum,
        'registratie' => $s[0]['registratie'],
        'start' => $s[0]['start'], // when start is empty?
      ];
      $ids = EzacStart::index($condition);

      // - check for duplicates or insert
      // if no value in ids, we have a new start to put in database
      if (count($ids) == 0) {
        $starts[$i][0]['op'] = 'create';
      }

      // if one value in ids, check record with $s for update or change
      if (count($ids) == 1) {
        $s_db = array(new EzacStart($ids[0]));
        // check values in $s_db for match with $s
        $identical = TRUE;
        foreach ($s_db as $key => $value) {
          if ($value != $s[0][$key]) $identical = FALSE;
        }
        if (!$identical) {
          //  mark record in starts table for user verification if values are not identical
          // user must select which record remains id put in database
          $s_db['op'] = 'select';
          $starts[$i][] = $s_db;
        }
        // if identical, the $s record can be ignored for idempotency
      }

      // if more than one value in ids, put each in the table for selecting or editing
      if (count($ids) > 1) {
        foreach ($ids as $id) {
          $s_db = array(new EzacStart($id));
          // user must select which record remains in database
          $s_db['op'] = 'select';
          $starts[$i][] = $s_db;
        }
      }
    }

    // store $starts for submit
    $form['starts'] = [
      '#type' => 'value',
      '#value' => $starts,
    ];

    // @todo verify table operations

    $header = [
      'datum',
      'start',
      'landing',
      'soort',
      'registratie',
      'gezagvoerder',
      'tweede',
      'instructie',
      'opmerking',
      'methode',
      'actie',
    ];
    $caption = "Starts uit bestand $destination";
    // build selection table from $form['starts']
    $options = [
      'create' => 'Aanmaken',
      'select' => 'Selecteren',
      'delete' => 'Verwijderen',
      'ignore' => 'Negeer',
    ];
    $form['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#caption' => $caption,
      '#sticky' => TRUE,
    ];
    foreach ($starts as $i => $s) {
      // index [0] = record from upload_file
      // possible further indexes are duplicates from database for selection
      // in each record, ['op'] indicates operation: select | create
      // @todo build form elements for each line: create | select | delete
      foreach ($s as $j => $start) {
        $form['table'][$i] = [
          ['#plain_text' => $start['datum']],
          ['#plain_text' => $start['start']],
          ['#plain_text' => $start['landing']],
          ['#plain_text' => $start['soort']],
          ['#plain_text' => $start['registratie']],
          ['#plain_text' => $start['gezagvoerder']],
          ['#plain_text' => $start['tweede']],
          ['#plain_text' => $start['instructie']],
          ['#plain_text' => $start['opmerking']],
          ['#plain_text' => $start['methode']],
          [
            '#type' => 'select',
            '#options' => $options,
            '#default_value' => $start['op'],
            'j' => $j
          ],
        ];
      }
    }
    $form['submit_upload'] = array(
      '#type'  =>  'submit',
      '#value'  =>  'Submit'
    );
    return $form;
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * process uploaded starts file
   * check for double entries - idempotency
   *
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state); // TODO: Change the autogenerated stub
  }


  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $messenger = Drupal::messenger();
    // TODO: Implement submitForm() method.
    // parse $form['starts'] and update records where applicable
    $starts = $form_state->getValue('starts');
    $table = $form_state->getValue('table');
    $created = 0;
    $deleted = 0;
    foreach ($table as $i => $row) {
      $j = $row['j']; //@todo $row == 10 => 'create'
      $starts[$i][$j]['op'] = $table[$i][10]; //@todo replace 10
    }
    foreach ($starts as $i => $s_array) {
      foreach ($s_array as $j => $s) {
        switch ($s['op']) {
          case 'create':
            $start = new EzacStart();
            // copy all fields from logfile entry
            foreach ($start as $key => $value) {
              if (key_exists($key, $s)) {
                $start->$key = $s[$key];
              }
            }
            // update starts table
            $id = $start->create();
            $created++;
            break;
          case 'select':
            break;
          case 'delete':
            $start = new EzacStart(($s['id']));
            if ($start->id != NULL) {
              $nr = $start->delete();
              if ($nr == 0) {
                $messenger->addError("record $start->id niet verwijderd");
              }
              else {
                $deleted++;
              }
            }
            break;
          case 'ignore':
            break;
        }
      }
    }

    $messenger->addMessage("Bestand verwerkt: $created records toegevoegd, $deleted records verwijderd");

  }

}