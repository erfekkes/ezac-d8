<?php

namespace Drupal\ezac_starts\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ezac\Util\EzacUtil;
use Drupal\ezac_kisten\Model\EzacKist;
use Drupal\ezac_leden\Model\EzacLid;
use Drupal\ezac_starts\Model\EzacStart;

/**
 * UI to update starts record
 * tijdelijke aanpassing
 */
class EzacStartsUpdateForm extends FormBase {

  /**
   * @inheritdoc
   */
  public function getFormId(): string {
    return 'ezac_starts_update_form';
  }

  /**
   * buildForm for STARTS update with ID parameter
   * This is also used to CREATE new starts record (no ID param given as input)
   *
   * @param array $form
   * @param FormStateInterface $form_state
   * @param null $id
   *
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL): array {
    // Wrap the form in a div.
    $form = [
      '#prefix' => '<div id="updateform">',
      '#suffix' => '</div>',
    ];

    // Query for items to display.
    // if $id is set, perform UPDATE else CREATE
    if (isset($id)) {
      $start = new EzacStart($id);
      $newRecord = FALSE;
    }
    else { // prepare new record
      $start = new EzacStart(); // create empty start occurrence
      $newRecord = TRUE;
    }

    //store indicator for new record for submit function
    $form['new'] = [
      '#type' => 'value',
      '#value' => $newRecord, // TRUE or FALSE
    ];

    // get names of leden
    $condition = [
      'actief' => TRUE,
      'code' => 'VL',
    ];
    $leden = EzacUtil::getLeden($condition);

    // get kisten details
    $kisten = EzacUtil::getKisten();

    if ($form_state->getValue('registratie') != '') {
      // Check op tweezitter via (changed) form element
      $kist = new EzacKist(EzacKist::getID($form_state->getValue('registratie')));
      $tweezitter = ($kist->inzittenden == 2);
    }
    else {
      // Check op tweezitter via start record
      if (($start->registratie != '') and key_exists($start->registratie, $kisten)) {
        $kist = new EzacKist(EzacKist::getID($start->registratie));
        $tweezitter = ($kist->inzittenden == 2);
      }
      else $tweezitter = true;
    }

    $form['tweezitter'] = [
      '#prefix' => '<div id="tweezitter">',
      '#type' => 'checkbox',
      '#title' => 'Tweezitter',
      '#value' => $tweezitter,
      '#checked' => $tweezitter,
      '#attributes' => ['name' => 'tweezitter'],
    ];

    $form['datum'] = [
      '#type' => 'date',
      '#title' => 'datum',
      '#default_value' => $start->datum,
      '#required' => TRUE,
    ];

    // test if registratie exists
    $form['registratie_bekend'] = [
      '#type' => 'value',
      '#value' => ($start->registratie != '') ? key_exists($start->registratie, $kisten) : false,
      '#attributes' => [
        'name' => 'registratie_bekend'
      ],
    ];

    // the form has two possible fields for registratie, one select and one textfield if unknown value
    // the textfield is enabled when the select field is [''] <Onbekend>
    $form['registratie'] = [
      '#type' => 'select',
      '#title' => 'registratie',
      '#options' => $kisten,
      // use ajax to set tweezitter value to dynamically show tweede field for tweezitters
      '#ajax' => [
        'callback' => '::formTweedeCallback',
        'wrapper' => 'tweezitter',
      ],
      '#attributes' => [
        'name' => 'registratie',
      ]
    ];

    $form['registratie_onbekend'] = [
      '#type' => 'textfield',
      '#title' => 'registratie',
      '#size' => 10,
      '#maxlength' => 10,
      '#states' => [
        // show only when registratie == [''] <Onbekend>
        'visible' => [
          ':input[name="registratie"]' => ['value' => ''],
         ],
       ],
    ];

    if (($start->registratie != '') and key_exists($start->registratie, $kisten)) {
      $form['registratie']['#default_value'] = $start->registratie;
      $form['registratie_onbekend']['#default_value'] = '';
    }
    else {
      $form['registratie']['#default_value'] = '';
      $form['registratie_onbekend']['#default_value'] = $start->registratie;
    }

    // @todo allow for unknown name using checkbox
    $form['gezagvoerder'] = [
      '#type' => 'select',
      '#title' => 'gezagvoerder',
      '#options' => $leden,
      '#attributes' => [
        'name' => 'gezagvoerder',
      ],
    ];

    $form['gezagvoerder_onbekend'] = [
      '#type' => 'textfield',
      '#title' => 'gezagvoerder',
      '#maxlength' => 20,
      '#size' => 20,
      '#attributes' => [
        'name' => 'gezagvoerder_onbekend',
      ],
      '#states' => [
        // show this field only when Gezagvoerder = Onbekend
        //@see https://www.drupal.org/docs/drupal-apis/form-api/conditional-form-fields
        'visible' => [
          ':input[name="gezagvoerder"]' => ['value' => ''],
        ],
      ],
    ];

    // set default values depending on gezagvoerder being known
    if (key_exists($start->gezagvoerder, $leden)) {
      $form['gezagvoerder']['#default_value'] = $start->gezagvoerder;
      $form['gezagvoerder_onbekend']['#default_value'] = '';
    }
    else {
      $form['gezagvoerder']['#default_value'] = '';
      $form['gezagvoerder_onbekend']['#default_value'] = $start->gezagvoerder;
    }

    $form['tweede'] = [
      '#type' => 'select',
      '#title' => 'tweede inzittende',
      '#options' => $leden,
      '#attributes' => [
        'name' => 'tweede',
      ],
      '#states' => [
        // show this field only when tweezitter
        'visible' => [
          ':input[name="tweezitter"]' => ['checked' => true],
        ],
      ],
    ];

    $form['tweede_onbekend'] = [
      '#type' => 'textfield',
      '#title' => 'tweede inzittende',
      '#maxlength' => 20,
      '#size' => 20,
      '#attributes' => [
        'name' => 'tweede_onbekend',
      ],
      '#states' => [
        // show this field only when tweede = Onbekend
        'visible' => [
          ':input[name="tweede"]' => ['value' => ''],
          ':input[name="tweezitter"]' => ['checked' => true],
        ],
      ],
    ];

    // set default values
    if (key_exists($start->tweede, $leden)) {
      $form['tweede']['#default_value'] = $start->tweede;
      $form['tweede_onbekend']['#default_value'] = '';
    }
    else {
      $form['tweede']['#default_value'] = '';
      $form['tweede_onbekend']['#default_value'] = $start->tweede;
    }

    $form['soort'] = [
      '#type' => 'select',
      '#title' => 'soort',
      '#default_value' => $start->soort,
      '#options' => EzacStart::$startSoort,
    ];

    $form['startmethode'] = [
      '#type' => 'select',
      '#title' => 'startmethode',
      '#default_value' => $start->startmethode,
      '#options' => EzacStart::$startMethode,
    ];

    $form['start'] = [
      '#type' => 'textfield',
      '#title' => 'start',
      '#default_value' => substr($start->start, 0,5),
      '#size' => 5,
      '#maxlength' => 5,
    ];

    $form['landing'] = [
      '#type' => 'textfield',
      '#title' => 'landing',
      '#default_value' => substr($start->landing, 0, 5),
      '#size' => 5,
      '#maxlength' => 5,
    ];

    $form['duur'] = [
      '#type' => 'textfield',
      '#title' => 'duur',
      '#value' => substr($start->duur, 0, 5),
      '#size' => 5,
      '#maxlength' => 5,
      '#disabled' => true,
    ];

    $form['instructie'] = [
      '#type' => 'checkbox',
      '#title' => 'instructie',
      '#default_value' => $start->instructie,
    ];

    $form['opmerking'] = [
      '#type' => 'textfield',
      '#title' => 'opmerking',
      '#default_value' => $start->opmerking,
      '#maxlength' => 30,
    ];

    //Id
    //Toon het het Id nummer van het record
    $form['id'] = [
      '#type' => 'hidden',
      '#title' => 'record number (id)',
      '#default_value' => $start->id,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $newRecord ? t('Invoeren') : t('Update'),
      '#weight' => 31,
    ];

    //insert Delete button  gevaarlijk ivm dependencies
    if (Drupal::currentUser()->hasPermission('EZAC_delete')) {
      if (!$newRecord) {
        $form = EzacUtil::addField($form, 'deletebox', 'checkbox', 'verwijder', 'verwijder record', FALSE, 1, 1, FALSE, 29);
        $form['actions']['delete'] = [
          '#type' => 'submit',
          '#value' => t('Verwijderen'),
          '#weight' => 32,
        ];
      }
    }

    return $form;
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array|mixed
   */
  function formTweedeCallback(array $form, FormStateInterface $form_state): array {
    // Check op tweezitter
    $kist = new EzacKist(EzacKist::getID($form_state->getValue('registratie')));
    $tweezitter = ($kist->inzittenden == 2);
    $form['tweezitter'] = [
      '#prefix' => '<div id="tweezitter">',
      '#type' => 'checkbox',
      '#title' => 'Tweezitter',
      '#value' => $tweezitter,
      '#checked' => $tweezitter,
      '#attributes' => ['name' => 'tweezitter'],
    ];
    return $form["tweezitter"];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // perform validate for edit of record

    // gezagvoerder
    $gezagvoerder = $form_state->getValue('gezagvoerder');
    if ($gezagvoerder <> $form['gezagvoerder']['#default_value']) {
      if (EzacLid::counter(['afkorting' => $gezagvoerder]) == 0) {
        $form_state->setErrorByName('gezagvoerder', t("Afkorting $gezagvoerder bestaat niet"));
      }
    }
    if (!array_key_exists($form_state->getValue('soort'), EzacStart::$startSoort)) {
      $form_state->setErrorByName('soort', t("Ongeldige soort"));
    }
    // datum
    $dat = $form_state->getValue('datum');
    if ($dat !== '') {
      $lv = explode('-', $dat);
      if (checkdate($lv[1], $lv[2], $lv[0]) == FALSE) {
        $form_state->setErrorByName('datum', t("Datum [$dat] is onjuist"));
      }
    }

    //start
    $start = $form_state->getValue('start');
    if ($start <> '') {
      $stc = explode(':', $start);
      if (!is_numeric($stc[0]) || ($stc[0] < 0) || ($stc[0] > 23)) $form_state->setErrorByName('start', 'ongeldige tijd');
      if (!is_numeric($stc[1]) || ($stc[1] < 0) || ($stc[1] > 59)) $form_state->setErrorByName('start', 'ongeldige tijd');
    }

    //landing
    $landing = $form_state->getValue('landing');
    if ($landing <> '') {
      $stc = explode(':', $landing);
      if (!is_numeric($stc[0]) || ($stc[0] < 0) || ($stc[0] > 23)) $form_state->setErrorByName('landing', 'ongeldige tijd');
      if (!is_numeric($stc[1]) || ($stc[1] < 0) || ($stc[1] > 59)) $form_state->setErrorByName('landing', 'ongeldige tijd');
    }

    //duur
    $duur = $form_state->getValue('duur');
    if ($duur <> '') {
      $stc = explode(':', $duur);
      if (!is_numeric($stc[0]) || ($stc[0] < 0) || ($stc[0] > 23)) $form_state->setErrorByName('duur', 'ongeldige tijd');
      if (!is_numeric($stc[1]) || ($stc[1] < 0) || ($stc[1] > 59)) $form_state->setErrorByName('duur', 'ongeldige tijd');
    }

  }

  /**
   * {@inheritdoc}
   * @throws \Exception
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $messenger = Drupal::messenger();

    // delete record
    if ($form_state->getValue('op') == 'Verwijderen') {
      if (!Drupal::currentUser()->hasPermission('EZAC_delete')) {
        $messenger->addMessage('Verwijderen niet toegestaan', $messenger::TYPE_ERROR);
        return;
      }
      if ($form_state->getValue('deletebox') == FALSE) {
        $messenger->addMessage('Verwijdering niet geselecteerd', $messenger::TYPE_ERROR);
        return;
      }
      $start = new EzacStart; // initiate Start instance
      $start->id = $form_state->getValue('id');
      $count = $start->delete(); // delete record in database
      $messenger->addMessage("$count record verwijderd");
    }
    else {
      // Save the submitted entry.
      $start = new EzacStart;
      // get all fields
      foreach (EzacStart::$fields as $field => $description) {
        $start->$field = $form_state->getValue($field);
      }

      $s = new \DateTime("$start->datum $start->start");
      $l = new \DateTime("$start->datum $start->landing"); // for diff calculation
      if ($l < $s) {
        $messenger->addMessage("Landing voor start $start->landing", 'error');
        return;
      }
      //duur is calculated
      $diff = date_diff($l, $s);
      $start->duur = "$diff->h:$diff->i";

      //check gezagvoerder_onbekend, tweede_onbekend, registratie_onbekend
      if (($start->gezagvoerder == '') && $form_state->getValue('gezagvoerder_onbekend') != '')
        $start->gezagvoerder = $form_state->getValue('gezagvoerder_onbekend');
      if (($start->tweede == '') && $form_state->getValue('tweede_onbekend') != '')
        $start->tweede = $form_state->getValue('tweede_onbekend');
      if (($start->registratie == '') && $form_state->getValue('registratie_onbekend') != '')
        $start->registratie = $form_state->getValue('registratie_onbekend');

      //Check value newRecord to select insert or update
      if ($form_state->getValue('new') == TRUE) {
        $start->create(); // add record in database
        $messenger->addMessage("Starts record aangemaakt met id [$start->id]", $messenger::TYPE_STATUS);

      }
      else {
        $count = $start->update(); // update record in database
        $messenger->addMessage("$count record updated", $messenger::TYPE_STATUS);
      }
    }
    //go back to starts overzicht
    $redirect = Url::fromRoute(
      'ezac_starts_create',
      [
        'datum' => $form_state->getValue('datum'),
      ]
    );
    $form_state->setRedirectUrl($redirect);
  } //submitForm

}
