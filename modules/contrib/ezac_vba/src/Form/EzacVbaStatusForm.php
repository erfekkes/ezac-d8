<?php

namespace Drupal\ezac_vba\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ezac_vba\Model\EzacVbaBevoegdheid;
use Drupal\ezac_vba\Model\EzacVbaDagverslag;
use Drupal\ezac_vba\Model\EzacVbaDagverslagLid;

/**
 * UI to show status of VBA records
 */


class EzacVbaStatusForm extends FormBase
{

    /**
     * @inheritdoc
     */
    public function getFormId()
    {
        return 'ezac_vba_status_form';
    }

  /**
   * buildForm for vba status
   *
   * Voortgang en Bevoegdheid Administratie
   * Overzicht van de status (startscherm)
   *
   * @param array $form
   * @param FormStateInterface $form_state
   *
   * @return array
   */
    public function buildForm(array $form, FormStateInterface $form_state) {
      // Wrap the form in a div.
      $form = [
        '#prefix' => '<div id="statusform">',
        '#suffix' => '</div>',
      ];

      // apply the form theme
      //$form['#theme'] = 'ezac_vba_status_form';

      $datum_start = date('Y') . "-01-01";
      $datum_eind = date('Y') . "-12-31";

      $periode_list = [
        'seizoen' => 'dit seizoen',
        'tweejaar' => '24 maanden',
        'jaar' => '12 maanden',
        'maand' => '1 maand',
        'vandaag' => 'vandaag',
        //'anders' => 'andere periode',
      ];

      $form = [];

      $form['periode'] = [
        '#type' => 'select',
        '#title' => 'Periode',
        '#options' => $periode_list,
        '#weight' => 2,
        '#ajax' => [
          'wrapper' => 'status-div',
          'callback' => '::formPeriodeCallback',
          //'effect' => 'fade',
          //'progress' => array('type' => 'throbber'),
        ],
      ];

      $periode = $form_state->getValue('periode', key($periode_list)); // default is current pointed key in periode_list

      switch ($periode) {
        case 'vandaag' :
          $datum_start = date('Y-m-d');
          $datum_eind = date('Y-m-d');
          break;
        case 'maand' :
          $datum_start = date('Y-m-d', mktime(0, 0, 0, date('n') - 1, date('j'), date('Y')));
          $datum_eind = date('Y-m-d'); //previous month
          break;
        case 'jaar' :
          $datum_start = date('Y-m-d', mktime(0, 0, 0, date('n'), date('j'), date('Y') - 1));
          $datum_eind = date('Y-m-d'); //previous year
          break;
        case 'tweejaar' :
          $datum_start = date('Y-m-d', mktime(0, 0, 0, date('n'), date('j'), date('Y') - 2));
          $datum_eind = date('Y-m-d'); //previous 2 year
          break;
        case 'seizoen' :
          $datum_start = date('Y') . '-01-01'; //this year
          $datum_eind = date('Y') . '-12-31';
          break;
        case 'anders' :
          if (!isset($form_state['values']['datum_start'])) {
            $datum = date('Y-m-d'); //default vandaag
          }
          else {
            $datum_start = $form_state['values']['datum_start'];
            $datum_eind = $form_state['values']['datum_eind'];
          }
      }

      $condition = [
        'datum' => [
          'value' => [$datum_start, $datum_eind],
          'operator' => 'BETWEEN'
        ],
      ];

      $dagverslagenCount = EzacVbaDagverslag::counter($condition);
      $dagverslagenLidCount = EzacVbaDagverslagLid::counter($condition);


      $condition = [
        'datum_aan' => [
          'value' => [$datum_start, $datum_eind],
          'operator' => 'BETWEEN'
        ],
      ];
      $bevoegdheidLidCount = EzacVbaBevoegdheid::counter($condition);

      $form['status'] = [
        '#type' => 'container',
        '#prefix' => '<div id="status-div">',
        '#suffix' => '</div>',
        '#weight' => 3,
      ];

      // Table tag attributes
      $attributes = [
        'border' => 1,
        'cellspacing' => 0,
        'cellpadding' => 5,
        'width' => '80%',
      ];

      $header = [
        ['data' => 'status', 'width' => '30%'],
        ['data' => 'aantal'], //, 'width' => 20
      ];

      $dagverslagenUrl = Url::fromRoute(
        'ezac_vba_dagverslagen',
        [
          'datum_start' => $datum_start,
          'datum_eind' => $datum_eind,
        ]
      )->toString();
      $dagverslagenLidUrl = Url::fromRoute(
        'ezac_vba_dagverslag_lid',
        [
        'datum_start' => $datum_start,
          'datum_eind' => $datum_eind,
        ]
      )->toString();
      $bevoegdheidLidUrl = Url::fromRoute(
        'ezac_vba_bevoegdheid_lid',
        [
        'datum_start' => $datum_start,
          'datum_eind' => $datum_eind,
        ]
      )->toString();

      $rows = [
        [
          t("<a href=$dagverslagenUrl>Dagverslagen</a>"),
          $dagverslagenCount
        ],
        [
          t("<a href=$dagverslagenLidUrl>Opmerkingen voor leden</a>"),
          $dagverslagenLidCount
        ],
        [
          t("<a href=$bevoegdheidLidUrl>Bevoegdheden voor leden</a>"),
          $bevoegdheidLidCount
        ],
      ];

      $form['status']['tabel'] = [
        '#theme' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#attributes' => $attributes,
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
  function formPeriodeCallback(array $form, FormStateInterface $form_state)
    {
        // Kies gewenste periode voor overzicht dagverslagen
        return $form['status'];
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {

    }

    /**
     * {@inheritdoc}
     * @throws \Exception
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {

    } //submitForm
}
