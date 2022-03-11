<?php

namespace Drupal\ezac_rooster\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ezac\Util\EzacUtil;
use Drupal\ezac_rooster\Model\EzacRooster;

/**
 * UI to update rooster record
 */

class EzacRoosterTableForm extends FormBase
{

  /**
   * @inheritdoc
   */
  public function getFormId()
  {
      return 'ezac_rooster_table_form';
  }

  /**
   * Create rooster entries
   * @param array $form
   * @param FormStateInterface $form_state
   * @param null $datum
   * @return array
   **/
  function buildForm(array $form, FormStateInterface $form_state, $datum = null) {
    //sanitize $datum YYYY-MM-DD
    $datum = substr(htmlspecialchars($datum),0,10);
    /*
    if (!checkdate(substr($datum,5,2), substr($datum,8,2), substr($datum, 0,4))) {
      unset($datum); //date invalid
    }
    */
    if (!strtotime($datum)) unset($datum); // date invalid

    // get names of leden
    $condition = [
      'actief' => TRUE,
      'code' => 'VL',
    ];
    $leden = EzacUtil::getLeden($condition);
    unset($leden['']); // remove 'Onbekend' lid
    // leden om aan de diensten tabel toe te voegen
    $leden_add = $leden;
    $leden_add[''] = ' <selecteer>';
    asort($leden_add);
    // store leden_lijst in form for use in validate and submit function
    $form['leden'] = array(
      '#type' => 'value',
      '#value' => $leden,
    );

    // read settings
    $settings = Drupal::config('ezac_rooster.settings');
    //set up diensten
    $diensten = $settings->get('rooster.diensten');
    //ezac_rooster_diensten Id Dienst Omschrijving
    $diensten['-'] = '-'; //placeholder voor lege dienst
    $form['diensten'] = array(
      '#type' => 'value',
      '#value' => $diensten,
    );

    //set up periode
    $periodes = $settings->get('rooster.periodes');
    //store header info for periodes reference in submit function
    $form['periodes'] = array(
      '#type' => 'value',
      '#value' => $periodes,
    );

    //invoeren datum voor rooster
    if (!isset($datum)) { // geen datum parameter ontvangen
      $d = $form_state->getValue('datum'); //wel datum in form?
      $datum = $d ?? date('Y-m-d'); // default vandaag
    }

    //date picker
    $form['datum'] = array(
      '#title' => t('Datum'),
      '#type' => 'date',
      '#date_format' => 'Y-m-d',
      '#default_value' => $datum,
      '#weight' => 1,
      '#ajax' => array(
        'callback' => '::roosterTableCallback',
        'wrapper' => 'table-div',
        'effect' => 'fade',
        'progress' => array('type' => 'none'),
      ),
    );

    $datum_form = $form_state->getValue('datum') ?? $datum;

    //add rooster data to form
    //ezac_rooster Id Datum Periode Dienst Naam Mutatie Geruild
    $condition = ['datum' => $datum_form];
    $roosterIndex = EzacRooster::index($condition);
    $rooster = [];
    foreach ($roosterIndex as $id) {
      $rooster[] = new EzacRooster($id);
    }

    // zet gevonden diensten voor $datum in $diensten_value
    // diensten_value [afkorting][periode] = array [id] [dienst]
    $diensten_value = [];
    if (isset($rooster)) { // zijn er diensten op deze datum
      foreach ($rooster as $dr) { // lees elke dienst uit rooster voor datum
        $diensten_value[$dr->naam][$dr->periode]['id'] = $dr->id;
        $diensten_value[$dr->naam][$dr->periode]['dienst'] = $dr->dienst;
      }
    }
    //store current diensten values for submit comparison
    $form['diensten_value'] = array(
      '#type' => 'value',
      '#value' => $diensten_value,
    );

    //toon tabel met naam en per periode een kolom voor een select met diensten
    //prepare header
    $header = array(t('Naam'));
    // voeg een kolom per periode toe
    foreach ($periodes as $periode => $omschrijving) {
      array_push($header, t($omschrijving));
    }

    $caption = t("Rooster voor " .EzacUtil::showDate($datum_form));
    //show table with dienst entry field for each periode record per naam

    //vul tabel alleen voor actieve diensten, met veld voor toevoegen nieuwe dienst (naam, periodes)
    $form['table'] = array(
      // Theme this part of the form as a table.
      '#type' => 'table',
      '#header' => $header,
      '#caption' => $caption,
      '#sticky' => TRUE,
      '#weight' => 5,
      '#prefix' => '<div id="table-div">',
      '#suffix' => '</div>',
    );

    //build rows array of column arrays
    foreach ($leden as $afkorting => $naam) {
      if (isset($diensten_value[$afkorting]))
      { // voor naam is dienst aanwezig
        //Start the row with the Naam
        $form['table'][$afkorting]['naam'] =
          [
            '#type' => 'markup',
            '#markup' => t($naam),
            '#size' => 25,
          ];
        // verwijder naam uit lijst voor toevoegen naam
        unset($leden_add[$afkorting]);
      }
      foreach ($periodes as $periode => $omschrijving) {
        //create dienst entry field for this periode for this $afkorting (person)

        if (isset($diensten_value[$afkorting])) { // dienst aanwezig
          $dw = (isset($diensten_value[$afkorting][$periode]['dienst']))
            ? $diensten_value[$afkorting][$periode]['dienst']
            : '-';

          $form['table'][$afkorting][$periode] = [
            '#type' => 'select',
            '#options' => (array) $diensten,
            '#default_value' => $dw,
          ];
        }
      } //foreach $periodes
    } //foreach $leden

    // voeg select voor nieuwe dienst toe aan tabel
    $form['table']['NEW'] = []; // initialize row
    $form['table']['NEW']['naam'] = [
      '#type' => 'select',
      '#options' => $leden_add,
      '#default_value' => '',
    ];
    // voeg lege diensten toe
    foreach ($periodes as $periode => $omschrijving) {
      $form['table']['NEW'][$periode] = [
        '#type' => 'select',
        '#options' => (array) $diensten,
        '#default_value' => '-',
      ];
    }

    $form['submit'] = array(
      '#type' => 'submit',
      '#description' => t('Aanpassen van de diensten'),
      '#value' => t('Invoeren'),
      '#weight' => 99,
    );

    return $form;
  } //ezacroo_create_form

  /**
   * AJAX update
   **/
  function roosterTableCallback(array $form, FormStateInterface $form_state) {
    return $form['table'];
  }

  function validateForm(array &$form, FormStateInterface $form_state) {
  } //validateForm

  function submitForm(array &$form, FormStateInterface $form_state) {
    $messenger = Drupal::messenger();
    //ezac_rooster Id Datum Periode Dienst Naam Mutatie Geruild
    $datum = $form_state->getValue('datum');
    $leden = $form_state->getValue('leden');
    $periodes = $form_state->getValue('periodes');
    $diensten_value = $form_state->getValue('diensten_value');
    $diensten = $form_state->getValue('diensten');
    $table = $form_state->getValue('table');

    // NEW rooster entry is added in table for processing
    // check last element select on change
    $dienst_toegevoegd = end($table); // 'NEW' key
    if ($dienst_toegevoegd == null) return; // table is empty

    // check if a name was selected
    $afkorting = $dienst_toegevoegd['naam'];
    if ($afkorting != '') {
      // naam was entered in select
      // add diensten to table
      $periodes = $form_state->getValue('periodes');
      foreach ($periodes as $periode => $omschrijving) {
        //create dienst entry field for this periode for this afkorting (person)
        $dienst = $dienst_toegevoegd[$periode];
        if ($dienst != '-') { // dienst ingevuld
          // put entry in table
          $table[$afkorting][$periode] = $dienst;
          // update table in form_state
          //@todo reset dienst werkt niet: select box houdt laatste waarde
          $table['NEW'][$periode] = '-'; // reset dienst after adding
        }
      } //foreach $periodes
    }

    foreach ($leden as $afkorting => $naam) {
      foreach ($periodes as $periode => $omschrijving) {
        //create dienst entry field for this periode for this $afkorting (person)

        // was a dienst already present for afkorting in periode?
        $dw = (isset($diensten_value[$afkorting][$periode]['dienst']))
          ? $diensten_value[$afkorting][$periode]['dienst']
          : '-';

        // is a dienst entered in the form table?
        $dienst = $table[$afkorting][$periode] ?? '-';

        if ($dw != $dienst) { //dienst is veranderd
          if ($dw == '-') { //dienst is nieuw ingevoerd (was eerst '-')
            // check if dienst already exists for idempotency
            $condition = [
              'datum' => $datum,
              'periode' => $periode,
              'dienst' => $dienst,
              'naam' => $afkorting,
            ];
            $index = EzacRooster::index($condition);
            if ($index == []) {
              //create dienst record
              $rooster = new EzacRooster();
              $rooster->datum = $datum;
              $rooster->periode = $periode;
              $rooster->dienst = $dienst;
              $rooster->naam = $afkorting;
              $rooster->mutatie = date('Y-m-d H:i:s');
              $rooster->geruild = '';
              $id = $rooster->create();
              $messenger->addMessage("Dienst $diensten[$dienst] $periode [$id] voor $naam aangemaakt");
            }
            else $messenger->addError("Dienst $dienst op $datum $periode voor $leden[$afkorting] bestaat al");
          }
          elseif ($dienst == '-') {
            //dienst verwijderd (is nu '-')
            $id = $diensten_value[$afkorting][$periode]['id'];
            $rooster = new EzacRooster();
            $rooster->id = $diensten_value[$afkorting][$periode]['id'];
            $num_deleted = $rooster->delete();
            if ($num_deleted == 1) {
              $messenger->addMessage("Dienst $diensten[$dw] $periode [$id] voor $naam verwijderd");
            }
            else {
              $messenger->addMessage("$num_deleted records voor Dienst $diensten[$dw] $periode [$id] voor $naam verwijderd");
            }
          }
          else {
            //dienst gewijzigd (was niet '-' en nu ook niet '-')
            $id = $diensten_value[$afkorting][$periode]['id'];
            $rooster = new EzacRooster($id);
            $rooster->datum = $datum;
            $rooster->periode = $periode;
            $rooster->dienst = $dienst;
            $rooster->naam = $afkorting;
            $rooster->mutatie = date('Y-m-d H:i:s');
            $rooster->geruild = '';
            // update rooster
            $num_updated = $rooster->update();
            if ($num_updated == 1) {
              $messenger->addMessage("Dienst $diensten[$dw] $periode [$id] voor $naam gewijzigd in $diensten[$dienst] $periode");
            }
            else {
              $messenger->addError("$num_updated records voor Dienst $diensten[$dw] $periode [$id] voor $naam gewijzigd in $diensten[$dienst] $periode");
            }
          } //else
        } //if

      } //foreach $periodes
    } //foreach $leden
    //remain on the same page
    $form_state->disableRedirect();
    $form_state->setRebuild();
  } // submit

}
