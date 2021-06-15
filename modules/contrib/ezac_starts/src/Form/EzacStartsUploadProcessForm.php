<?php


namespace Drupal\ezac_starts\Form;


use ArrayObject;
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
   * @param array $a
   * @param array $b
   *
   * @return bool
   */
  private function start_sort(array $a, array $b) {
    if ($a['start'] == '00:00') $a['start'] = '';
    if ($b['start'] == '00:00') $b['start'] = '';
    return ($a['datum'] . $a['start'] . $a['registratie']) > ($b['datum'] . $b['start'] . $b['registratie']);
  }

  /**
   * @param array $a
   * @param array $b
   *
   * @return bool
   */
   function identical(array $a, array $b): bool {
      $identical = true;
      foreach ($a as $key => $value) {
        if (in_array($key,['start', 'landing', 'duur'])) {
          // test with '00:00' for empty times
          if ((($a[$key] == '') ? '00:00' : $a[$key]) != ($b[$key] == '' ? '00:00' : $b[$key])) {
            $identical = FALSE;
          }
        }
        elseif ($key != 'id') { // do not test id value
          if (key_exists($key, $b) and ($a[$key] != $b[$key])) $identical = false;
        }
      }
      return $identical;
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

    // prepare list of datums
    $datums = [];

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
      // build array of header columns and record values
      $start_rec = array_combine($header, $s);
      // add id field
      if (!key_exists('id',$start_rec)) $start_rec['id'] = 0;
      // convert datum from DD-MM-YYYY to YYYY-MM-DD
      $datum = substr($start_rec['datum'],6,4) .'-'
        .substr($start_rec['datum'],3,2) .'-'
        .substr($start_rec['datum'],0,2);
      $start_rec['datum'] = $datum;
      // store datum in datums
      if (!key_exists($datum, $datums)) $datums = array_merge($datums, array($datum));
      // use afkorting if available
      if ($start_rec['gezagvoerder_id'] != '') $start_rec['gezagvoerder'] = $start_rec['gezagvoerder_id'];
      if ($start_rec['tweede_id'] != '') $start_rec['tweede'] = $start_rec['tweede_id'];
      // make instructie flag boolean
      $start_rec['instructie'] = ($start_rec['instructie'] == 'J'? 1: 0);
      // store start_rec in starts
      $starts[] = $start_rec;
    }
    fclose($file);

    // read corresponding records from database
    $condition = [
      'datum' => [
        'value' => $datums,
        'operator' => 'IN'
      ],
    ];
    $ids = EzacStart::index($condition);
    foreach ($ids as $id) {
      $start_rec = new EzacStart($id);
      // reduce times to HH:MM format (skip :SS)
      $start_rec->start = substr($start_rec->start,0,5);
      $start_rec->landing = substr($start_rec->landing,0,5);
      $start_rec->duur = substr($start_rec->duur,0,5);
      $starts[] = (array) $start_rec;
    }

    // sort $starts on datum, tijd, registratie
    usort($starts, "self::start_sort");

    // remove duplicate entries (id == 0 indicates record from logfile)
    foreach ($starts as $i => $start_rec) {
      if ($i == 0) {
        $prev = $start_rec;
      }
      else {
        if (self::identical($prev, $start_rec)) {
          // remove starts
          unset($starts[$i-1], $starts[$i]);
          // prev does not need refreshing
        }
        else {
          $prev = $start_rec;
        }
      }
    }


    // prepare table
    foreach ($starts as $i => $s) {
      $starts[$i]['op'] = 'select';
    }

    // store $starts for submit
    $form['starts'] = [
      '#type' => 'value',
      '#value' => $starts,
    ];

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
      // in each record, ['op'] indicates operation: select | create
      $form['table'][$i] = [
        ['#plain_text' => $s['datum']],
        ['#plain_text' => $s['start']],
        ['#plain_text' => $s['landing']],
        ['#plain_text' => $s['soort']],
        ['#plain_text' => $s['registratie']],
        ['#plain_text' => $s['gezagvoerder']],
        ['#plain_text' => $s['tweede']],
        ['#plain_text' => $s['instructie']],
        ['#plain_text' => $s['opmerking']],
        ['#plain_text' => $s['methode']],
        [
          '#type' => 'select',
          '#options' => $options,
          '#default_value' => $s['op'],
        ],
      ];
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
    // parse $form['starts'] and update records where applicable
    $starts = $form_state->getValue('starts');
    $table = $form_state->getValue('table');
    $created = 0;
    $deleted = 0;
    foreach ($table as $i => $row) {
      $starts[$i]['op'] = array_pop($row); //get first element
    }
    foreach ($starts as $i => $s) {
      switch ($s['op']) {
        case 'select':
          if ($s['id'] == 0) {
            $start = new EzacStart();
            // copy all fields from logfile entry
            foreach ($start as $key => $value) {
              if (key_exists($key, $s)) {
                $start->$key = $s[$key];
              }
            }
            // override gezagvoerder and tweede with afkorting if available
            if (isset($s['gezagvoerder_id'])) $start->gezagvoerder = $s['gezagvoerder_id'];
            if (isset($s['tweede_id'])) $start->tweede = $s['tweede_id'];
            // update starts table
            $id = $start->create();
            if ($id) $created++;
            else $messenger->addError("record $i niet aangemaakt");
          }
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

    $messenger->addMessage("Bestand verwerkt: $created records toegevoegd, $deleted records verwijderd");
  }

}