<?php


namespace Drupal\ezac_starts\Plugin\rest\resource;

use Drupal;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\ezac_starts\Model\EzacStart;

  /**
   * Provides a resource for starts table reads
   *
   * @RestResource(
   *   id = "ezac_starts_resource",
   *   label = @Translation("EZAC starts table"),
   *   uri_paths = {
   *     "canonical" = "/api/v2/starts",
   *     "create" = "/api/v2/starts"
   *   }
   * )
   */
class EzacStartsResource extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  /*
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->logger = $container->get('logger.factory')->get('ezac_starts');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }
  */
  /**
   * @param $datum
   * @param $datumStart
   * @param $datumEnd
   *
   * @return string
   */
  private function check_datum($datum, &$datumStart, &$datumEnd): string {
    $errmsg = '';
    $datum_delen = explode('-', $datum);
    switch (strlen($datum)) {
      case 4: //YYYY
        if (!checkdate(01, 01, $datum_delen[0])) {
          $errmsg = 'Invalid value parameter datum YYYY [' .$datum .']';
        }
        $datumStart = $datum .'-01-01';
        $datumEnd   = $datum .'-12-31';
        break;
      case 7: //YYYY-MM
        if (!checkdate($datum_delen[1], 01, $datum_delen[0])) {
          $errmsg = 'Invalid value parameter datum YYYY-MM [' .$datum .']';
        }
        $datumStart = $datum .'-01';
        if     (checkdate($datum_delen[1], 31, $datum_delen[0])) $datumEnd = $datum .'-31';
        elseif (checkdate($datum_delen[1], 30, $datum_delen[0])) $datumEnd = $datum .'-30';
        elseif (checkdate($datum_delen[1], 29, $datum_delen[0])) $datumEnd = $datum .'-29';
        elseif (checkdate($datum_delen[1], 28, $datum_delen[0])) $datumEnd = $datum .'-28';
        break;
      case 10: //YYYY-MM-DD
        if (!checkdate($datum_delen[1], $datum_delen[2], $datum_delen[0])) { //mm dd yyyy
          $errmsg = 'Invalid value parameter datum YYYY-MM-DD [' .$datum .']';
        }
        $datumStart = $datum; // .' 00:00:00');
        $datumEnd   = $datum; // .' 23:59:59');
        break;
      default: //invalid
        $errmsg = 'Invalid length parameter datum [' .$datum .']';
    }
    return $errmsg;
  }

  /**
   * Responds to GET requests.
   *
   * Returns a starts table record for the specified ID.
   *
   * @param null $id
   *   The ID of the leden record.
   * @param string $datum
   *    Datum YYYY[-MM[-DD]] or date range YYYY-MM-DD:YYYY-MM-DD
   * @param string $naam
   *    Naam for gezagvoerder or tweede in start records
   * @param string $registratie
   *    Registratie in start recods
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the leden record or array of records.
   *
   */
  public function get(): ResourceResponse {

    // Configure caching settings.
    $build = [
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    //get parameters
    $id = Drupal::request()->query->get('id');
    $datum = Drupal::request()->query->get('datum');
    $naam = Drupal::request()->query->get('naam');
    $registratie = Drupal::request()->query->get('registratie');

    // when id given, read that record
    if (isset($id) && ($id != '')) {
      // return record for id
      $record = (new EzacStart)->read($id);
      if (!empty($record)) {
        return (new ResourceResponse((array) $record))->addCacheableDependency($build);
      }
      throw new NotFoundHttpException("Invalid ID: $id");
    }

    //parse $datum
    if (!isset($datum) or $datum == NULL) {
      $datum = date('Y-m-d'); // defaults to today
    }
    //if $datum is a range, split and process
    // range is indicated by date:date format
    if (strpos($datum, ':')) {
      $datum_range = explode(':', $datum);
      // eerste datum is $datum_range[0]
      // tweede datum is [1]
      // take datumStart from first date
      $errmsg = self::check_datum($datum_range[0], $datumStart, $de);
      if ($errmsg != '') {
        // invalid date
        throw new NotFoundHttpException($errmsg);
      }
      //take datumEnd from second date
      $errmsg = self::check_datum($datum_range[1], $ds, $datumEnd);
      if ($errmsg != '') {
        // invalid date
        throw new NotFoundHttpException($errmsg);
      }
    }
    else { // single date
      $errmsg = self::check_datum($datum, $datumStart, $datumEnd);
      if ($errmsg != '') {
        // invalid date
        throw new NotFoundHttpException($errmsg);
      }
    }

    // build selection condition
    $condition = [
      'datum' => [
        'value' => [$datumStart, $datumEnd],
        'operator' => 'BETWEEN',
      ]
    ];

    // registratie
    if (isset($registratie)) {
      //@TODO test valid registratie values
      $condition ['registratie'] = $registratie;
    }

    // naam
    if (isset($naam)) {
      $condition['OR'] = [
        'gezagvoerder' => $naam,
        'tweede' => $naam,
      ];
    }

    if ($condition != []) {
      // only send response when selection criteria are given
      $startsIndex = EzacStart::index($condition);
      if (isset($id) && ($id == '')) {
        // empty id value indicates index request
        return (new ResourceResponse($startsIndex))->addCacheableDependency($build);
      }
      else {
        // return selected starts records
        $result = [];
        foreach ($startsIndex as $id) {
          $result[] = (array) (new EzacStart)->read($id);
        }
        return (new ResourceResponse($result))->addCacheableDependency($build);
      }
    }
    else {
      // no parameter given
      throw new BadRequestHttpException('No valid parameter provided');
    }
  } //get

  /**
   * Responds to POST requests.
   *
   * @param $datum
   * @param $registratie
   * @param $gezagvoerder
   * @param $tweede
   * @param $soort
   * @param $startmethode
   * @param $start
   * @param $landing
   * @param $duur
   * @param $instructie
   * @param $opmerking
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   */
  public function post(): ModifiedResourceResponse {

    // Use current user after pass authentication to validate access.
    /*
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }
    */

    //get parameters
    $datum = Drupal::request()->query->get('datum');
    $registratie = Drupal::request()->query->get('registratie');
    $gezagvoerder = Drupal::request()->query->get('gezagvoerder');
    $tweede = Drupal::request()->query->get('tweede');
    $soort = Drupal::request()->query->get('soort');
    $startmethode = Drupal::request()->query->get('startmethode');
    $start = Drupal::request()->query->get('start');
    $landing = Drupal::request()->query->get('landing');
    $duur = Drupal::request()->query->get('duur');
    $instructie = Drupal::request()->query->get('instructie');
    $opmerking = Drupal::request()->query->get('opmerking');

    // Build start record
    $start_record = new EzacStart();

    // datum
    if (isset($datum)) {
      // check datum
      $dc = explode('-', $datum);
      if (!checkdate($dc[1], $dc[2], $dc[0])) {
        throw new BadRequestHttpException("Invalid datum provided: $datum");
      }
      $start_record->datum = $datum;
    }
    else {
      throw new BadRequestHttpException('No datum parameter provided');
    }

    // registratie
    if (isset($registratie)) {
      //check registratie to be valid format xx-xxxx
      if (!strpos($registratie, '-')) {
        throw new BadRequestHttpException("Invalid registratie: $registratie");
      }
      $start_record->registratie = $registratie;
    }
    else {
      throw new BadRequestHttpException('No registratie parameter provided');
    }

    // gezagvoerder
    if (isset($gezagvoerder)) {
      $start_record->gezagvoerder = substr($gezagvoerder, 0, 20);
    }
    else {
      throw new BadRequestHttpException('No gezagvoerder parameter provided');
    }

    // tweede
    if (isset($tweede)) {
      $start_record->tweede = substr($tweede, 0, 20);
    }
    // soort
    if (isset($soort) && $soort != '') {
      if (!array_key_exists($soort, EzacStart::$startSoort)) {
        throw new BadRequestHttpException("Invalid soort: $soort");
      }
      $start_record->soort = $soort;
    }

    // startmethode
    if (isset($startmethode)) {
      if (!array_key_exists($startmethode, EzacStart::$startMethode)) {
        throw new BadRequestHttpException("Invalid startmethode: $startmethode");
      }
      $start_record->startmethode = $startmethode;
    }
    else {
      throw new BadRequestHttpException('No startmethode parameter provided');
    }

    // start
    if (isset($start)) {
      // check valid time
      if (!in_array(strlen($start), array(5, 8))) { //HH:MM[:SS]
        throw new BadRequestHttpException("Invalid start time: $start");
      }
      $start_delen = explode(':',$start);
      if (intval($start_delen[0] > 23)
        or (intval($start_delen[1] > 59))) {
        throw new BadRequestHttpException("Invalid start time: $start");
      }
      $start = strtotime("$datum $start");
      $start_record->start = date('Y-m-d H:i:s',$start); //construct valid datetime format
    }
    else {
      // no start provided
      $start_record->start = date('Y-m-d H:i:s', 0);
    }

    // landing
    if (isset($landing)) {
      // check valid time
      if (!in_array(strlen($landing), array(5, 8))) { //HH:MM[:SS]
        throw new BadRequestHttpException("Invalid landing time: $landing");
      }
      $landing_delen = explode(':',$landing);
      if (intval($landing_delen[0] > 23)
        or (intval($landing_delen[1] > 59))) {
        throw new BadRequestHttpException("Invalid landing time: $landing");
      }
      $landing = strtotime("$datum $landing");
      if ($landing < $start) {
        throw new BadRequestHttpException("Landing before start $landing");
      }
      $start_record->landing = date('Y-m-d H:i:s',$landing); //construct valid datetime format
      //duur is calculated
      $start_record->duur = date('Y-m-d H:i:s', $landing-$start);
    }
    else {
      // no landing provided
      $start_record->landing = date('Y-m-d H:i:s', 0);
      $start_record->duur = date('Y-m-d H:i:s', 0);
    }

    // duur is calculated from start-landing

    // instructie
    if (isset($instructie)) {
      if (!in_array(intval($instructie), [0, 1])) {
        throw new BadRequestHttpException("Invalid instructie: $instructie");
      }
      $start_record->instructie = intval($instructie);
    }

    // opmerking
    //opmerking is optional, max 30 chars
    if (isset($opmerking)) {
      $start_record->opmerking = substr(trim($start_record->opmerking), 0, 30);
    }

    // write start record to database
    $record = $start_record->create();
    return new ModifiedResourceResponse($record->id, 200);
  } // post

}