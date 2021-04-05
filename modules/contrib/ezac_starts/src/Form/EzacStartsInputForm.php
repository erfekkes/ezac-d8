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
class EzacStartsInputForm extends FormBase {

  /**
   * @inheritdoc
   */
  public function getFormId(): string {
    return 'ezac_starts_input_form';
  }

  /**
   * buildForm for STARTS input in a list format
   *
   * @param array $form
   * @param FormStateInterface $form_state
   * @param null|string $datum
   * @param null|string $selectie alle | start | landing
   *
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state, $datum = NULL, $selectie = null): array {
    // Wrap the form in a div.
    $form = [
      '#prefix' => '<div id="inputform">',
      '#suffix' => '</div>',
    ];

    // Query for items to display.
    //@todo select last date in starts as default?
    if ($datum == null) $datum = date('Y-m-d'); //today default

    // get names of leden
    $condition = [
      'actief' => TRUE,
      'code' => 'VL',
    ];
    $leden = EzacUtil::getLeden($condition);
    $form['leden'] = [
      '#type' => 'value',
      '#value' => $leden,
    ];
    // get kisten details
    $kisten = EzacUtil::getKisten();
    $form['kisten'] = [
      '#type' => 'value',
      '#value' => $kisten,
    ];

    // prepare start record
    $start = new EzacStart;

    // check for tweezitter setting
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
      '#default_value' => $datum,
      '#required' => TRUE,
    ];

    // startlijst header
    $header = [
      t('registratie'),
      t('gezagvoerder'),
      t('tweede inzittende'),
      t('start'),
      t('landing'),
      t('duur'),
      t('startmethode'),
      t('instructie'),
      t('soort'),
      t('opmerking'),
    ];

    $caption = t("Startlijst voor ") .EzacUtil::showDate($datum);

    $form['startlijst'] = [
      // Theme this part of the form as a table.
      '#type' => 'table',
      '#header' => $header,
      '#caption' => $caption,
      '#sticky' => TRUE,
      '#weight' => 5,
      '#prefix' => '<div id="startlijst-div">',
      '#suffix' => '</div>',
    ];

    // zet starts in startlijst
    $condition = [
      'datum' => $datum,
    ];
    if ($selectie == 'start') {
      $condition['start'] = null;
    }
    if ($selectie == 'landing') {
      $condition['landing'] = null;
    }
    $startsIndex = EzacStart::index($condition);
    foreach ($startsIndex as $id) {
      $this->addStart($form, new EzacStart($id));
    }
    // add blank line
    $this->addStart($form, new EzacStart());

    /*
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
      '#default_value' => substr($start->duur, 0, 5),
      '#size' => 5,
      '#maxlength' => 5,
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
    $form['id'] = [
      '#type' => 'value',
      '#value' => $start->id,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Invoeren'),
      '#weight' => 31,
    ];
    */
    return $form;
  }

  /**
   * @param $form
   * @param \Drupal\ezac_starts\Model\EzacStart $start
   */
  function addStart(&$form, EzacStart $start) {
    $id = $start->id;

    // test if registratie exists
    $registratie_bekend = ($start->registratie != '') ? key_exists($start->registratie, $form['kisten']) : false;

    // the form has two possible fields for registratie, one select and one textfield if unknown value
    // the textfield is enabled when the select field is [''] <Onbekend>
    $form['startlijst'][$id]['registratie'] = [
      '#type' => 'container',
      '#tree' => true,
    ];

    $form['startlijst'][$id]['registratie']['bekend'] = [
      '#type' => 'select',
      '#title' => 'registratie',
      '#options' => $form['kisten'],
      //'#default_value' => ($registratie_bekend) ? $start->registratie : '',
      // use ajax to set tweezitter value to dynamically show tweede field for tweezitters
      '#ajax' => [
        'callback' => '::formTweedeCallback',
        'wrapper' => "tweezitter_$id",
      ],
      '#states' => [
        // show only when registratie_onbekend == [''] <not entered>
        'visible' => [
          ':input[name="registratie_onbekend_"'.$id .'"]' => ['value' => ''],
        ],
      ],
      '#attributes' => [
        'name' => "registratie_$id",
      ]
    ];

    $form['startlijst'][$id]['registratie']['onbekend'] = [
      '#type' => 'textfield',
      '#title' => 'registratie',
      '#size' => 10,
      '#maxlength' => 10,
      '#states' => [
        // show only when registratie == [''] <Onbekend>
        'visible' => [
          ':input[name="registratie_"'.$id .'"]' => ['value' => ''],
        ],
      ],
      '#attributes' => [
        'name' => "registratie_onbekend_$id",
      ]
    ];

    // fill default values
    if (($start->registratie != '') and key_exists($start->registratie, $form['kisten'])) {
      $form['startlijst'][$id]['registratie']['bekend']['#default_value'] = $start->registratie;
      $form['startlijst'][$id]['registratie']['onbekend']['#default_value'] = '';
    }
    else {
      $form['startlijst'][$id]['registratie']['bekend']['#default_value'] = '';
      $form['startlijst'][$id]['registratie']['onbekend']['#default_value'] = $start->registratie;
    }

    //@todo nog aanpassen
    $form['startlijst'][$id]['gezagvoerder'] = [
      '#type' => 'textfield',
      '#default_value' => $start->gezagvoerder,
      '#size' => 6,
      '#required' => false,
    ];
    $form['startlijst'][$id]['tweede'] = [
      '#type' => 'textfield',
      '#default_value' => $start->tweede,
      '#size' => 6,
      '#required' => false,
    ];
    $form['startlijst'][$id]['start'] = [
      '#type' => 'time',
      '#default_value' => $start->start,
      '#size' => 6,
      '#required' => false,
    ];
    $form['startlijst'][$id]['landing'] = [
      '#type' => 'time',
      '#default_value' => $start->landing,
      '#size' => 6,
      '#required' => false,
    ];
    $form['startlijst'][$id]['duur'] = [
      '#type' => 'time',
      '#default_value' => $start->duur,
      '#size' => 6,
      '#required' => false,
    ];
    $form['startlijst'][$id]['startmethode'] = [
      '#type' => 'select',
      '#options' => EzacStart::$startMethode,
      '#default_value' => $start->startmethode,
      '#required' => false,
    ];
    $form['startlijst'][$id]['instructie'] = [
      '#type' => 'checkbox',
      '#default_value' => $start->instructie,
      '#required' => false,
    ];
    $form['startlijst'][$id]['soort'] = [
      '#type' => 'select',
      '#options' => EzacStart::$startSoort,
      '#default_value' => $start->soort,
      '#required' => false,
    ];
    $form['startlijst'][$id]['opmerking'] = [
      '#type' => 'textfield',
      '#default_value' => $start->opmerking,
      '#size' => 20,
      '#required' => false,
    ];

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

    //@todo traverse startlijst tabel
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
  }

  /**
   * {@inheritdoc}
   * @throws \Exception
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $messenger = Drupal::messenger();

      //@todo traverse startlijst tabel

      // Save the submitted entry.
      $start = new EzacStart;
      // get all fields
      foreach (EzacStart::$fields as $field => $description) {
        $start->$field = $form_state->getValue($field);
      }
      //check gezagvoerder_onbekend, tweede_onbekend, registratie_onbekend
      if (($start->gezagvoerder == '') && $form_state->getValue('gezagvoerder_onbekend') != '')
        $start->gezagvoerder = $form_state->getValue('gezagvoerer_onbekend');
      if (($start->tweede == '') && $form_state->getValue('tweede_onbekend') != '')
        $start->tweede = $form_state->getValue('tweede_onbekend');
      if (($start->registratie == '') && $form_state->getValue('registratie_onbekend') != '')
        $start->registratie = $form_state->getValue('registratie_onbekend');

      //Check value newRecord to select insert or update
      if ($form_state->getValue('new') == TRUE) {
        //$start->create(); // add record in database
        //$messenger->addMessage("Starts record aangemaakt met id [$start->id]", $messenger::TYPE_STATUS);

      }
      else {
        //$count = $start->update(); // update record in database
        //$messenger->addMessage("$count record updated", $messenger::TYPE_STATUS);
      }

    //go back to starts overzicht
    $redirect = Url::fromRoute(
      'ezac_starts_overzicht',
      [
        'datum_start' => $form_state->getValue('datum'),
        'datum_eind' => $form_state->getValue('datum'),
      ]
    );
    $form_state->setRedirectUrl($redirect);
  } //submitForm

}
