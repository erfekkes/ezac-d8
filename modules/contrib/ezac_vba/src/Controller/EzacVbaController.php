<?php

namespace Drupal\ezac_vba\Controller;

use Drupal;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\ezac\Util\EzacUtil;
use Drupal\ezac_vba\Model\EzacVbaBevoegdheid;
use Drupal\ezac_vba\Model\EzacVbaDagverslag;
use Drupal\ezac_vba\Model\EzacVbaDagverslagLid;

/**
 * Controller for EZAC start administration.
 */
class EzacVbaController extends ControllerBase {

  /**
   * toon dagverslagen
   *
   * @param string $datum_start
   * @param string $datum_eind
   *
   * @return array
   *  renderable array
   */
  public function dagverslagen($datum_start, $datum_eind) {
    $content = [];

    $rows = [];
    $headers = [
      t('Datum'),
      t('Verslag'),
    ];

    // build dagverslagen table - rows

    $condition = [];
    $namen = EzacUtil::getLeden($condition);

    //lees dagverslag index
    $condition = [
      'datum' => [
        'value' => [$datum_start, $datum_eind],
        'operator' => 'BETWEEN',
      ],
    ];
    $dagverslagIndex = EzacVbaDagverslag::index($condition);

    //lees dagverslagLid index
    $dagverslagLidIndex = EzacVbaDagverslagLid::index($condition);

    // lees bevoegdheidLid index
    $condition = [
      'datum_aan' => [
        'value' => [$datum_start, $datum_eind],
        'operator' => 'BETWEEN',
      ],
    ];
    $bevoegdheidLidIndex = EzacVbaBevoegdheid::index($condition);

    $header = [
      ['data' => 'datum', 'width' => '20%'],
      ['data' => 'verslag'],
    ];
    $rows = [];

    // check permission for update
    //@todo only amend own dagverslag
    $permission_update_all = Drupal::currentUser()
      ->hasPermission('EZAC_update_all');

    // initialize overzicht
    $overzicht = [];

    foreach ($dagverslagIndex as $id) {
      $dagverslag = new EzacVbaDagverslag($id);
      $p_weer = nl2br($dagverslag->weer);
      $p_verslag = nl2br($dagverslag->verslag);
      $p_instructeur = $namen[$dagverslag->instructeur];
      $overzicht[$dagverslag->datum]['dagverslag'][$dagverslag->id] =
        t("Instructeur: $p_instructeur<br>Weer: $p_weer<br>Verslag: $p_verslag");
    }

    //verwerk dagverslagen_lid

    foreach ($dagverslagLidIndex as $id) {
      $dl = new EzacVbaDagverslagLid($id);
      $p_naam = $namen[$dl->afkorting];
      $p_instr = $namen[$dl->instructeur];
      $p_verslag = nl2br($dl->verslag);
      $overzicht[$dl->datum]['dagverslag_lid'][$dl->id] =
        t("Opmerking voor $p_naam:<br>$p_verslag ($p_instr)</p>");
    }

    //verwerk bevoegdheid_lid
    foreach ($bevoegdheidLidIndex as $id) {
      $bl = new EzacVbaBevoegdheid($id);
      $p_naam = $namen[$bl->afkorting];
      $p_instr = $namen[$bl->instructeur];
      $p_onderdeel = nl2br($bl->onderdeel);
      $p_opmerking = nl2br($bl->opmerking);
      $overzicht[$bl->datum_aan]['bevoegdheid_lid'][$bl->id] =
        t("Bevoegdheid voor $p_naam: <br>$bl->bevoegdheid $p_onderdeel $p_opmerking($p_instr)</p>");
    }

    //display verslagen
    if (isset($overzicht)) {
      krsort($overzicht); //sort overzicht on datum key (descending)
      foreach ($overzicht as $datum => $ovz) {
        if (isset($ovz['dagverslag'])) {
          foreach ($ovz['dagverslag'] as $id => $verslag) {
            if ($permission_update_all) {
              // allow dagverslag edit
              $urlString = Url::fromRoute(
                'ezac_vba_dagverslag_table',  // edit dagverslag record
                ['id' => $id]
              )->toString();
              $d = t("<a href=$urlString>" . EzacUtil::showDate($datum) . "</a>");
            }
            else {
              $d = EzacUtil::showDate($datum);
            }
          }
          $rows[] = [
            $d,
            $verslag,
          ];
        }
      if (isset($ovz['dagverslag_lid'])) {
        foreach ($ovz['dagverslag_lid'] as $id => $verslag) {
          $rows[] = [
            ezacUtil::showDate($datum),
            $verslag,
          ];
        }
      }
      if (isset($ovz['bevoegdheid_lid'])) {
        foreach ($ovz['bevoegdheid_lid'] as $id => $verslag) {
          $rows[] = [
            ezacUtil::showDate($datum),
            $verslag,
          ];
        }
      }
      } // foreach
    }

    // define table for output
    $caption = "Overzicht EZAC VBA verslagen van $datum_start tot $datum_eind";
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
      '#weight' => 5,
    ];
    // Don't cache this page.
    $content['#cache']['max-age'] = 0;

    return $content;
  } // dagverslagen

  /**
   * Render a list of entries in the database.
   *
   * @param string $datum_start
   *  $jaar - categorie (optional)
   * @param $datum_eind
   *
   * @return array
   */
  public function dagverslagLid($datum_start, $datum_eind) {
    //@todo move to form
    $content = [];

    $rows = [];
    $headers = [
      t('start'),
    ];

    $leden = EzacUtil::getLeden();
    // $kisten = EzacUtil::getKisten();

    $caption = "Overzicht EZAC vba bestand";
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
      '#weight' => 5,
    ];
    // Don't cache this page.
    $content['#cache']['max-age'] = 0;

    return $content;
  } // dagverslagLid


  /**
   * Render a list of entries in the database.
   *
   * @param string
   *  $jaar - categorie (optional)
   *
   * @return array
   */
  public function bevoegdheidLid($datum_start, $datum_eind) {
    // read settings
    $settings = Drupal::config('ezac_vba.settings');

    //set up bevoegdheden
    $bevoegdheden = $settings->get('vba.bevoegdheden');

    //@todo move to form
    $content = [];

    $rows = [];
    $headers = [
      t('datum'),
      t('naam'),
      t('instructeur'),
      t('bevoegdheid'),
    ];

    $leden = EzacUtil::getLeden();

    $condition = [
      'datum_aan' => [
        'value' => [$datum_start, $datum_eind],
        'operator' => 'BETWEEN',
      ],
    ];
    $bevoegdhedenIndex = EzacVbaBevoegdheid::index($condition);

    $rows = [];
    foreach ($bevoegdhedenIndex as $id) {
      $bevoegdheid = new EzacVbaBevoegdheid($id);
      $bevoegdheid_naam = $bevoegdheden[$bevoegdheid->bevoegdheid]['naam'];
      $rows[] = [
        EzacUtil::showDate($bevoegdheid->datum_aan),
        $leden[$bevoegdheid->afkorting],
        $leden[$bevoegdheid->instructeur],
        "$bevoegdheid_naam - $bevoegdheid->onderdeel",
      ];
    }

    $ds = EzacUtil::showDate($datum_start);
    $de = EzacUtil::showDate($datum_eind);
    $caption = "Overzicht EZAC vba bestand - bevoegdheden $ds - $de";
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
      '#weight' => 5,
    ];
    // Don't cache this page.
    $content['#cache']['max-age'] = 0;

    return $content;
  } // bevoegdheidLid

} //class EzacVbaController
