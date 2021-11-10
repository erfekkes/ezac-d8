<?php

namespace Drupal\ezac_passagiers\Controller;

use Drupal;
use Drupal\ezac\Util\EzacMail;
use Drupal\ezac_passagiers\Model\EzacPassagier;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

use Drupal\ezac\Util\EzacUtil;
use Drupal\ezac_leden\Model\EzacLid;

/**
 * Controller for EZAC passagiers
 */
class EzacPassagiersController extends ControllerBase {

  /**
   * toon passagiers
   *
   * @param string $datum YYYY-MM-DD:YYYY-MM-DD
   *
   * @return array
   *  renderable array
   */
  public static function overzicht($datum = null): array {

    // read settings
    $settings = Drupal::config('ezac_passagiers.settings');
    $slots = $settings->get('slots');
    $dagen = $settings->get('dagen');

    // prepare output
    $content = [];

    $header = [
      t('Datum'),
      t('Tijd'),
      t('Naam'),
      t('Telefoon'),
      t('Mail'),
    ];

    if (!isset($datum)) $datum = date('Y-m-d') .':' .date('Y') .'-12-31'; //toon passagiers tot einde jaar
    $errmsg = EzacUtil::checkDatum($datum,
      $datum_start,
      $datum_eind);
    if ($errmsg != '') {
      // fout in datum
      $content[] = t($errmsg);
      return $content;
    }

    $condition = [
      'datum' => [
        'value' => [$datum_start, $datum_eind],
        'operator' => 'BETWEEN',
      ],
    ];
    $rows = [];
    $sort = [
      [
        '#key' => 'datum',
        '#dir' => 'ASC',
      ],
      [
        '#key' => 'tijd',
        '#dir' => 'ASC',
      ],
    ];
    $resIndex = EzacPassagier::index($condition); // @TODO add $sort
    foreach ($resIndex as $id) {
      $passagier = new EzacPassagier($id);
      // add link for delete for own or all passagiers

      //   aanmaken sleutel met hash functie
      /* niet nodig bij verwijderen door ezac lid
      $hash_fields = array(
        'id' => $id,
        'datum' => $passagier->datum,
        'tijd' => $passagier->tijd,
        'naam' => $passagier->naam,
        'mail' => $passagier->mail,
        'telefoon' => $passagier->telefoon,
      );
      $data = implode('/', $hash_fields);
      $hash = hash('sha256', $data, FALSE);
      */

      $urlAnnulering = Url::fromRoute(
        'ezac_passagiers_verwijdering_form',
        [
          'id' => $id,
          //'hash' => $hash,
        ]
      )->toString();

      $lid = new EzacLid($passagier->aanmaker);
      $show_date = EzacUtil::showDate($passagier->datum);
      $afkorting = EzacUtil::getUser();
      //annulering link alleen tonen als eigen passagier of gemachtigd
      if (($passagier->aanmaker == $afkorting)
        or (Drupal::currentUser()->hasPermission('EZAC_update_all'))) {
        // add link
        $datum_with_link = "<a href=$urlAnnulering>$show_date</a>";
      }
      else {
        // no link added
        $datum_with_link = $show_date;
      }
      $rows[] = [
        t($datum_with_link),
        $passagier->tijd,
        $passagier->naam,
        $passagier->telefoon,
        $passagier->mail,
      ];
    }

    // define table for output
    $caption = "Overzicht EZAC passagiers van $datum_start tot $datum_eind";
    $content['table'] = [
      '#type' => 'table',
      '#caption' => $caption,
      '#header' => $header,
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
  } // overzicht

  //@TODO add annulering function

  public static function verwijderen($id, $reden) {
    $messenger = Drupal::messenger();

    $reservering = new EzacPassagier($id);
    $result = $reservering->delete();
    if ($result == 1) {
      $messenger->addMessage("Reservering $id is verwijderd", 'status');
      // Stuur bericht van verwijdering naar passagier
      if (isset($reservering->mail)) {
        $naam = $reservering->naam;
        $datum = EzacUtil::showDate($reservering->datum);
        $tijd = substr($reservering->tijd, 0, 5);
        $url = Url::fromRoute('ezac_passagiers')->toString();
        $subject = "Je reservering voor meevliegen bij de EZAC is vervallen";
        $body = "<p>Beste $naam,";
        $body .= "<br>Je reservering voor meevliegen bij de EZAC op $datum $tijd is helaas vervallen";
        if (!empty($reden)) {
          $body .= "<BR>De reden hiervoor is $reden";
        }
        $body .= "<br>";
        $body .= "<br>Om een nieuwe reservering te maken kun je ";
        $body .= "<a href=$url>DEZE LINK</a> gebruiken";
        $body .= "<br>";
        $body .= "<br>Met vriendelijke groet,";
        $body .= "<br>Eerste Zeeuws Vlaamse Aero Club";
        //mail($reservering->mail, $subject, $body);
        EzacMail::mail('Ezac_passagiers', 'reservering', $reservering->mail, $subject, $body);
      }
    }
    else {
      $messenger->addMessage("Reservering $id is NIET verwijderd ($result)", 'error');
    }
  }

  /**
   * @param int $id
   * @param string $hash
   *
   * @return array
   */
  public function annulering(int $id, string $hash): array {
    $messenger = Drupal::messenger();
    // $hash ingevuld?
    if (!isset($hash) || empty($hash)) {
      $messenger->addMessage("Code voor passagier $id ontbreekt", 'error');
      return [];
    }
    // read passagier record
    $passagier = new EzacPassagier($id);
    if (!isset($passagier)) {
      $messenger->addMessage("passagier $id niet gevonden", 'error');
      return [];
    }

    $datum    = substr($passagier->datum, 0,10);
    $tijd = substr($passagier->tijd, 0, 5);
    $soort    = $passagier->soort;
    $afkorting = $passagier->aanmaker;

    $hash_fields = array(
      'id' => $id,
      'datum' => $datum,
      'tijd' => $tijd,
      'naam' => $passagier->naam,
      'mail' => $passagier->mail,
      'telefoon' => $passagier->telefoon,
    );
    $data = implode('/', $hash_fields);

    $calculated_hash = hash('sha256', $data, FALSE);
    if ($calculated_hash <> $hash) {
      $messenger->addMessage("Onjuiste code voor passagier $id", 'error');
      //$messenger->addMessage("calculated - $calculated_hash", 'error');
      //$messenger->addMessage("received   - $hash", 'error');
      return [];
    }

    // delete passagier
    $aantal = $passagier->delete();
    if ($aantal == 1) {
      $messenger->addMessage("passagier voor $soort op $datum verwijderd", 'status');
    }
    else {
      $messenger->addMessage("passagier niet verwijderd [$aantal]", 'error');
    }
    return [];
    //return drupal_get_form('ezacreserveer_delete_form', $passagier);

  }
} //class EzacPassagiersController
