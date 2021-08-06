<?php


namespace Drupal\ezac_starts\Plugin\rest\resource;

use DateTime;
use Drupal;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\ezac_starts\Model\EzacStart;
use Drupal\ezac\Util\EzacUtil;

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
      $record = new EzacStart($id);
      if (!empty($record)) {
        return (new ResourceResponse((array) $record))->addCacheableDependency($build);
      }
      throw new NotFoundHttpException("Invalid ID: $id");
    }

    //parse $datum
    if (!isset($datum) or $datum == '') {
      $datum = date('Y-m-d'); // defaults to today
    }
    //if $datum is a range, split and process
    // range is indicated by date:date format
    if (strpos($datum, ':')) {
      $datum_range = explode(':', $datum);
      // eerste datum is $datum_range[0]
      // tweede datum is [1]
      // take datumStart from first date
      $errmsg = EzacUtil::checkDatum($datum_range[0], $datumStart, $de);
      if ($errmsg != '') {
        // invalid date
        throw new NotFoundHttpException($errmsg);
      }
      //take datumEnd from second date
      $errmsg = EzacUtil::checkDatum($datum_range[1], $ds, $datumEnd);
      if ($errmsg != '') {
        // invalid date
        throw new NotFoundHttpException($errmsg);
      }
    }
    else { // single date
      $errmsg = EzacUtil::checkDatum($datum, $datumStart, $datumEnd);
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
          $result[] = (array) new EzacStart($id);
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
   * @param $id
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
   * @return \Drupal\ezac_starts\Model\EzacStart
   */
  private function processStart($id,
                                $datum,
                                $registratie,
                                $gezagvoerder,
                                $tweede,
                                $soort,
                                $startmethode,
                                $start,
                                $landing,
                                $duur,
                                $instructie,
                                $opmerking): EzacStart {
    // Build start record
    $start_record = new EzacStart();

    // id
    if (isset($id)) {
      $start_record->id = $id; // used for update
    }
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
      $start_timestamp = strtotime("$datum $start");
      $s = new DateTime("$datum $start"); // for diff calculation
      $start_record->start = date('Y-m-d H:i:s',$start_timestamp); //construct valid datetime format
    }
    else {
      // no start provided
      $start_record->start = null;
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
      $landing_timestamp = strtotime("$datum $landing");
      $l = new DateTime("$datum $landing"); // for diff calculation
      if ($l < $s) {
        throw new BadRequestHttpException("Landing before start $landing");
      }
      $start_record->landing = date('Y-m-d H:i:s',$landing_timestamp); //construct valid datetime format
      //duur is calculated
      $diff = date_diff($l, $s, true);
      $start_record->duur = date("Y-m-d $diff->h:$diff->i:$diff->s");
    }
    else {
      // no landing provided
      $start_record->landing = null;
      $start_record->duur = null;
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
      $start_record->opmerking = substr(trim($opmerking), 0, 30);
    }
    return $start_record;
  } //processStart

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

    //get parameters
    $id = null; // is assigned with POST
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

    $start_record = $this->processStart(
      $id,
      $datum,
      $registratie,
      $gezagvoerder,
      $tweede,
      $soort,
      $startmethode,
      $start,
      $landing,
      $duur,
      $instructie,
      $opmerking);
    // write start record to database
    $id = $start_record->create();
    return new ModifiedResourceResponse($id, 200);
  } // post

  /**
   * Responds to PATCH requests.
   *
   * @param $id
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
  public function patch(): ModifiedResourceResponse {
    //get parameters
    $id = Drupal::request()->query->get('id');
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

    $start_record = $this->processStart($id, $datum, $registratie, $gezagvoerder, $tweede, $soort, $startmethode, $start, $landing, $duur, $instructie, $opmerking);
    // write start record to database
    $nrAffected = $start_record->update();
    return new ModifiedResourceResponse($nrAffected, 200);

  }  //patch

  /**
   * @param $id
   *   id from record to be deleted
   * @param $datum
   *   datum value from record to be deleted
   * @return \Drupal\rest\ModifiedResourceResponse
   */
  public function delete(): ModifiedResourceResponse {
    //get parameters
    $id = Drupal::request()->query->get('id');
    $datum = Drupal::request()->query->get('datum');
    // check validity of datum is record to be deleted - as a protection
    $record = new EzacStart($id);
    if ($record->id == null) {
      throw new NotFoundHttpException("Invalid ID: $id");
    }
    if ($datum != $record->datum) {
      throw new BadRequestHttpException("Invalid datum: $datum");
    }
    // delete record
    $nrAffected = $record->delete();

    return new ModifiedResourceResponse();
  } //delete

}