<?php

namespace Drupal\ezac_passagiers\Controller;

use Drupal;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

use Drupal\ezac_passagiers\Model\EzacPassagier;
use Drupal\ezac_passagiers\Model\EzacPassagierDag;

/**
 * Controller for EZAC passagiers
 */
class EzacPassagiersSlotsController extends ControllerBase {

  public static function slots() {
    $messenger = Drupal::messenger();
    setlocale(LC_ALL, 'NL-nl');

    // read settings
    $settings = Drupal::config('ezac_passagiers.settings');
    $slots = $settings->get('slots');
    $optie_tijd = $settings->get('parameters.optie_tijd');
    $texts = $settings->get('texts');
    $parameters = $settings->get('parameters');

    // Wrap the form in a div.
    $form = [
      '#prefix' => '<div id="reserveringform">',
      '#suffix' => '</div>',
    ];

    if ($texts['mededeling'] != '') {
      $form['mededeling'] = [
        '#type' => 'markup',
        '#markup' => t($texts['mededeling']),
        '#weight' => 0,
      ];
    }

    // Opschonen opties die zijn vervallen
    $limit = date('Y-m-d H:i:s', time() - $optie_tijd); // G:i:s veranderd in H:i:s
    $condition = [
      'status' => $parameters['reservering_optie'],
      'aangemaakt' => [
        'value' => $limit,
        'operator' => '<',
      ],
    ];
    $optiesIndex = EzacPassagier::index($condition);
    if (count($optiesIndex) > 0) {
      foreach ($optiesIndex as $id) {
        $optie = new EzacPassagier($id);
        $naam = $optie->naam;
        $tijd = $optie->tijd;
        $datum = $optie->datum;
        $aangemaakt = $optie->aangemaakt;
        $reden = "De reservering van $aangemaakt is niet op tijd bevestigd";
        //verwijder reservering en mail passagier
        EzacPassagiersController::verwijderen($id, $reden);
        if (!empty(Drupal::currentUser()->getAccountName())) { // toon alleen voor aangemelde gebruikers
          $messenger->addMessage("Optie van $naam op $datum $tijd is vervallen wegens te late bevestiging", 'status');
        }
      }
    }

    //toon lijst met beschikbare datums vanaf vandaag of eerste datum, per maand
    $heden = date('Y-m-d');

    //lees slot tijden uit ezac_Passagiers_Slots
    // 200314 - aangepast voor zaterdag en zondag slots
    $aantal_slots_zaterdag = 0;
    $aantal_slots_zondag = 0;
    foreach ($slots as $slot) {
      if ($slot['zaterdag'] == 1) {
        $aantal_slots_zaterdag++;
      }
      if ($slot['zondag'] == 1) {
        $aantal_slots_zondag++;
      }
    }
    $aantal_slots = $aantal_slots_zaterdag + $aantal_slots_zondag;

    //lees beschikbare dagen uit ezac_Passagiers_Dagen vanaf $heden
    $condition = [
      'datum' => [
        'value' => $heden,
        'operator' => '>=',
      ],
    ];
    $dagen = EzacPassagierDag::index($condition, 'datum');

    $slots_vrij = 0;
    foreach ($dagen as $dag) {
      $plaatsen[$dag]['datum'] = $dag;
      $plaatsen[$dag]['slots_vrij'] = $aantal_slots;

      $weekday = date('l', strtotime($dag));
      if ($weekday == 'Saturday') {
        $slots_vrij += $aantal_slots_zaterdag; // init potential number of free slots for Saturday
      }
      if ($weekday == 'Sunday') {
        $slots_vrij += $aantal_slots_zondag; // init potential number of free slots for Sunday
      }

      // $slots_vrij += $aantal_slots; // initialize potential number of free slots - old version
      foreach ($slots as $tijd => $slot) {
        //$plaatsen[$dag][$slot] = ''; // indicate free slot
        // changed 200314
        if (($slot['zaterdag'] == 1) && ($weekday == 'Saturday')) {
          $plaatsen[$dag][$tijd] = '';
        } // indicate free slot Saturday
        if (($slot['zondag'] == 1) && ($weekday == 'Sunday')) {
          $plaatsen[$dag][$tijd] = '';
        } // indicate free slot Sunday
      }
    }
    //lees reserveringen uit ezac_Passagiers vanaf $heden
    $condition = [
      'datum' => [
        'value' => $heden,
        'operator' => '>=',
      ],
    ];
    $reserveringenIndex = EzacPassagier::index($condition);
    foreach ($reserveringenIndex as $id) {
      $reservering = new EzacPassagier($id);
      $dat = substr($reservering->datum, 0, 10); //skip anything after date
      $tijd = substr($reservering->tijd, 0, 5); //skip seconds part
      $plaatsen[$dat]['slots_vrij']--; // = $plaatsen[$dat]['slots_vrij'] - 1;
      $slots_vrij--; // decrease number of available slots
      $plaatsen[$dat][$tijd] = $reservering->naam; // indicate used slot
    }

    $form[0]['#type'] = 'markup';
    if ($slots_vrij == 0) { // no slots available
      $form[0]['#markup'] = $texts['geen_plaatsen'];
    }
    else {
      $form[0]['#markup'] = $texts['kies_dag'];
      $form[0]['#weight'] = 0;
    }
    $form[0]['#prefix'] = '<div class="ezacpass-intro-div">';
    $form[0]['#suffix'] = '</div>';
    // tabel datum/dag en slot tijden in header. 'vrij' in cel met link
    //  table header
    // Table tag attributes
    $attributes = [
      'border' => 1,
      'cellspacing' => 0,
      'cellpadding' => 5,
      'width' => '90%',
    ];

    //Set up the table Headings
    $header[]['data'] = t('Datum');
    foreach ($slots as $tijd => $slot) {
      $header[]['data'] = t($tijd);
    }
    if (isset($plaatsen)) { //tabel alleen aanmaken als er plaatsen zijn
      foreach ($plaatsen as $dag => $plaats) {
        // table rows
        unset($row);
        $row[] = t(date('D j M Y', strtotime($dag))); // dag dd mmm jjjj
        foreach ($slots as $tijd => $slot) {
          if (key_exists($tijd, $plaats) and $plaats[$tijd] == '') {
            $url = Url::fromRoute(
              'ezac_passagiers_boeking',
              [
                'datum' => $dag,
                'tijd' => $tijd,
              ]
            )->toString();
            $row[] = t("<a href=$url>vrij</a>");
          }
          else {
            $row[] = '';
          } // no link
        }
        $rows[] = $row;
      }
    }
    if (isset($row)) {
      $form[1]['#theme'] = 'table';
      $form[1]['#attributes'] = $attributes;
      $form[1]['#header'] = $header;
      $form[1]['#rows'] = $rows;
      $form[1]['#empty'] = t('Geen gegevens beschikbaar');
      $form[1]['#weight'] = 1;
    }
    // Don't cache this page.
    $form['#cache']['max-age'] = 0;

    //D7 code ends
    return $form;
  }

} //class EzacPassagiersSlotsController

