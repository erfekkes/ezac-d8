<?php

namespace Drupal\ezac_leden\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ezac_leden\Model\EzacLid;

/**
 * UI to update leden recordvoor ledenlijst
 */
class EzacLedenLijstForm extends FormBase {

  /**
   * @inheritdoc
   */
  public function getFormId() {
    return 'ezac_leden_lijst_form';
  }

  /**
   * buildForm voor tonen ledenlijst gegevens
   *
   * @param array $form
   * @param FormStateInterface $form_state
   *
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Wrap the form in a div.
    $form = [
      '#prefix' => '<div id="ledenlijstform">',
      '#suffix' => '</div>',
    ];

    $form['voornaam'] = [
      '#type' => 'textfield',
      '#title' => t('Voornaam'),
      '#maxlength' => 20,
      '#size' => 20,
      '#weight' => 1,
      '#attributes' => [
        'name' => 'voornaam',
      ],
      '#ajax' => [
        'callback' => '::formCallback',
        'disable-refocus' => 'true',
        'event' => 'change',
        'wrapper' => 'table',
      ],
    ];

    $form['achternaam'] = [
      '#type' => 'textfield',
      '#title' => t('Achternaam'),
      '#maxlength' => 20,
      '#size' => 20,
      '#weight' => 2,
      '#attributes' => [
        'name' => 'achternaam',
      ],
      '#ajax' => [
        'callback' => '::formCallback',
        'disable-refocus' => 'true',
        'event' => 'change',
        'wrapper' => 'table',
      ],
    ];

    $headers = [
      'Naam',
      'Adres',
      'Telefoon',
      'E-mailadres',
      'Code',
    ];

    $rows = [];

    $form['table'] = [
      '#type' => 'table',
      '#caption' => t("Overzicht EZAC leden"),
      '#header' => $headers,
      '#rows' => $this->buildRows($form_state->getValue('voornaam'), $form_state->getValue('achternaam')),
      '#empty' => t('Geen gegevens beschikbaar.'),
      '#sticky' => TRUE,
      '#prefix' => '<div id="table">',
      '#suffix' => '</div>',
      '#weight' => 3,
      '#attributes' => [
        'name' => 'table',
      ],
      '#states' => [
        'invisible' => [
          ':input[name="voornaam"]' => ['value' => ''],
          ':input[name="achternaam"]' => ['value' => ''],
        ],
      ],
    ];

    // switch off caching for ajax processing
    $form_state->setCached(false);

    /*
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Zoek'),
      '#weight' => 3,
    ];
    */
    return $form;
  }

  function formCallBack(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    return ($form['table']);
  }

  function buildRows($voornaam, $achternaam) {
    $messenger = Drupal::messenger();
    if (($voornaam == null) and ($achternaam == null)) return [];

    // Verwijder voorvoegsels
    if (strrchr($achternaam, " ")) {
      $achternaam = trim(strrchr($achternaam, " "));
    }

    if ($voornaam != '') {
      $condition['voornaam'] = $voornaam;
    }
    if ($achternaam != '') {
      $condition['achternaam'] = $achternaam;
    }

    if (!isset($condition)) {
      $messenger->addMessage('geen geldige selectie opgegeven', 'error');
      return [];
    }

    $condition['actief'] = TRUE;
    $condition['code'] = [
      'value' => ['AL', 'VL', 'AVL', 'DO'],
      'operator' => 'IN',
    ];
    $ledenIndex = EzacLid::index($condition, 'id', 'achternaam');

    //Toon aanwezige records

    // Table tag attributes
    $attributes = [
      'border' => 1,
      'cellspacing' => 0,
      'cellpadding' => 5,
      'width' => '90%',
    ];

    //Set up the table Headings
    $header = [
      ['data' => t('Naam')],
      ['data' => t('Adres')],
      ['data' => t('Telefoon')],
      ['data' => t('E-mail')],
      ['data' => t('Code')],
    ];
    $rows = [];

    foreach ($ledenIndex as $id) {
      $lid = new EzacLid($id);
      $rows[] = [
        t("$lid->voornaam $lid->voorvoeg $lid->achternaam"),
        t("$lid->adres<br>$lid->postcode $lid->plaats<br>$lid->land"),
        t("$lid->telefoon <br> $lid->mobiel"),
        t("<a href='mailto:$lid->e_mail'>$lid->e_mail</a>"),
        $lid->code,
      ];
    }
    return $rows;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // leeg
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  } //submitForm

}
