<?php


namespace Drupal\ezac\Util;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ezac_kisten\Model\EzacKist;
use Drupal\ezac_leden\Model\EzacLid;

class EzacUtil {

  /**
   * @file
   * adds a field to a form
   *
   * @param array $form
   * @param string $label
   * @param string $type
   * @param string $title
   * @param string $description
   * @param $default_value
   * @param integer $maxlength
   * @param integer $size
   * @param boolean $required
   * @param integer $weight
   * @param array $options
   * @param array|null $ajax
   *
   * @return array
   */
  public static function addField(array $form,
                                  string $label,
                                  string $type,
                                  string $title,
                                  string $description,
                                  $default_value,
                                  int $maxlength,
                                  int $size,
                                  bool $required,
                                  int $weight,
                                  array $options = NULL,
                                  array $ajax = NULL) {
    if (isset($type)) {
      $form[$label]['#type'] = $type;
    }
    if (isset($title)) {
      $form[$label]['#title'] = $title;
    }
    if (isset($description)) {
      $form[$label]['#description'] = $description;
    }
    if (isset($default_value)) {
      $form[$label]['#default_value'] = $default_value;
    }
    if (isset($maxlength)) {
      $form[$label]['#maxlength'] = $maxlength;
    }
    if (isset($size)) {
      $form[$label]['#size'] = $size;
    }
    if (isset($required)) {
      $form[$label]['#required'] = $required;
    }
    if (isset($weight)) {
      $form[$label]['#weight'] = $weight;
    }
    if (isset($options)) {
      $form[$label]['#options'] = $options;
    }
    if (isset($ajax)) {
      $form[$label]['#ajax'] = $ajax;
    }
    return $form;
  }

  /**
   * @file
   * return table with leden names
   *
   * @param array $condition
   *
   * @return array
   */
  public static function getLeden(array $condition = []) {
    if ($condition == []) {
      $condition = [
        //'actief' => TRUE,
        //'code' => ['VL'],
      ];
    }
    $sortkey = [
      ['#key' => 'voornaam'],
      ['#key' => 'achternaam'],
    ];
    $ledenIndex = EzacLid::index($condition, 'id', $sortkey);
    $leden = [];
    $leden[''] = "Onbekend";
    foreach ($ledenIndex as $id) {
      $lid = new EzacLid($id);
      if ($lid->afkorting != '')
        $leden[$lid->afkorting] = "$lid->voornaam $lid->voorvoeg $lid->achternaam";
    }
    return $leden;
  }

  /**
   * @file
   * return table with kisten names
   *
   * @param array $condition
   *
   * @return array
   */
  public static function getKisten(array $condition = []) {
    if ($condition == []) {
      $condition = [
        'actief' => TRUE,
      ];
    }
    $kistenIndex = EzacKist::index($condition, 'id', 'registratie');
    $kisten = [];
    $kisten[''] = "Onbekend";
    foreach ($kistenIndex as $id) {
      $kist = new EzacKist($id);
      $kisten[$kist->registratie] = "$kist->registratie $kist->callsign ($kist->inzittenden)";
    }
    return $kisten;
  }

  /**
   * @file return EZAC afkorting for drupal user or '' when not found
   * @return mixed|string
   */
  public static function getUser() {
    //get current user afkorting
    $condition = ['user' => Drupal::currentUser()->getAccountName()];
    $afkortingen = EzacLid::index($condition,'afkorting');
    if (count($afkortingen) == 1) {
      return $afkortingen[0];
    }
    else { // geen ledenrecord voor deze drupal user aanwezig
      return '';
    }
  }

  public static function showDate($datum) {
    /* Set locale to Dutch */
    setlocale(LC_TIME, 'nl_NL');
    // dd maand jaar
    return strftime('%e %B %Y', strtotime($datum));
  }

  /**
   * @param $datum
   * @param &$datumStart
   * @param &$datumEnd
   *
   * @return string errmsg
   */
  public static function checkDatum($datum, &$datumStart, &$datumEnd): string {
    $errmsg = '';

    //if $datum is a range, split and process
    // range is indicated by date:date format
    if (strpos($datum, ':')) {
      $datum_range = explode(':', $datum);
      // eerste datum is $datum_range[0]
      // tweede datum is [1]
      // take datumStart from first date
      $errmsg = self::checkDatum($datum_range[0], $datumStart, $de);
      if ($errmsg != '') {
        // invalid date
        return $errmsg;
      }
      //take datumEnd from second date
      $errmsg = self::checkDatum($datum_range[1], $ds, $datumEnd);
      if ($errmsg != '') {
        // invalid date
        return $errmsg;
      }
      return $errmsg; // finished processing date range
    }

    // orginal code
    $datum_delen = explode('-', $datum);
    switch (strlen($datum)) {
      case 4: //YYYY
        if (!checkdate(01, 01, $datum_delen[0])) {
          $errmsg = 'Invalid value parameter datum YYYY [' . $datum . ']';
        }
        $datumStart = $datum . '-01-01';
        $datumEnd = $datum . '-12-31';
        break;
      case 7: //YYYY-MM
        if (!checkdate($datum_delen[1], 01, $datum_delen[0])) {
          $errmsg = 'Invalid value parameter datum YYYY-MM [' . $datum . ']';
        }
        $datumStart = $datum . '-01';
        if (checkdate($datum_delen[1], 31, $datum_delen[0])) {
          $datumEnd = $datum . '-31';
        }
        elseif (checkdate($datum_delen[1], 30, $datum_delen[0])) {
          $datumEnd = $datum . '-30';
        }
        elseif (checkdate($datum_delen[1], 29, $datum_delen[0])) {
          $datumEnd = $datum . '-29';
        }
        elseif (checkdate($datum_delen[1], 28, $datum_delen[0])) {
          $datumEnd = $datum . '-28';
        }
        break;
      case 10: //YYYY-MM-DD
        if (!checkdate($datum_delen[1], $datum_delen[2], $datum_delen[0])) { //mm dd yyyy
          $errmsg = 'Invalid value parameter datum YYYY-MM-DD [' . $datum . ']';
        }
        $datumStart = $datum; // .' 00:00:00');
        $datumEnd = $datum; // .' 23:59:59');
        break;
      default: //invalid
        $errmsg = 'Invalid length parameter datum [' . $datum . ']';
    }
    return $errmsg;
  }

}