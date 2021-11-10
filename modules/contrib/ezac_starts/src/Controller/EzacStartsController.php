<?php

namespace Drupal\ezac_starts\Controller;

use Drupal;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

use Drupal\ezac_starts\Model\EzacStart;
use Drupal\ezac\Util\EzacUtil;

/**
 * Controller for EZAC start administration.
 */
class EzacStartsController extends ControllerBase {

  /**
   * Display the status of the EZAC starts table
   * @return array
   */
  public function status() {
    $content = [];

    //$schema = drupal_get_module_schema('Ezac', 'starts');

    // show record count for each Code value
    $headers = [
      t("Jaar"),
      t("Aantal vliegdagen"),
      t("Uitvoer"),
    ];
    
    $total = 0;
    $condition = [];
    $sortkey = [
      '#key' => 'datum',
      '#dir' => 'DESC',
    ];
    $datums = array_unique(EzacStart::index($condition, 'datum', $sortkey));
    $jaren = [];
    foreach ($datums as $datum) {
      $dp = date_parse($datum);
      $year = $dp['year'];
      if (isset($jaren[$year])) $jaren[$year]++;
      else $jaren[$year] = 1;
    }
    foreach ($jaren as $jaar => $aantal) {
      $count = $aantal;
      $total = $total+$count;
      $urlJaar = Url::fromRoute(
        'ezac_starts_overzicht',
        [
          'datum' => "$jaar-01-01:$jaar-12-31",
        ]
      )->toString();
      $urlExport = Url::fromRoute(
        'ezac_starts_export_jaar',
        [
          'filename' => "Starts-$jaar.csv",
          'jaar' => $jaar
        ]
      )->toString();
      $rows[] = [
        t("<a href=$urlJaar>$jaar</a>"),
        $count,
        t("<a href=$urlExport>Starts-$jaar.csv</a>"),
      ];
    }
    // add line for totals
    $urlExport = Url::fromRoute(
      'ezac_starts_export',
      [
        'filename' => "Starts.csv",
      ]
    )->toString();
    $rows[]= [
      t('Totaal'),
      $total,
      t("<a href=$urlExport>Starts.csv</a>"),
    ];
    //build table
    $content['table'] = [
      '#type' => 'table',
      '#caption' => t("Jaar overzicht van het EZAC STARTS bestand"),
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => t('Geen gegevens beschikbaar.'),
      '#sticky' => TRUE,
    ];

    // Don't cache this page.
    $content['#cache']['max-age'] = 0;

    return $content;
  }

    /**
     * Render a list of entries in the database.
     * @param string
     *  $jaar - categorie (optional)
     * @return array
     */
  public function overzichtJaar($jaar) {
    $content = array();

    $rows = [];
    $headers = [
        t('datum'),
        t('aantal starts'),
    ];

    // select all start dates for selected year
    $condition = [
        'datum' => [
            'value' => ["$jaar-01-01", "$jaar-12-31"],
            'operator' => 'BETWEEN'
        ],
    ];
    $from = null;
    $range = null;
    $field = 'datum';
    $sortkey = null;
    $sortdir = null;
    $unique = TRUE; // return unique results only

    // bepaal aantal dagen
    $total = count(EzacStart::index($condition, $field, $sortkey, $sortdir, $from, $range, $unique));

    // prepare pager
    $range = 120;
    $pager = Drupal::service('pager.manager')
      ->createPager($total, $range);
    $page = $pager
      ->getCurrentPage();

    $from = $range * $page;
    $field = 'datum';
    $sortkey = [
      '#key' => 'datum',
      '#dir' => 'ASC',
    ];

    $startsDates = EzacStart::index($condition, $field, $sortkey);
    $startsIndex = array_unique($startsDates);

    $dagen = [];
    foreach ($startsDates as $datum) {
      if (isset($dagen[$datum])) $dagen[$datum]++;
      else $dagen[$datum] = 1;
    }

    foreach ($startsIndex as $datum) {
      $urlString = Url::fromRoute(
        'ezac_starts_overzicht',  // show starts for datum
        [
          'datum' => $datum,
        ]
      )->toString();

      $d = EzacUtil::showDate($datum);
      $rows[] = [
        //link each record to overzicht route
        t("<a href=$urlString>$d"),
        $dagen[$datum],
      ];
    }
    $caption = "Overzicht EZAC Starts data voor $jaar";
    $content['table'] = [
      '#type' => 'table',
      '#caption' => $caption,
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => t('Geen gegevens beschikbaar.'),
      '#sticky' => TRUE,
    ];
    // add pager
    $content['pager'] = [
      '#type' => 'pager',
      '#weight' => 5
    ];
    // Don't cache this page.
    $content['#cache']['max-age'] = 0;

    return $content;
  } // overzichtJaar

  /**
   * @param string $datum YYYY-MM-DD[:YYYY-MM-DD]
   * @param string $vlieger null
   * @param boolean $detail true
   * @return array $content
   */
  public static function startOverzicht($datum, $vlieger = NULL, $detail = TRUE) {
    $messenger = Drupal::messenger();
    $errmsg = EzacUtil::checkDatum($datum, $datum_start, $datum_eind);
    if ($errmsg != "") {
      $messenger->addMessage("Foutieve datum [$errmsg]",'error');
      return [];
    }
    // lees volledige ledenlijst
    $leden = EzacUtil::getLeden();

    $d = EzacUtil::showDate($datum_start);
    $intro = "<h2>Starts van $d";
    if (isset($datum_eind) and $datum_eind != $datum_start) {
      $d = EzacUtil::showDate($datum_eind);
      $intro .= " tot $d";
    }
    if (isset($vlieger) and ($vlieger != null)) {
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

    // select all starts for selected dates
    if (isset($datum_eind))
    {
      $condition['datum'] =
        [
          'value' => [$datum_start, $datum_eind],
          'operator' => 'BETWEEN',
        ];
    }
    else $condition = ['datum' => $datum_start];

    if (isset($vlieger) and ($vlieger != null)) {
      // add orGroup to selection
      $condition['OR'] =
        [
          'gezagvoerder' => $vlieger,
          'tweede' => $vlieger,
        ];
    }

    // detail overzicht van starts
    $rows = [];
    $headers = [
      t('datum'),
      t('start'),
      t('landing'),
      t('duur'),
      t('registratie'),
      t('gezagvoerder'),
      t('tweede'),
      t('soort'),
      t('start methode'),
      t('instructie'),
      t('opmerking'),
    ];

    // prepare pager
    $total = EzacStart::counter($condition);
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

    if ($detail) {
      // @todo pager werkt niet goed, error bij display 2e pagina bij limit = 100 (memory overrun)
      $limit = 200; // set only when details requested
      $pager = Drupal::service('pager.manager')
        ->createPager($total, $limit);
      $page = $pager
        ->getCurrentPage();
      $from = $limit * $page;
    }
    else {
      $limit = null;
      $from = null;
    }

    $unique = FALSE; // return all results

    $startsIndex = EzacStart::index($condition, $field, $sortkey, $sortdir, $from, $limit, $unique);
    foreach ($startsIndex as $id) {
      $start = new EzacStart($id);

      if (isset($leden[$start->gezagvoerder]) && $start->gezagvoerder <> '') {
        $gezagvoerder = $leden[$start->gezagvoerder];
      }
      else $gezagvoerder = $start->gezagvoerder; // un-edited record value

      if (isset($leden[$start->tweede]) && $start->tweede <> '') {
        $tweede = $leden[$start->tweede];
      }
      else $tweede = $start->tweede; // un-edited record value

      if (key_exists($start->soort, EzacStart::$startSoort)) {
        $startSoort = EzacStart::$startSoort[$start->soort];
      }
      else $startSoort = $start->soort;

      if (key_exists($start->startmethode, EzacStart::$startMethode)) {
        $startMethode = EzacStart::$startMethode[$start->startmethode];
      }
      else $startMethode = $start->startmethode;

      // start mag worden gewijzigd als het een eigen start is of permission EZAC_update_all aanwezig
      $eigen_afkorting = EzacUtil::getUser();
      if (($start->gezagvoerder == $eigen_afkorting) or ($start->tweede == $eigen_afkorting)
      or Drupal::currentUser()->hasPermission('EZAC_update_all')) {
        // toon tijd als link naar edit start
        $urlString = Url::fromRoute(
          'ezac_starts_update',  // edit starts record
          ['id' => $start->id]
        )->toString();
        $tijd = t("<a href=$urlString>" .substr($start->start, 0, 5) ."</a>");
      }
      else {
        // toon tijd zonder link
        $tijd = substr($start->start, 0, 5);
      }

      $rows[] = [
        //link each record to edit route
        EzacUtil::showDate($start->datum),
        $tijd,
        substr($start->landing,0,5),
        substr($start->duur, 0,5),
        $start->registratie,
        $gezagvoerder,
        $tweede,
        $startSoort,
        $startMethode,
        ($start->instructie) ? 'Ja' :'',
        $start->opmerking,
      ];

      // start D7 code
      // display results
      // zet in de tabel
      $registratie = $start->registratie;
      $soort = $start->soort;
      //tel starts per startmethode
      $startmethodes[$startMethode]['aantal'] =
        (isset($startmethodes[$startMethode]['aantal']))
          ? $startmethodes[$startMethode]['aantal'] + 1
          : 1;

      $duur_hhmm = explode(':', $start->duur);
      if (key_exists(1, $duur_hhmm)) {
        $duur_minuten = $duur_hhmm[0] * 60 + $duur_hhmm[1];
      }
      else {
        $duur_minuten = 0;

      }

      $kist[$registratie] = [
        'aantal' =>
          (isset($kist[$registratie]['aantal']))
            ? $kist[$registratie]['aantal'] + 1
            : 1,//increase number of starts for kist
        'duur'   =>
          (isset($kist[$registratie]['duur']))
            ? $kist[$registratie]['duur']+ $duur_minuten
            : $duur_minuten,
      ];

      $soort_tellers[$soort]['aantal'] =
        (isset($soort_tellers[$soort]['aantal']))
          ? $soort_tellers[$soort]['aantal'] + 1
          : 1;
      $soort_tellers[$soort]['duur'] =
        (isset($soort_tellers[$soort]['duur']))
          ? $soort_tellers[$soort]['duur'] + $duur_minuten
          : $duur_minuten;


    }

    //toon totalen per kist
    //Set up the table Headings
    $header2 = array(
      array('data' => t('kist')),
      array('data' => t('aantal')),
      array('data' => t('duur')),
    );

    if (isset($kist)) {
      $total_count = 0;
      $total_time = 0;
      $outputat = '%02u:%02u';
      foreach ($kist as $registratie => $value) {
        $hours = intval($value['duur'] / 60);
        $minutes = $value['duur'] - ($hours * 60);
        $row2[] = array(
          $registratie,
          $value['aantal'],
          sprintf($outputat, $hours, $minutes),
        );
        $total_count = $total_count + $value['aantal'];
        $total_time = $total_time + $value['duur'];
      } //foreach kist
      $hours = intval($total_time / 60);
      $minutes = $total_time - ($hours * 60);
      $row2[] = array ( //provide totals line
        t('Totaal'),
        $total_count,
        sprintf($outputat, $hours, $minutes),
      );
      $content[2]['#theme'] = 'table';
      $content[2]['#header'] = $header2;
      if (isset($row2)) $content[2]['#rows'] = $row2;
      $content[2]['#empty'] = t('Geen gegevens beschikbaar');
      $content[2]['#weight'] = 2;
    } //if kist

    //show starts per startmethode
    $header3 = array(
      array('data' => t('startmethode')),
      array('data' => t('aantal')),
    );

    if (isset($startmethodes)) {
      $total_count = 0;
      foreach ($startmethodes as $soort => $value) {
        $row3[] = array(
          $soort,
          $value['aantal'],
        );
        $total_count = $total_count + $value['aantal'];
      }
      $row3[] = array ( //provide totals line
        t('Totaal'),
        $total_count,
      );
      $content[3]['#theme'] = 'table';
      $content[3]['#header'] = $header3;
      if (isset($row3)) $content[3]['#rows'] = $row3;
      $content[3]['#empty'] = t('Geen gegevens beschikbaar');
      $content[3]['#weight'] = 3;
    } //if startmethode

    //show starts per soort
    $header4 = array(
      array('data' => t('soort')),
      array('data' => t('aantal')),
      array('data' => t('duur')),
    );

    if (isset($soort_tellers)) {
      foreach ($soort_tellers as $teller => $value) {
        $hours = intval($value['duur'] / 60);
        $minutes = $value['duur'] - ($hours * 60);
        $row4[] = array(
          $teller, //@TODO make CASE for SOORTen NORM DONA PASS CLUB
          $value['aantal'],
          sprintf($outputat, $hours, $minutes),
        );
      } //foreach teller
      $content[4]['#theme'] = 'table';
      $content[4]['#header'] = $header4;
      if (isset($row4)) $content[4]['#rows'] = $row4;
      $content[4]['#empty'] = t('Geen gegevens beschikbaar');
      $content[4]['#weight'] = 4;
    } //if soort-tellers

    if ($detail) {
      // show details only when required
      $d = EzacUtil::showDate($datum_start);
      if ($datum_eind <> $datum_start) {
        $d .= " tot " . EzacUtil::showDate($datum_eind);
      }
      $caption = "Overzicht EZAC Starts bestand $d";
      $content['table'] = [
        '#type' => 'table',
        '#caption' => $caption,
        '#header' => $headers,
        '#rows' => $rows,
        '#empty' => t('Geen gegevens beschikbaar.'),
        '#sticky' => TRUE,
        '#weight' => 5,
      ];
      // add pager
      $content['pager'] = [
        '#type' => 'pager',
        '#weight' => 6,
      ];
    }
    // Don't cache this page.
    $content['#cache']['max-age'] = 0;

    return $content;
  }


  /**
     * Maak exportbestand uit Starts tabel
     * geformatteerd voor input in bestand (csv)
     * Output via html headers naar attachment
     *
     * @param string $filename
     * @param null $jaar
     * @return mixed Response output text in csv format
     *   output text in csv format
     */
  public function export($filename = 'Ezac.txt', $jaar = NULL) {

    $messenger = Drupal::messenger();

    // lees alle leden met een afkorting
    $condition = [
      'afkorting' => [
        'value' => '',
        'operator' => '<>',
      ],
    ];
    $leden = EzacUtil::getLeden($condition);
    unset($leden['']); // remove 'Onbekend'

    if ($filename == '') $filename = 'Ezac.txt';

    // Determine Jaar  from Starts for export
    if (isset($jaar)) {
        $condition = [
            'datum' => [
                'value' => ["$jaar-01-01", "$jaar-12-31"],
                'operator' => 'BETWEEN'
            ],
        ];
    }
    else $condition = []; //select all active records

    $records = EzacStart::index($condition); //read records index
    $count = count($records);
    $messenger->addMessage("Export $count records voor jaar [$jaar] naar bestand [$filename]"); //DEBUG

    $output = ""; // initialize output
    //build header line
    foreach (EzacStart::$fields as $field => $description) {
      $output .= '"' .$field .'";';
    }
    //remove last ";" 
    $output = rtrim($output, ";") ."\r\n";

    // export all records
    foreach ($records as $id) {
      $start = new EzacStart($id);
      // add all fields
      foreach (EzacStart::$fields as $field => $description) {
        // replace afkorting with naam
        if ($field == 'gezagvoerder' or $field == 'tweede') {
          if (key_exists($start->$field, $leden))
            $naam = $leden[$start->$field];
          else $naam = $start->$field;
          $output .= sprintf('"%s";', $naam);
        }
        else $output .= sprintf('"%s";',$start->$field);
      }
      //remove last ";" 
      $output = rtrim($output, ";") ."\r\n";
    }

    $response = new Response(
      $output,
      Response::HTTP_OK,
      array(
        'content-type' => 'text/plain',
      )
    );

    $disposition = $response->headers->makeDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      $filename
    );
    $response->headers->set('Content-Disposition', $disposition);
    $response->setCharset('UTF-8');

      /** @var mixed $response */
      return $response;
  } // export  
  
} //class EzacStartsController
