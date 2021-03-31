<?php

namespace Drupal\ezac_reserveringen\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ezac\Util\EzacMail;
use Drupal\ezac_leden\Model\EzacLid;
use Drupal\ezac_reserveringen\Model\EzacReservering;
use Drupal\ezac_reserveringen\Controller\EzacReserveringenController;
use Drupal\ezac_rooster\Model\EzacRooster;
use Drupal\ezac\Util\EzacUtil;


/**
 * UI to show status of VBA records
 */


class EzacReserveringenForm extends FormBase
{

    /**
     * @inheritdoc
     */
    public function getFormId()
    {
        return 'ezac_reserveringen_form';
    }

  /**
   * buildForm for reserveringen status
   *
   * @param array $form
   * @param FormStateInterface $form_state
   * @param string $type vliegen kader werken
   *
   * @return array
   */
    public function buildForm(array $form, FormStateInterface $form_state, $type = '') {
      $messenger = Drupal::messenger();

      // read settings
      $settings = Drupal::config('ezac_reserveringen.settings');
      $mededeling = $settings->get('reservering.mededeling');
      $periodes = $settings->get('reservering.periodes');
      $types = $settings->get('reservering.types');

      // per resource: naam | capaciteit | aantal
      if ($type != '' && in_array($type, $types)) {
        // read one resource type
        $resources = $settings->get("reservering.resources.$type");
      }
      else {
        // read all resource types
        $resources = [];
        foreach ($types as $t => $label) {
          $resources = array_merge($resources, $settings->get("reservering.resources.$t"));
        }
      }
      // Wrap the form in a div.
      $form = [
        '#prefix' => '<div id="statusform">',
        '#suffix' => '</div>',
      ];

      $form['mededeling'] = [
        '#type' => 'markup',
        '#markup' => t($mededeling),
        '#weight' => 0,
      ];

      unset($date_list);
      //zoek volgende vliegdag in dienstenrooster
      $today = date('Y-m-d');
      $lastday = date('Y-m-d', strtotime("$today +1 week")-43200); //one week period - 12 hours
      //$date->sub(new DateInterval('P12H'));

      $condition = [
        'datum' => [
          'value' => [$today, $lastday],
          'operator' => 'BETWEEN',
        ],
      ];
      $days = array_unique(EzacRooster::index($condition,'datum'));

      if (count($days) > 0) {
        $firstday = substr($days[0], 0, 10);
        foreach ($days as $day) {
          $date_list[substr($day, 0, 10)] = EzacUtil::showDate($day);
          $lastday = substr($day, 0, 10);
        }
      }
      else {
        //geen rooster dagen gevonden
        $messenger->addMessage('Geen instructie diensten in het rooster gevonden');
        //zoek volgende vliegdag in volgend weekend
        $datum = date('Y-m-d',strtotime('Saturday'));
        $firstday = $datum;
        $nextday = $datum;
        $date_list[$datum] = EzacUtil::showDate($datum);
        if (date('l',strtotime($datum)) == 'Saturday') {
          $sunday = strtotime('Sunday', strtotime($datum));
          $nextday = date('Y-m-d', $sunday);
          $lastday = $nextday;
          $date_list[$nextday] = EzacUtil::showDate($nextday);
        }
      }

      // bepaal soorten resources
      $soort_list = [];
      foreach ($resources as $resource => $res) {
        // alle resources zijn nu in alle periodes te boeken
        $soort_list[$resource] = $res['naam'];
      }

      // maak tabel met per dag | periode | resource gegevens
      $rsc_tabel = [];
      foreach ($date_list as $day => $show_day) {
        foreach ($periodes as $periode => $omschrijving) {
          foreach ($resources as $resource => $res) {
            $rsc_tabel[$day][$periode][$resource] =
              [
                'capaciteit' => $res['capaciteit'],
                'aantal' => $res['aantal'],
                'vrij' => $res['capaciteit'],
                'gereserveerd' => 0,
              ];
          }
        }
      }

      //lees reserveringen uit ezac_reservering voor $datum
      $condition = [
        'datum' => [
          'value' => [$firstday, $lastday],
          'operator' => 'BETWEEN',
        ],
      ];
      $reserveringenIndex = EzacReservering::index($condition);
      $res_tabel = [];
      // maak tabel met per dag | periode | reservering gegevens
      foreach ($reserveringenIndex as $id) {
        $res = new EzacReservering($id);
        $res_tabel[$res->datum][$res->periode][$res->id] = [
          'soort' => $res->soort,
          'leden_id' => $res->leden_id,
          'doel' => $res->doel,
          'reserve' =>$res->reserve
        ];
      }

      //toon reserveringen met routine uit EzacReserveringenController
      $res_display = EzacReserveringenController::reserveringen("$firstday:$lastday");
      $form = array_merge($res_display, $form);

      // invoeren nieuwe reservering
      $form['datum'] = array(
        '#title' => t('Datum'),
        '#type' => 'select',
        '#options' => $date_list,
        '#description' => 'Selecteer de datum voor je reservering',
        '#weight' => 5,
      );

      $form['periode'] = array(
        '#type' => 'select',
        '#title' => t('Periode'),
        '#options' => $periodes, //$periode_list,
        '#description' => t('Kies een periode'),
        '#weight' => 6,
      );

      $form['soort'] = array(
        '#type' => 'select',
        '#title' => t('Soort reservering'),
        '#options' => $soort_list,
        '#description' => t('Kies een soort reservering'),
        '#weight' => 7,
      );

      $form['doel'] = array(
        '#title' => t('Doel van de reservering'),
        '#type' => 'textfield',
        '#description' => t('Welk doel heeft de reservering?'),
        '#maxlength' => 20,
        '#required' => TRUE,
        '#size' => 20,
        '#weight' => 8,
      );

      $form['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Plaats reservering'),
        '#weight' => 9,
        '#prefix' => '<div class="reserveer-submit-div">',
        '#suffix' => '</div>',
      );

      // verwerk reserveringen in beschikbare capaciteit
      if (isset($res_tabel)) {
        foreach ($res_tabel as $datum => $periode_reserveringen) {
          foreach ($periode_reserveringen as $periode => $reserveringen) {
            foreach ($reserveringen as $id => $reservering) { //  soort | naam | doel | reserve
              $soort = $reservering['soort'];
              if (isset($rsc_tabel[$datum][$periode][$soort])) {
                // de reservering is voor een bestaande resource soort
                // muteer aantal per boeking in aantal gereserveerd en aantal vrij
                $rsc_tabel[$datum][$periode][$soort]['gereserveerd'] += $resources[$soort]['aantal'];
                $rsc_tabel[$datum][$periode][$soort]['vrij'] -= $resources[$soort]['aantal'];
              }
            }
          }
        }
      }

      $form[10]['#type'] = 'markup';
      $form[10]['#markup'] = "<h3>Nog beschikbare capaciteit</h3>";
      $form[10]['#weight'] = 10;
      $form[10]['#prefix'] = '<div class="ezacreserveer-intro-div">';
      $form[10]['#suffix'] = '</div>';

      //  table header
      // Table tag attributes
      $attributes = array(
        'border'      => 1,
        'cellspacing' => 0,
        'cellpadding' => 5,
        'width'	  => '90%');

      //Set up the table Headings
      $header = array(
        array('data' => t('Datum')),
        array('data' => t('Periode')),
        array('data' => t('Soort')),
        array('data' => t('Capaciteit')),
        array('data' => t('Aantal per boeking')),
        array('data' => t('Aantal gereserveerd')),
        array('data' => t('Aantal beschikbaar')),
      );

      foreach ($rsc_tabel as $datum => $rsc_datum) {
        foreach ($rsc_datum as $periode => $rsc_periode) {
          foreach ($rsc_periode as $resource => $rsc) {
            // table rows
            $row[] = array(
              EzacUtil::showDate($datum),
              $periode,
              $resource,
              $rsc['capaciteit'],
              $rsc['aantal'],
              $rsc['gereserveerd'],
              $rsc['vrij'],
            );
          }
        }
      }

      // store rsc_tabel for validate function
      $form['rsc_tabel'] = [
        '#type' => 'value',
        '#value' => $rsc_tabel,
      ];

      $form[4]['#theme'] = 'table';
      $form[4]['#attributes'] = $attributes;
      $form[4]['#header'] = $header;
      $form[4]['#rows'] = (isset($row)) ? $row : null;
      $form[4]['#empty'] = t('Geen gegevens beschikbaar');
      $form[4]['#weight'] = 11;

      // Bepaal leden_id ingelogde gebruiker
      $condition = ['user' => Drupal::currentUser()->getAccountName()];
      $ids = EzacLid::index($condition,'id');

      $form['leden_id'] = array(
        '#type' => 'hidden',
        '#value' => $ids[0] ?? null,
      );

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
      // check if user exists
      if ($form_state->getValue('leden_id') ==  null) {
        $user = Drupal::currentUser()->getAccountName();
        $form_state->setErrorByName('datum', "Geen reservering mogelijk voor $user");
      }

      // check if capacity is available
      $rsc_tabel = $form_state->getValue('rsc_tabel');
      $datum = $form_state->getValue('datum');
      $periode = $form_state->getValue('periode');
      $soort = $form_state->getValue('soort');
      if ($rsc_tabel[$datum][$periode][$soort]['vrij'] < 1) {
        $form_state->setErrorByName('soort', "Geen capaciteit meer beschikbaar voor $soort in $periode periode");
      }
    }

    /**
     * {@inheritdoc}
     * @throws \Exception
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
      $messenger = Drupal::messenger();

      // start D7 code
      // vastleggen reservering
      $datum = $form_state->getValue('datum');
      $periode = $form_state->getValue('periode');
      $soort = $form_state->getValue('soort');
      $leden_id = $form_state->getValue('leden_id');
      $doel = $form_state->getValue('doel');
      //$reserve = $form_state->getValue('reserve');

      $user = Drupal::currentUser()->getAccountName();
      // check if user exists
      if ($leden_id ==  null) {
        $form_state->setErrorByName('datum', "Geen reservering mogelijk voor $user");
      }
      //zoek mail adres voor user
      $lid = new EzacLid($leden_id);
      $email = $lid->e_mail;
      $naam = sprintf('%s %s %s', $lid->voornaam, $lid->voorvoeg, $lid->achternaam);

      // write to database
      $reservering = new EzacReservering();
      $reservering->datum = $datum;
      $reservering->periode = $periode;
      $reservering->soort = $soort;
      $reservering->leden_id = $leden_id;
      $reservering->doel = $doel;
      $reservering->aangemaakt = date('Y-m-d h:m:s');
      $reservering->reserve = 0; // wachtlijst is niet in gebruik
      $id = $reservering->create();
      if ($id) {
        $show_datum = EzacUtil::showDate($datum);
        $messenger->addMessage("$soort gereserveerd voor periode $periode op $show_datum [$id]",'status');
      }
      else {
        $messenger->addMessage("Reservering niet mogelijk",'error');
        return;
      }
      // versturen bevestiging met link en sleutel voor wijziging / annulering
      //   aanmaken sleutel met hash functie
      $hash_fields = array(
        'id' => $id,
        'datum' => $datum,
        'periode' => $periode,
        'soort' => $soort,
        'leden_id' => $leden_id,
      );
      $data = implode('/', $hash_fields);
      //$hash = drupal_hash_base64($data);
      // hash changed to SHA256
      $hash = hash('sha256', $data, FALSE);

      // passagiers/edit/id/datum/tijd/naam/telefoon/hash

      // link naar annulering
      $urlAnnuleringString = Url::fromRoute(
        'ezac_reserveringen_annulering',  // show rooster for datum
        [
          'id' => $id,
          'hash' => $hash,
        ]
      )->toString();

      $show_datum = EzacUtil::showDate($datum);
      $subject = "Reservering $soort EZAC op $show_datum in de $periode periode";

      /* additional header pieces for errors, From cc's, bcc's, etc */
      $headers = [
        //'From' => "webmaster@ezac.nl",
        'Bcc' => "webmaster@ezac.nl",
        //'X-Mailer' => "PHP",
        'Content-Type' => "text/html; charset=iso-8859-1",
        //'Return-Path' => "webmaster@ezac.nl",
      ];

      $body  = '<html lang="nl"><body>';
      $body .= "<p>Er is voor $naam een reservering voor $soort bij de EZAC aangemaakt";
      $body .= "<br>";
      $body .= "<br>De reservering is voor $show_datum in de $periode periode";
      $body .= "<br>";
      $body .= "<br>Mocht het niet mogelijk zijn hiervan gebruik te maken, dan kan deze reservering";
      $body .= "<br>via <a href=https://www.ezac.nl$urlAnnuleringString>DEZE LINK</a> worden geannuleerd ";
      $body .= "<br>";
      $body .= "<br>Met vriendelijke groet,";
      $body .= "<br>Eerste Zeeuws Vlaamse Aero Club";
      $body .= "</body></html>";
      $mailed = mail($email,$subject, $body, $headers);

      if ($mailed) {
        $messenger->addMessage("Bevestiging verstuurd aan $email",'status');
      }
      else $messenger->addMessage("Bevestiging aan $email kon niet worden verstuurd", 'error');

      /*
      //_ezacreserveer_mail($email, $subject, $body, $headers);
      $body_plain = "Er is voor $naam een reservering voor $soort $doel bij de EZAC aangemaakt\r\n"
        ."\r\nDe reservering is voor $show_datum in de $periode periode.\r\n"
        ."\r\nMocht het noet mogelijk zijn hiervan gebruik te maken, dan kan deze reservering \r\n"
        ."via https://www.ezac.nl$urlAnnuleringString worden geannuleerd. \r\n"
        ."\r\n -- EZAC reservering systeem";
      mail($email, $subject, $body_plain, $headers);
      */

      // toon bevestiging
      //$form_state['redirect'] = "reservering";

    } //submitForm
}
