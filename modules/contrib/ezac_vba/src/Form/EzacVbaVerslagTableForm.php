<?php

namespace Drupal\ezac_vba\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ezac\Util\EzacUtil;
use Drupal\ezac_leden\Model\EzacLid;
use Drupal\ezac_starts\Model\EzacStart;
use Drupal\ezac_vba\Model\EzacVbaBevoegdheid;
use Drupal\ezac_vba\Model\EzacVbaDagverslag;
use Drupal\ezac_vba\Model\EzacVbaDagverslagLid;

/**
 * UI to show status of VBA records
 */
class EzacVbaVerslagTableForm extends FormBase {

  /**
   * @inheritdoc
   */
  public function getFormId(): string {
    return 'ezac_vba_verslag_table_form';
  }

  /**
   * Build dagrapport form.
   *
   * Voortgang en Bevoegdheid Administratie
   *
   * @param array $form
   * @param FormStateInterface $form_state
   * @param int $id id van verslag voor edit
   *
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL): array {
    // prepare message area
    $messenger = Drupal::messenger();

    // 1. prepare form data elements
    // read settings
    $settings = Drupal::config('ezac_vba.settings');

    //set up bevoegdheden
    $bevoegdheden = $settings->get('vba.bevoegdheden');
    $form['bevoegdheden'] = [
      '#type' => 'value',
      '#value' => $bevoegdheden,
    ];

    // set up status van bevoegdheden
    $status = $settings->get('vba.status');
    $form['status'] = [
      '#type' => 'value',
      '#value' => $status,
    ];

    //maak lijst van leden voor dropdown menu
    $condition = [
      'code' => 'VL',
      'actief' => TRUE,
    ];
    $leden = EzacUtil::getLeden($condition);
    $leden[''] = '<selecteer>';

    $form['leden'] = [
      '#type' => 'value',
      '#value' => $leden,
    ];

    //maak lijst van bevoegdheden voor dropdown menu
    $bv_list[0] = '<Geen wijziging>';
    if (isset($bevoegdheden)) {
      foreach ($bevoegdheden as $bevoegdheid => $bevoegdheid_array) {
        $bv_list[$bevoegdheid] = $bevoegdheid_array['naam'];
      }
    }

    // find this or previous year's flight days, descending
    $year = date('Y');
    $starts = [];
    while ($starts == []) {
      $condition = [
        'datum' => [
          'value' => ["$year-01-01", "$year-12-31"],
          'operator' => 'BETWEEN',
        ],
      ];
      $starts = array_unique(EzacStart::index($condition, 'datum', 'datum', 'DESC'));
      $year--;
    }
    $year++; // set year to the one used

    $start_dates = [];
    foreach ($starts as $start) {
      $start_dates[$start] = EzacUtil::showDate($start); //list of dates for selection
    }
    if (isset($starts)) {
      $datum = $starts[array_key_first($starts)]; // most recent date
    }
    else {
      $datum = date('Y-m-d');
    }

    // 2. build form contents

    // if id is set, read dagverslag for edit
    if ($id != NULL) {
      $dagverslag = new EzacVbaDagverslag($id);
      $newRecord = FALSE;
      $form['id'] = [
        '#type' => 'value',
        '#value' => $id,
      ];
    }
    else {
      $dagverslag = new EzacVbaDagverslag();
      $newRecord = TRUE;
    }
    $form['newRecord'] = [
      '#type' => 'value',
      '#value' => $newRecord,
      '#attributes' => ['name' => 'newRecord'],
    ];

    // datum selector dropdown list
    $form['datum_select'] = [
      '#title' => t('Datum'),
      '#type' => 'select',
      '#options' => $start_dates,
      '#default_value' => ($newRecord) ? key($start_dates) : $dagverslag->datum,
      //most recent date
      '#states' => [
        'visible' => [
          ':input[name="datum_other"]' => ['checked' => FALSE],
          //':input[name="newRecord"]' => ['value' => TRUE],
        ],
      ],
    ];

    // Enter datum manually if requested or no list available
    $form['datum_entry'] = [
      '#title' => t('Datum'),
      '#type' => 'date', // date_select is extension to 'date'
      '#date_format' => 'Y-m-d',
      '#default_value' => ($newRecord) ? $datum : $dagverslag->datum, //today
      '#states' => [
        'visible' => [
          ':input[name="datum_other"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // checkbox to select other datum
    $form['datum_other'] = [
      '#title' => t('Kies andere datum'),
      '#type' => 'checkbox',
      '#default_value' => !isset($starts),
      '#attributes' => ['name' => 'datum_other'],
    ];

    // set instructeur default to value of current user
    // via AJAX wordt de lijst van vliegers voor die instructeur getoond

    //get current user details
    $afkorting = EzacUtil::getUser();

    $form['instructeur'] = [
      '#title' => t('Instructeur / verantwoordelijke'),
      '#type' => 'select',
      '#options' => $leden,  //@TODO select only instructeur from leden
      '#default_value' => ($newRecord) ? $afkorting : $dagverslag->instructeur,
      //'#description' => t('Instructeur of verantwoordelijke'),
      '#weight' => 2,
      '#ajax' => [
        'callback' => '::verslagCallback',
        'wrapper' => 'vliegers-div',
      ],
    ];

    // textarea field for 'weer' (2 lines)
    $form['weer'] = [
      '#title' => t('Weer en baanrichting'),
      '#type' => 'textarea',
      '#rows' => 2,
      '#default_value' => ($newRecord) ? '' : nl2br($dagverslag->weer),
      '#weight' => 3,
      '#prefix' => '<div id="weer">',
      '#suffix' => '</div>',
    ];

    // textarea field for 'verslag'  (10 lines)
    $form['verslag'] = [
      '#title' => t('Algemeen verslag'),
      '#type' => 'textarea',
      '#rows' => 10,
      '#default_value' => ($newRecord) ? '' : nl2br($dagverslag->verslag),
      '#required' => TRUE,
      '#weight' => 4,
      '#prefix' => '<div id="verslag">',
      '#suffix' => '</div>',
    ];

    //optie om alleen eigen leerlingen te selecteren of alle vliegers van die dag
    $form['leerling'] = [
      '#title' => t('Selecteer alleen eigen leerlingen van deze dag'),
      '#type' => 'checkbox',
      '#default_value' => TRUE,
      '#weight' => 5,
      '#ajax' => [
        'callback' => '::verslagCallback',
        'wrapper' => 'vliegers-div',
      ],
    ];

    // generate form element with vliegers for the selected day
    // get starts->gezagvoerder, starts->tweede in $leden
    //[vliegers] form wordt door AJAX opnieuw opgebouwd

    // initialize vliegers array
    $vliegers = [];

    // get datum from entry or select depending on checkbox datum_other
    //@todo - DIT WERKT NIET - datum blijft null
    $do = $form_state->getValue('datum_other');
    $de = $form_state->getValue('datum_entry');
    $ds = $form_state->getValue('datum_select');

    if ($do) {
      $datum = $de;
    }
    else {
      $datum = $ds;
    }

    // als datum door gebruiker is ingevuld, deze overnemen
    if ($form_state->getValue('datum_other') != NULL) {
      $datum = ($form_state->getValue('datum_other') == TRUE)
        ? $form_state->getValue('datum_entry')
        : $form_state->getValue('datum_select');
    }
    // select only own students depending on checkbox
    $eigen_leerling = $form_state->getValue('leerling') ?? TRUE;

    // get selected instructeur from form
    $instructeur = $form_state->getValue('instructeur') ?? $afkorting;

    // read starts for selected datum and put names in $vliegers
    $condition = ['datum' => $datum];
    $startsIndex = EzacStart::index($condition);
    foreach ($startsIndex as $id) {
      $start = new EzacStart($id); // read start record
      $gezagvoerder = $start->gezagvoerder;
      if (!$eigen_leerling) {
        // selecteer alle leerlingen
        if (!isset($vliegers[$gezagvoerder])) // initialiseer vliegers voor gezagvoerder
        {
          $vliegers[$gezagvoerder] = $leden[$gezagvoerder];
        }
        if (isset($start->tweede) && ($start->tweede != '')) {
          $tweede = $start->tweede;
          if (!isset($vliegers[$tweede])) // initialiseer vliegers voor tweede inzittende
          {
            $vliegers[$tweede] = $leden[$tweede];
          }
        }
      }
      // selecteer eigen leerlingen
      if ($eigen_leerling && ($gezagvoerder == $instructeur)) {
        if (isset($start->tweede) && ($start->tweede != '')) {
          $tweede = $start->tweede;
          if (!isset($vliegers[$tweede])) {
            $vliegers[$tweede] = $leden[$tweede];
          }
        }
      }
    }

    //sorteer $vliegers array op inhoud
    asort($vliegers);

    //3. Build form entry table for each vlieger
    //@todo add option to show existing verslagen and bevoegdheid

    //toon tabel met verslag en bevoegdheid / onderdeel per persoon
    //prepare header
    $header = [t('Naam')];
    $header = [
      t('Naam'),
      t('Verslag'),
      t('Bevoegdheid'),
      t('Onderdeel'),
    ];
    $caption = t("Verslag per vlieger");

    $form['vliegers'] = [
      // Theme this part of the form as a table.
      '#type' => 'table',
      '#header' => $header,
      '#caption' => $caption,
      '#sticky' => TRUE,
      '#weight' => 6,
      '#prefix' => '<div id="vliegers-div">',
      //This section replaced by AJAX callback
      '#suffix' => '</div>',
    ];

    foreach ($vliegers as $vlieger => $naam) {
      $form['vliegers'][$vlieger]['naam'] = [
        '#type' => 'item',
        '#title' => $naam,
      ];
      $form['vliegers'][$vlieger]['opmerking'] = [
        '#type' => 'textarea',
        '#size' => 40,
        '#rows' => 3,
        '#required' => FALSE,
      ];
      $form['vliegers'][$vlieger]['bevoegdheid'] = [
        '#description' => 'Toekennen van een nieuwe bevoegdheid',
        '#type' => 'select',
        '#options' => $bv_list,
      ];
      $form['vliegers'][$vlieger]['onderdeel'] = [
        '#description' => 'Bijvoorbeeld overland type',
        '#type' => 'textfield',
        '#size' => 20,
        '#required' => FALSE,
      ];
    }

    //4. add submit button
    $form['submit'] = [
      '#type' => 'submit',
      '#description' => t('Verslag opslaan en via mail verzenden'),
      '#value' => ($newRecord) ? t('Opslaan') : t('Bijwerken'),
      '#weight' => 97,
    ];

    //5. add delete button
    if (Drupal::currentUser()->hasPermission('EZAC_delete')) {
      $form['delete_checkbox'] = [
        '#type' => 'checkbox',
        '#title' => t('Verwijder dagverslag'),
        '#checked' => FALSE,
        '#weight' => 96,
      ];
      $form['delete'] = [
        '#type' => 'submit',
        '#description' => t('Verslag verwijderen'),
        '#value' => t('Verwijderen'),
        '#weight' => 99,
      ];
    }


    return $form;
  }

  /**
   * Selects the piece of the form we want to use as replacement text and
   * returns it as a form (renderable array).
   *
   * @param $form
   * @param $form_state
   *
   * @return  array (the textfields element)
   */
  function verslagCallback(array $form, FormStateInterface $form_state) {
    return $form['vliegers']; //HTML for verslag form['vliegers']
  }

  /**
   * Validate the form.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // @todo check delete validation
  }

  /**
   * Handle post-validation form submission.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $message = Drupal::messenger();

    // delete record
    if ($form_state->getValue('op') == 'Verwijderen') {
      if (!Drupal::currentUser()->hasPermission('EZAC_delete')) {
        $message->addMessage('Verwijderen niet toegestaan', $message::TYPE_ERROR);
        return;
      }
      if ($form_state->getValue('delete_checkbox') == FALSE) {
        $message->addMessage('Verwijdering niet geselecteerd', $message::TYPE_ERROR);
        return;
      }
      $dagverslag = new EzacVbaDagverslag($form_state->getValue('id')); // initiate Start instance
      $count = $dagverslag->delete(); // delete record in database
      $message->addMessage("$count record verwijderd");
      return;
    }

    // if datum_other is checked, take datum from entry, else from select
    $datum = ($form_state->getValue('datum_other') == 1)
      ? $form_state->getValue('datum_entry')
      : $form_state->getValue('datum_select');

    if ($form_state->getValue('newRecord')) {
      $dagverslag = new EzacVbaDagverslag();
    }
    else {
      $dagverslag = new EzacVbaDagverslag($form_state->getValue('id'));
    }

    $leden = $form_state->getValue('leden');
    $dagverslag->datum = $datum;
    $dagverslag->instructeur = $form_state->getValue('instructeur');
    $dagverslag->weer = htmlentities($form_state->getValue('weer'));
    $dagverslag->verslag = htmlentities($form_state->getValue('verslag'));
    $dagverslag->mutatie = date('Y-m-d h:m:s');

    //write verslag to vba_dagverslagen
    if ($dagverslag->weer . $dagverslag->verslag != '') { // verslag ingevuld
      if ($form_state->getValue('newRecord')) {
        $id = $dagverslag->create(); // write to database
        $message->addMessage("Dagverslag [$id] voor "
          . EzacUtil::showDate($dagverslag->datum) . ' aangemaakt', 'status');
      }
      else {
        // update record
        $nr = $dagverslag->update();
        if ($nr == 1) {
          $message->addMessage("Dagverslag [$dagverslag->id] voor "
            . EzacUtil::showDate($dagverslag->datum) . ' bijgewerkt', 'status');
        }
        else {
          // update niet gelukt
          $message->addMessage("Dagverslag [$dagverslag->id] kon niet worden bijgewerkt", 'error');
        }
      }
    }
    //write verslag per vlieger
    // $vliegers[afkorting] has keys naam opmerking bevoegdheid onderdeel
    $vliegers = $form_state->getValue('vliegers');
    if (isset($vliegers)) {
      foreach ($vliegers as $afkorting => $vlieger) {

        if (isset($vlieger['opmerking']) && $vlieger['opmerking'] <> '') {
          //opmerking is ingevoerd
          $dagverslagLid = new EzacVbaDagverslagLid();
          $dagverslagLid->datum = $datum;
          $dagverslagLid->afkorting = $afkorting;
          $dagverslagLid->instructeur = $form_state->getValue('instructeur');
          $dagverslagLid->verslag = htmlentities($vlieger['opmerking']);
          $dagverslagLid->mutatie = date('Y-m-d h:m:s');
          $id = $dagverslagLid->create();
          $message->addMessage('Verslag voor ' . $leden[$afkorting] . ' aangemaakt', 'status');
        }
        //update bevoegdheden per vlieger
        if (isset($vlieger['bevoegdheid']) && $vlieger['bevoegdheid'] <> '0') {
          //Bevoegdheid ingevoerd
          $bevoegdheid = new EzacVbaBevoegdheid();
          $bevoegdheid->bevoegdheid = $vlieger['bevoegdheid'];
          $bevoegdheid->onderdeel = htmlentities($vlieger['onderdeel']);
          $bevoegdheid->datum_aan = $datum;
          $bevoegdheid->datum_uit = NULL;
          $bevoegdheid->afkorting = $afkorting;
          $bevoegdheid->instructeur = $form_state->getValue('instructeur');
          $bevoegdheid->actief = TRUE;
          $bevoegdheid->mutatie = date('Y-m-d h:m:s');
          $id = $bevoegdheid->create();
          $message->addMessage('Bevoegdheid ' . $vlieger['bevoegdheid']
            . ' voor ' . $leden[$afkorting] . " aangemaakt [$id]", 'status');
        }
      }
    }

    //mail verslag naar instructeurs
    self::verslagenMail($datum);

    //@todo redirect naar calling url
    /*
    if ($current_url != "") {
      $form_state['redirect'] = $current_url;
    }
    else $form_state['redirect'] = 'vba';
    */
    // return result
    return;
  }

  /**
   * Mail Verslag (dag en per lid)
   *
   * @param string datum
   **/
  function verslagenMail($datum) {
    $condition = [
      'code' => 'VL',
      'actief' => TRUE,
    ];
    $leden = EzacUtil::getLeden($condition);

    //mail verslag naar instructeurs
    $condition = [
      'instructie' => TRUE,
      'actief' => TRUE,
    ];
    $instructeurs = EzacLid::index($condition, 'e_mail');
    $to = '';
    foreach ($instructeurs as $email) {
      $to .= $email . '; ';
    }
    $to .= 'webmaster@ezac.nl'; //instructie@ezac.nl //TEST DEBUG

    $subject = "EZAC instructie verslag $datum";

    //Haal omstandigheden en verslag uit de database
    $condition = ['datum' => $datum];
    $dagverslagenIndex = EzacVbaDagverslag::index($condition);
    $message = '';
    if (count($dagverslagenIndex)) {
      foreach ($dagverslagenIndex as $id) {
        $dagverslag = new EzacVbaDagverslag($id);
        $mail_instructeur = $leden[$dagverslag->instructeur];
        $message .= "<p><h1>Verslag van $mail_instructeur</h1></p>";
        $message .= "<p><h2>Omstandigheden</h2></p>";
        $message .= "<p>" . $dagverslag->weer . "</p>";
        $message .= "<p><h2>Verslag</h2></p>";
        $message .= "<p>" . $dagverslag->verslag . "</p>";
      }
    }

    //haal de opmerkingen uit de database ivm los ingevoerde opmerkingen
    $condition = ['datum' => $datum];
    $verslagenIndex = EzacVbaDagverslagLid::index($condition);
    if (count($verslagenIndex)) {
      $message .= "<p><h2>Opmerkingen per leerling</h2></p>";
      foreach ($verslagenIndex as $id) {
        $verslag = new EzacVbaDagverslagLid($id);
        $naam = $leden[$verslag->afkorting];
        $message .= "<p><h3>$naam</h3></p>";
        $message .= "<p> $verslag->verslag </p>";
      }
    }

    $condition = ['datum_aan' => $datum];
    $bevoegdhedenIndex = EzacVbaBevoegdheid::index($condition);
    if (count($bevoegdhedenIndex)) {
      $message .= "<p><h2>Bevoegdheden toegekend per leerling</h2></p>";
      foreach ($bevoegdhedenIndex as $id) {
        $bevoegdheid = new EzacVbaBevoegdheid($id);
        $message .= "<p> $leden($bevoegdheid->afkorting) : $bevoegdheid->bevoegdheid";
        $message .= " door instructeur $leden($bevoegdheid->instructeur) </p>";
      }
    }
    // verstuur mail
    $headers  = "From: webmaster@ezac.nl\n";
    $headers .= "X-Mailer: PHP\n"; // mailer
    $headers .= "Return-Path: <webmaster@ezac.nl>\n"; // Return path for errors
    $headers .= "Content-Type: text/html; charset=iso-8859-1\n"; // Mime type
    $mailed = mail($to, $subject, $message, $headers);

  } // verslagenMail

}
