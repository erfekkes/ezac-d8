<?php

namespace Drupal\ezac_vba\Form;

use Drupal;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use Drupal\ezac\Util\EzacUtil;
use Drupal\ezac_leden\Model\EzacLid;
use Drupal\ezac_starts\Controller\EzacStartsController;
use Drupal\ezac_vba\Model\EzacVbaBevoegdheid;
use Drupal\ezac_vba\Model\EzacVbaDagverslag;
use Drupal\ezac_vba\Model\EzacVbaDagverslagLid;

/**
 * UI to show status of VBA records
 */


class EzacVbaLidForm extends FormBase {

  /**
   * @inheritdoc
   */
  public function getFormId(): string {
    return 'ezac_vba_lid_form';
  }

  /**
   * buildForm for vba lid status and bevoegdheid
   *
   * Voortgang en Bevoegdheid Administratie
   * Overzicht van de status en bevoegdheid voor een lid
   *
   * @param array $form
   * @param FormStateInterface $form_state
   *
   * @param $datum_start
   * @param $datum_eind
   *
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state, $datum_start = NULL, $datum_eind = NULL): array {
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

    // Wrap the form in a div.
    $form = [
      '#prefix' => '<div id="statusform">',
      '#suffix' => '</div>',
    ];

    // apply the form theme
    //$form['#theme'] = 'ezac_vba_lid_form';

    // when datum not given, set default for this year
    if ($datum_start == NULL) {
      $datum_start = date('Y') . "-01-01";
    }
    if ($datum_eind == NULL) {
      $datum_eind = date('Y') . "-12-31";
    }
    $form['datum_start'] = [
      '#type' => 'value',
      '#value' => $datum_start,
    ];
    $form['datum_eind'] = [
      '#type' => 'value',
      '#value' => $datum_eind,
    ];

    $condition = [
      'code' => 'VL',
      'actief' => TRUE,
    ];
    $namen = EzacUtil::getLeden($condition);
    $namen[''] = '<selecteer>';
    //@todo optie voor iedereen toevoegen

    $form['persoon'] = [
      '#type' => 'select',
      '#title' => 'Vlieger',
      '#options' => $namen,
      '#weight' => 2,
      '#ajax' => [
        'wrapper' => 'vliegers-div',
        'callback' => '::formPersoonCallback',
        'effect' => 'fade',
      ],
    ];


    // Kies gewenste vlieger voor overzicht dagverslagen
    $overzicht = TRUE; // @todo replace parameter $overzicht

    //maak container voor vliegers
    //[vliegers] form wordt door AJAX opnieuw opgebouwd
    $form['vliegers'] = [
      '#title' => t('Vlieger'),
      '#type' => 'container',
      '#weight' => 4,
      '#prefix' => '<div id="vliegers-div">',
      //This section replaced by AJAX callback
      '#suffix' => '</div>',
      '#tree' => TRUE,
    ];

    //submit
    //@todo dit is onnodig - er valt niets te versturen
    $form['vliegers']['submit'] = [
      '#type' => 'submit',
      '#description' => t('Opslaan'),
      '#value' => t('Opslaan'),
      '#weight' => 99,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    return $form;
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array|mixed
   */
  function formPersoonCallback(array $form, FormStateInterface $form_state): array {

    $overzicht = TRUE; //@todo temporary fix
    $condition = [
      'code' => 'VL',
      'actief' => TRUE,
    ];
    $namen = EzacUtil::getLeden($condition);

    $persoon = $form_state->getValue('persoon');
    $datum_start = $form_state->getValue('datum_start');
    $datum_eind = $form_state->getValue('datum_eind');

    $bevoegdheden = $form_state->getValue('bevoegdheden');
    $status = $form_state->getValue('status');

    if (isset($persoon) && $persoon != '') {

      // lees vlieger gegevens
      $vlieger_afkorting = $form_state->getValue('persoon');
      $lid = new EzacLid(EzacLid::getId($vlieger_afkorting));
      $helenaam = "$lid->voornaam $lid->voorvoeg $lid->achternaam";

      //Toon eerdere verslagen per lid
      // query vba verslag, bevoegdheid records
      $condition = ['afkorting' => $vlieger_afkorting];
      if (isset($datum_start)) {
        $condition ['datum'] =
          [
            'value' => [$datum_start, $datum_eind],
            'operator' => 'BETWEEN'
          ];
      }
      $verslagenIndex = EzacVbaDagverslagLid::index($condition);

      // put in table
      if (isset($verslagenIndex)) { //create fieldset
        $form['vliegers']['verslagen'][$vlieger_afkorting] = [
          '#title' => t("Eerdere verslagen voor $helenaam"),
          '#type' => 'fieldset',
          '#edit' => FALSE,
          '#required' => FALSE,
          '#collapsible' => TRUE,
          '#collapsed' => !$overzicht,
          '#weight' => 6,
          '#tree' => TRUE,
        ];

        $header = [
          ['data' => 'datum', 'width' => '20%'],
          ['data' => 'instructeur', 'width' => '20%'],
          ['data' => 'opmerking'],
        ];

        $rows = [];
        foreach ($verslagenIndex as $id) {
          $verslag = new EzacVbaDagverslagLid($id);
          $rows[] = [
            EzacUtil::showDate($verslag->datum),
            $namen[$verslag->instructeur],
            nl2br($verslag->verslag),
          ];
        }
        $form['vliegers']['verslagen'][$vlieger_afkorting]['tabel'] = [
          '#theme' => 'table',
          '#header' => $header,
          '#rows' => $rows,
          '#empty' => t('Geen gegevens beschikbaar'),
          //'#attributes' => $attributes,
        ];
      }

      //toon huidige bevoegdheden
      // query vba verslag, bevoegdheid records
      $condition = [
        'afkorting' => $vlieger_afkorting,
        'actief' => TRUE,
      ];
      $vlieger_bevoegdhedenIndex = EzacVbaBevoegdheid::index($condition);

      // put in table
      $header = [
        ['data' => 'datum', 'width' => '20%'],
        ['data' => 'instructeur', 'width' => '20%'],
        ['data' => 'bevoegdheid'],
      ];
      $rows = [];

      if (!empty($vlieger_bevoegdhedenIndex)) { //create fieldset
        $form['vliegers']['bevoegdheden'][$vlieger_afkorting] = [
          '#title' => t("Bevoegdheden voor $helenaam"),
          '#type' => 'fieldset',
          '#edit' => FALSE,
          '#required' => FALSE,
          '#collapsible' => TRUE,
          '#collapsed' => FALSE, //!$overzicht,
          '#weight' => 7,
          '#tree' => TRUE,
        ];
        foreach ($vlieger_bevoegdhedenIndex as $id) {
          $bevoegdheid = new EzacVbaBevoegdheid($id);
          $rows[] = [
            EzacUtil::showDate($bevoegdheid->datum_aan),
            $namen[$bevoegdheid->instructeur],
            $bevoegdheid->bevoegdheid . ' - '
            . $bevoegdheden[$bevoegdheid->bevoegdheid] . ' '
            . nl2br($bevoegdheid->onderdeel)
          ];
        }
        $form['vliegers']['bevoegdheden'][$vlieger_afkorting]['tabel'] = [
          '#theme' => 'table',
          '#header' => $header,
          '#rows' => $rows,
          '#empty' => t('Geen gegevens beschikbaar'),
          '#weight' => 7,
        ];
      }

      //toon vluchten dit jaar
      $form['vliegers']['starts'] = EzacStartsController::startOverzicht(
        "$datum_start:$datum_eind",
        $vlieger_afkorting);

      if (!$overzicht) {
        //@todo param $overzicht nog hanteren? of apart form voor maken
        // invoeren opmerking
        $form['vliegers']['opmerking'] = [
          '#title' => t("Opmerkingen voor $helenaam"),
          '#type' => 'textarea',
          '#rows' => 3,
          '#required' => FALSE,
          '#weight' => 5,
          '#tree' => TRUE,
        ];
      }

      if (!$overzicht) {
        //invoer bevoegdheid
        $form['vliegers']['bevoegdheid'] = [
          '#title' => 'Bevoegdheid',
          '#type' => 'container',
          '#prefix' => '<div id="bevoegdheid-div">',
          '#suffix' => '</div>',
          '#required' => FALSE,
          '#collapsible' => TRUE,
          '#collapsed' => FALSE,
          '#weight' => 10,
          '#tree' => TRUE,
        ];

        $form['vliegers']['bevoegdheid']['keuze'] = [
          '#title' => t('Bevoegdheid'),
          '#type' => 'select',
          '#options' => $bevoegdheden,
          '#default_value' => 0, //<Geen wijziging>
          '#weight' => 10,
          '#tree' => TRUE,
          '#ajax' => [
            'callback' => 'EzacVba_bevoegdheid_callback',
            'wrapper' => 'bevoegdheid-div',
            'effect' => 'fade',
            'progress' => ['type' => 'throbber'],
          ],
        ];

        if (isset($form_state['values']['vliegers']['bevoegdheid']['keuze'])
          && ($form_state->getValue(['bevoegdheid']['keuze']) <> '0')) {
          $form['vliegers']['bevoegdheid']['onderdeel'] = [
            '#title' => t('Onderdeel'),
            '#description' => 'Bijvoorbeeld overland type',
            '#type' => 'textfield',
            '#maxlength' => 30,
            '#required' => FALSE,
            '#default_value' => '',
            '#weight' => 11,
            '#tree' => TRUE,
          ];
        }
      } //!$overzicht
      // end copied code
    } //isset(persoon)
    return $form['vliegers'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   * @throws \Exception
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  } //submitForm

}
