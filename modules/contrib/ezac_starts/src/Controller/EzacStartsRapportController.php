<?php

namespace Drupal\ezac_starts\Controller;

use Drupal;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

use Drupal\ezac_starts\Model\EzacStart;
use Drupal\ezac\Util\EzacUtil;

/**
 * Controller for EZAC start administration.
 */
class EzacStartsRapportController extends ControllerBase {

  public static function startRapport($datum = NULL, $vlieger = NULL) {
    $messenger = Drupal::messenger();

    if ($datum = null or $datum == '') {
      $datum_start = date('Y'). '-01-01';
      $datum_eind = date('Y'). '-12-31';
    }
    else {
      // splits datum
      $errmsg = EzacUtil::checkDatum($datum, $datum_start, $datum_eind);
      if ($errmsg != "") {
        $messenger->addMessage("Foutieve datum [$errmsg]", 'error');
        return [];
      }
    }
    // lees volledige ledenlijst
    $condition = [
      'code' => 'VL',
      'actief' => true,
    ];
    $leden = EzacUtil::getLeden($condition);
    unset ($leden['']); // remove 'Onbekend'

    $d = EzacUtil::showDate($datum_start);
    $intro = "<h2>Starts van $d";
    if (isset($datum_eind) and $datum_eind != $datum_start) {
      $d = EzacUtil::showDate($datum_eind);
      $intro .= " tot $d";
    }
    if (isset($vlieger) and ($vlieger != NULL)) {
      $intro .= " voor $leden[$vlieger]";
    }
    $intro .= "</h2>";

    $content = [
      'caption' => [
        '#type' => 'markup',
        '#markup' => t($intro),
        '#weight' => 0,
      ],
    ];

    foreach ($leden as $afkorting => $naam) {
      $dagen = self::index_starts_by_date($datum_start, $datum_eind, $afkorting, null);
      if (isset($dagen)) {
        $totaal = 0;
        $aantal_dagen = 0;
        $eigen_aantal = 0;
        $eigen_duur = 0;
        $duur = 0; // vluchtduur in minuten
        foreach ($dagen as $dag => $aantal) { //aantal is array van [aantal] en [duur]
          $totaal += $aantal['aantal'];
          $duur += $aantal['duur'];
          $eigen_aantal += $aantal['eigen_aantal'];
          $eigen_duur += $aantal['eigen_duur'];
          $aantal_dagen += 1;
        }
        $hours = intval($duur / 60);
        $minutes = $duur - ($hours * 60);
        $hours2 = intval($eigen_duur / 60);
        $minutes2 = $eigen_duur - ($hours2 * 60);

        $dat = (is_array($datum))
          ? "$datum[0]:$datum[1]"
          : $datum;

        // prepare link to overzicht lid
        $urlString = Url::fromRoute(
          'ezac_starts_overzicht_lid',  // show starts
          [
            'datum' => "$datum_start:$datum_eind",
            'vlieger' => $afkorting,
          ]
        )->toString();

        $row[] = array(
          t("<a href=$urlString>$naam</a>"),
          //'<a href=' .BASE_PATH() .'startlijst/browse/' .$dat .'/' .$afkorting .'>' .$naam .'</a>',
          $aantal_dagen,
          $dag,
          $totaal,
          sprintf('%02u:%02u', $hours, $minutes),
          $eigen_aantal,
          sprintf('%02u:%02u', $hours2, $minutes2),
        );
      }
    }

    // Table tag attributes
    $attributes = array(
      'border'      => 1,
      'cellspacing' => 0,
      'cellpadding' => 5,
      'width'	  => '90%'
    );

    //Set up the table Headings
    $header = array(
      array('data' => t('naam')),
      array('data' => t('aantal<br>vliegdagen')),
      array('data' => t('laatste dag')),
      array('data' => t('totaal<br>aantal starts')),
      array('data' => t('totaal<br>duur')),
      array('data' => t('werkuren<br>aantal starts')),
      array('data' => t('werkuren<br>duur')),
    );

    $form_element['#theme'] = 'table';
    $form_element['#attributes'] = $attributes;
    $form_element['#header'] = $header;
    $form_element['#sticky'] = TRUE;

    if (isset($row)) $form_element['#rows'] = $row;
    $form_element['#empty'] = t('Geen gegevens beschikbaar');
    //$form_element['#weight'] = 3;

    $content['table'] = $form_element;

    // add pager
    $content['pager'] = [
      '#type' => 'pager',
      '#weight' => 5
    ];
    // Don't cache this page.
    $content['#cache']['max-age'] = 0;

    return $content;

  } //startRapport

  /**
   * Index starts records
   * @param string $datumStart timestamp
   *       lower boundary
   * @param string $datumEnd timestamp
   *       higher boundary
   * @param string $naam optional
   * @param string $registratie optional
   * @return array
   *       index list of ezac_Starts records within Datum boundaries
   *           [aantal] - number of starts
   *           [duur] - total duration in minutes
   *           [eigen_aantal] - number of starts for club work assignment
   *           [eigen_duur] - duration in minutes for club work assignment
   *
   */
  public static function index_starts_by_date($datum_start, $datum_eind, $vlieger, $registratie) {
    // select all starts for selected dates
    if (isset($datum_eind)) {
      $condition['datum'] =
        [
          'value' => [$datum_start, $datum_eind],
          'operator' => 'BETWEEN',
        ];
    }
    else {
      $condition = ['datum' => $datum_start];
    }

    if (isset($vlieger) and ($vlieger != NULL)) {
      // add orGroup to selection
      $condition['OR'] =
        [
          'gezagvoerder' => $vlieger,
          'tweede' => $vlieger,
        ];
    }

    // prepare pager
    $field = 'id';
    $sortkey = [
      [
        '#key' => 'datum',
        '#dir' => 'ASC',
      ],
      [
        '#key' => 'start',
        '#dir' => 'ASC',
      ],
    ];
    $sortdir = 'ASC'; // deprecated

    $startsIndex = EzacStart::index($condition, $field, $sortkey, $sortdir);
    foreach ($startsIndex as $id) {
      $start = new EzacStart($id);

      $datum = substr($start->datum,0,10);
      $duur  = substr($start->duur,0,5); //strip seconds
      $duur_hhmm = explode(':', $duur);
      $duur_minuten = (int) $duur_hhmm[0] * 60 + (int) $duur_hhmm[1];

      /* Check of de vlucht voor eigen rekening is of club / prive */
      if (isset($vlieger)) { // only when selected list is for a specific person
        if (in_array($start->startmethode, array('S', 'M')) // sleep (tow) or motor (self launch)
          or (($start->tweede == $vlieger) and ($start->instructie == FALSE))// not PIC
          or (($start->gezagvoerder == $vlieger) and ($start->instructie == TRUE))// instructor
          or (in_array($start->soort, array('CLUB', 'DONA', 'PASS'))) //CLUB, DONATEUR or PASSENGER flight
        )
        {
          $eigen_minuten = 0;
        }
        else $eigen_minuten = $duur_minuten;
      }

      if (!isset($index[$datum])) {
        $index[$datum]['aantal'] = 1;
        $index[$datum]['duur'] = $duur_minuten;
        if ($eigen_minuten > 0) $index[$datum]['eigen_aantal'] = 1;
        else $index[$datum]['eigen_aantal'] = 0;
        $index[$datum]['eigen_duur'] = $eigen_minuten;
      }
      else {
        $index[$datum]['aantal']++; // increment value for each record
        $index[$datum]['duur'] += $duur_minuten;
        if ($eigen_minuten > 0) $index[$datum]['eigen_aantal']++;
        $index[$datum]['eigen_duur'] += $eigen_minuten;
      }
    } //foreach
    if (isset($index)) return $index;
    else return [];
  }

} //class EzacStartsController
