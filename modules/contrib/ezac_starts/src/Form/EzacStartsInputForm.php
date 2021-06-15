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

    // check if user selected datum
    $input = $form_state->getUserInput();
    if (key_exists('datum', $input)) {
      $datum = $input['datum'];
    }

    // select last date in starts as default
    if ($datum == null or $datum == '') {

      // maak lijst van vliegdagen dit jaar
      $jaar = date('Y');
      $condition = [
        'datum' => [
          'value' => ["$jaar-01-01", "$jaar-12-31"],
          'operator' => 'BETWEEN',
        ],
      ];
      $sortkey = [
        '#key' => 'datum',
        '#dir' => 'DESC',
      ];
      $dagenIndex = array_unique(EzacStart::index($condition, 'datum', $sortkey));
      if ($dagenIndex != []) $datum = $dagenIndex[0];
      else $datum = date('Y-m-d');
    }

    // get names of leden
    $condition = [
      'actief' => TRUE, // ook oud leden
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

    $form['datum'] = [
      '#type' => 'date',
      '#title' => 'datum',
      //'#date_date_element' => 'date',
      //'#date_time_element' => 'none', // or 'text'
      //'#date_time_format' => 'd-m-Y',
      //'#default_value' => new DrupalDateTime("$datum 00:00:00"),
      '#default_value' => $datum,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::formDatumCallback',
        'event' => 'change',
        'wrapper' => 'startlijst-div',
        'effect' => 'fade',
        'progress' => ['type' => 'throbber'],
      ],
    ];

    // startlijst header
    $header = [
      t('registratie<br>tweezitter'),
      t('gezagvoerder<br>tweede inzittende'),
      t('start<br>landing'),
      t('nu'),
      t('startmethode<br>instructie'),
      t('soort<br>opmerking'),
    ];

    $caption = t("Startlijst voor ") .EzacUtil::showDate($datum);

    $form['startlijst'] = [
      '#type' => 'container',
      '#tree' => true,
      '#prefix' => '<div id="startlijst-div">',
      '#suffix' => '</div>',
    ];
    $form['startlijst']['table'] = [
      // Theme this part of the form as a table.
      '#type' => 'table',
      '#header' => $header,
      '#caption' => $caption,
      '#sticky' => TRUE,
      '#weight' => 5,
    ];

    // get starts in startlijst
    $condition = [
      'datum' => $datum,
    ];
    // selecteer zo nodig vluchten zonder start of landing tijd
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

    return $form;
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  function formDatumCallback(array $form, FormStateInterface $form_state): array {
    return $form['startlijst'];
  }

    /**
   * Add a form line for editing a start
   *
   * @param $form
   * @param \Drupal\ezac_starts\Model\EzacStart $start
   */
  private function addStart(&$form, EzacStart $start) {
    $id = $start->id;

    $kisten = $form['kisten']['#value'];
    $leden = $form['leden']['#value'];

    // test if registratie exists and set tweezitter flag
    if (key_exists($start->registratie, $kisten) and ($start->registratie != '')) {
      $registratie_bekend = true;
      $kist= new EzacKist(EzacKist::getId($start->registratie));
      $tweezitter = ($kist->inzittenden == 2);
    }
    else {
      $registratie_bekend = false;
      $tweezitter = true;
    }

    // each line of the startlijst is a container
    $form['startlijst']['table'][$id] = [
      '#type' => 'container',
      '#tree' => true,
    ];

    // the form has two possible fields for registratie, one select and one textfield if unknown value
    // the textfield is enabled when the select field is [''] <Onbekend>
    $form['startlijst']['table'][$id]['registratie'] = [
      '#type' => 'container',
      '#tree' => true,
      '#prefix' => '<div id="startlijst_' .$id .'">',
      '#suffix' => '</div>',
      '#attributes' => ['name' => "startlijst_$id"],
    ];

    $form['startlijst']['table'][$id]['registratie']['bekend'] = [
      '#type' => 'select',
      '#options' => $kisten,
      '#default_value' => ($registratie_bekend) ? $start->registratie : '',
      // use ajax to set tweezitter value to dynamically show tweede field for tweezitters
      '#id' => $id, // to indicate id line for callback
      '#ajax' => [
        'callback' => '::formTweedeCallback',
        'wrapper' => "tweezitter_$id",
      ],
      '#attributes' => ['name' => "registratie_bekend_$id"],
    ];

    $form['startlijst']['table'][$id]['registratie']['onbekend'] = [
      '#type' => 'textfield',
      '#size' => 10,
      '#maxlength' => 10,
      '#states' => [
        // show only when registratie == [''] <Onbekend>
        'visible' => [
          ':input[name="registratie_bekend_'.$id .'"]' => ['value' => ''],
        ],
      ],
      '#attributes' => ['name' => "registratie_onbekend_$id"],
    ];

    // fill default values
    if (($start->registratie != '') and key_exists($start->registratie, $kisten)) {
      //@todo set status for registratie_bekend
      $form['startlijst']['table'][$id]['registratie']['bekend']['#default_value'] = $start->registratie;
      $form['startlijst']['table'][$id]['registratie']['onbekend']['#default_value'] = '';
    }
    else {
      $form['startlijst']['table'][$id]['registratie']['bekend']['#default_value'] = '';
      $form['startlijst']['table'][$id]['registratie']['onbekend']['#default_value'] = $start->registratie;
    }

    // tweezitter flag
    $form['startlijst']['table'][$id]['registratie']['tweezitter'] = [
      '#type' => 'checkbox',
      '#default_value' => $tweezitter,
      '#prefix' => '<div id="tweezitter_' .$id .'">',
      '#suffix' => '</div>',
      '#attributes' => ['name' => "tweezitter_$id"],
    ];

    $form['startlijst']['table'][$id]['inzittenden'] = [
      '#type' => 'container',
      '#tree' => true,
    ];
    // gezagvoerder - select or textfield
    $gezagvoerder_bekend = key_exists($start->gezagvoerder, $leden);
    $form['startlijst'][$id]['inzittenden']['gezagvoerder'] = [
      '#type' => 'container',
      '#tree' => true,
    ];
    $form['startlijst']['table'][$id]['inzittenden']['gezagvoerder']['bekend'] = [
      '#type' => 'select',
      '#options' => $leden,
      '#default_value' => ($gezagvoerder_bekend) ? $start->gezagvoerder : '',
      '#required' => false,
      '#attributes' => [
        'name' => "gezagvoerder_bekend_$id",
      ],
    ];
    $form['startlijst']['table'][$id]['inzittenden']['gezagvoerder']['onbekend'] = [
      '#type' => 'textfield',
      '#size' => 20,
      '#maxlength' => 20,
      '#default_value' => $start->gezagvoerder,
      '#required' => false,
      '#attributes' => [
        'name' => "gezagvoerder_onbekend_$id",
      ],
      '#states' => [
        'visible' => [
          ':input[name="gezagvoerder_bekend_'.$id .'"]' => ['value' => ''],
        ],
      ],
    ];

    // tweede inzittende
    // tweede - select or textfield
    $tweede_bekend = ($start->tweede != '') and (key_exists($start->tweede, $leden));
    $form['startlijst']['table'][$id]['inzittenden']['tweede'] = [
      '#type' => 'container',
      '#tree' => true,
    ];
    $form['startlijst']['table'][$id]['inzittenden']['tweede']['bekend'] = [
      '#type' => 'select',
      '#options' => $leden,
      '#default_value' => ($tweede_bekend) ? $start->tweede : '',
      '#required' => false,
      '#attributes' => [
        'name' => "tweede_bekend_$id",
      ],
      '#states' => [
        'visible' => [
          ':input[name="tweezitter_'.$id.'"]' => ['checked' => true],
        ],
      ],
    ];
    $form['startlijst']['table'][$id]['inzittenden']['tweede']['onbekend'] = [
      '#type' => 'textfield',
      '#size' => 20,
      '#maxlength' => 20,
      '#default_value' => $start->tweede,
      '#required' => false,
      '#attributes' => [
        'name' => "tweede_onbekend_$id",
      ],
      '#states' => [
        'visible' => [
          ':input[name="tweezitter_'.$id.'"]' => ['checked' => true],
          ':input[name="tweede_bekend_'.$id .'"]' => ['value' => ''],
        ],
      ],
    ];

    $datum = $form['datum']['#default_value'];
    if ($datum == '') $datum = date('Y-m-d');

    $form['startlijst']['table'][$id]['tijden'] = [
      '#type' => 'container',
      '#tree' => true,
      '#prefix' => '<div id="tijden_' .$id .'">',
      '#suffix' => '</div>',
      '#attributes' => ['name' => "tijden_$id"],
    ];
    $form['startlijst']['table'][$id]['tijden']['start'] = [
      '#type' => 'datetime',
      '#date_date_element' => 'none',
      '#date_time_element' => 'time', // or 'text'
      '#date_time_format' => 'H:i',
      '#default_value' => new DrupalDateTime("$datum $start->start"),
      '#size' => 8,
      '#required' => false,
      '#attributes' => ['name' => "start_$id"],
    ];
    $form['startlijst']['table'][$id]['tijden']['landing'] = [
      '#type' => 'datetime',
      '#date_date_element' => 'none',
      '#date_time_element' => 'time',
      '#date_time_format' => 'H:i',
      '#default_value' => new DrupalDateTime("$datum $start->landing"),
      '#size' => 8,
      '#required' => false,
      '#attributes' => ['name' => "landing_$id"],
    ];
    $form['startlijst']['table'][$id]['tijden']['duur'] = [
      '#type' => 'hidden',
      '#default_value' => substr($start->duur,0,8),
      '#size' => 8,
      '#required' => false,
    ];

    // button title depends on start or landing value
    if ($start->start == '') $nu_title = t('start');
    elseif ($start->landing == '') $nu_title = t('landing');
    else $nu_title = t('bewaar');
    $form['startlijst']['table'][$id]['nu'] = [
      '#type' => 'submit', // button validates and submits
      '#name' => $id, // to identify which butten was pressed
      '#value' => $nu_title,
      //'#limit_validation_errors' => [],
    ];

    $form['startlijst']['table'][$id]['methode_instructie'] = [
      '#type' => 'container',
      '#tree' => true,
    ];
    $form['startlijst']['table'][$id]['methode_instructie']['startmethode'] = [
      '#type' => 'select',
      '#options' => EzacStart::$startMethode,
      '#default_value' => $start->startmethode,
      '#required' => false,
    ];
    $form['startlijst']['table'][$id]['methode_instructie']['instructie'] = [
      '#type' => 'checkbox',
      '#default_value' => $start->instructie,
      '#required' => false,
    ];
    $form['startlijst']['table'][$id]['soort_opm'] = [
      '#type' => 'container',
      '#tree' => true,
    ];
    $form['startlijst']['table'][$id]['soort_opm']['soort'] = [
      '#type' => 'select',
      '#options' => EzacStart::$startSoort,
      '#default_value' => $start->soort,
      '#required' => false,
    ];
    $form['startlijst']['table'][$id]['soort_opm']['opmerking'] = [
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
    // get id of startlijst line
    $trigger = $form_state->getTriggeringElement();
    $id= $trigger["#id"];

    // get raw user input
    $input = $form_state->getUserInput();

    $kisten = $form_state->getValue('kisten');
    if (key_exists("registratie_bekend_$id", $input)) {
      $reg = $input["registratie_bekend_$id"];
      // laatste 3 posities van kist naam = (1) of (2)
      $tweezitter = ($reg != '') ? (substr($kisten[$reg],-3,3) == '(2)') : true;
    }
    else $tweezitter = true;
    $form['startlijst']['table'][$id]['registratie']['tweezitter']['#checked'] = $tweezitter;
    //@todo also fill registratie with $reg?
    //$form['startlijst'][$id]['registratie']['#value'] = $reg;
    return $form['startlijst']['table'][$id]['registratie']['tweezitter'];
  }

  /**
   * validate datum, start and landing time
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // perform validate for edit of record

    // datum
    $dat = $form_state->getValue('datum');
    if ($dat !== '') {
      $lv = explode('-', $dat);
      if (checkdate($lv[1], $lv[2], $lv[0]) == FALSE) {
        $form_state->setErrorByName('datum', t("Datum [$dat] is onjuist"));
      }
    }

    $startlijst = $form_state->getValue('startlijst')['table'];
    //traverse startlijst tabel
    foreach ($startlijst as $id => $start_record) {
      if (key_exists('start', $start_record)) {
        // start time is available
        $start = $start_record['start'];
        //@todo validate start format hh:mm
        $start_time = date_create_from_format('H:i', $start);
        if ($start_time == false) {
          $form_state->setError($form['startlijst']['table'][$id]['tijden']['start'], 'Ongeldige start tijd');
        }
        // check landing time
        if (key_exists('landing', $start_record)) {
          $landing = $start_record['landing'];
          $landing_time = date_create_from_format('H:i', $landing);
          if ($landing_time == false) {
            $form_state->setError($form['startlijst']['table'][$id]['tijden']['landing'], 'Ongeldige landing tijd');
          }
          if ($start_time > $landing_time) {
            $form_state->setError($form['startlijst']['table'][$id]['tijden']['landing'], 'Landing voor start');
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   * @throws \Exception
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $messenger = Drupal::messenger();

    // select field values must be read from raw user input
    $input = $form_state->getUserInput();

    // test if submit was reached by changing datum field
    if ($input['_triggering_element_name'] == "datum") {
      // ignore submit, redirect to selected datum
      $datum = $input['datum'];
      $redirect = Url::fromRoute(
        'ezac_starts_lijstinvoer',
        [
          'datum' => $datum,
        ]
      );
      $form_state->setRedirectUrl($redirect);
      $form_state->setRebuild();
      $messenger->addStatus("Datum gewijzigd naar $datum");
    }
    else {

      // datum is for all starts
      $datum = $form_state->getValue('datum');

      //traverse startlijst tabel
      $startlijst = $form_state->getValue('startlijst')['table'];
      //traverse startlijst tabel
      foreach ($startlijst as $id => $start_record) {

        $registratie = $input["registratie_bekend_$id"];
        if ($registratie == '') {
          $registratie = $input["registratie_onbekend_$id"];
        }

        $gezagvoerder = $input["gezagvoerder_bekend_$id"];
        if ($gezagvoerder == '') {
          $gezagvoerder = $input["gezagvoerder_onbekend_$id"];
        }

        $tweede = $input["tweede_bekend_$id"];
        if ($tweede == '') {
          $tweede = $input["tweede_onbekend_$id"];
        }

        $methode = $start_record['methode_instructie']['startmethode'];
        $instructie = $start_record['methode_instructie']['instructie'];

        $soort = $start_record['soort_opm']['soort'];
        $opmerking = $start_record['soort_opm']['opmerking'];

        $start = $input["start_$id"];
        $landing = $input["landing_$id"];

        $trigger = $form_state->getTriggeringElement();
        if ($trigger['#name'] == $id) {
          // button was pressed for this start
          if ($start_record['nu'] == 'start') {
            // if start is empty, fill in current time
            if ($start == '00:00:00') {
              $start = date('H:i');
            }
          }
          if ($start_record['nu'] == 'landing') {
            // if landing is empty, fill in current time
            if ($landing == '00:00:00') {
              $landing = date('H:i');
            }
          }
        }

        if (($start != '00:00:00') and ($landing != '00:00:00')) {
          // bereken duur
          $st = new \DateTime("$datum $start");
          $lt = new \DateTime("$datum $landing"); // for diff calculation
          $diff = date_diff($lt, $st);
          $duur = "$diff->h:$diff->i";
        }
        else {
          $duur = '00:00:00';
        }

        if ($registratie != '' and $gezagvoerder != '') {
          // save or update the start in the database
          $s = new EzacStart();
          $s->id = $id;
          $s->datum = $datum;
          $s->registratie = $registratie;
          $s->gezagvoerder = $gezagvoerder;
          $s->tweede = $tweede;
          $s->soort = $soort;
          $s->startmethode = $methode;
          $s->start = ($start != '00:00:00') ? $start : NULL;
          $s->landing = ($landing != '00:00:00') ? $landing : NULL;
          $s->duur = ($duur != '00:00:00') ? $duur : NULL;
          $s->instructie = $instructie;
          $s->opmerking = $opmerking;

          if ($id == 0) {
            $id = $s->create();
          }
          else {
            $nr = $s->update();
          }
        }
      }

      //go back to starts lijstinvoer
      $redirect = Url::fromRoute(
        'ezac_starts_lijstinvoer',
        [
          'datum' => $datum,
        ]
      );
      $form_state->setRedirectUrl($redirect);
    }
  } //submitForm

}
