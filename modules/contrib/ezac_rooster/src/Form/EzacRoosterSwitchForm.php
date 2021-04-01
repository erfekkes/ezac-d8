<?php


namespace Drupal\ezac_rooster\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ezac\Util\EzacUtil;
use Drupal\ezac_leden\Model\EzacLid;
use Drupal\ezac_rooster\Model\EzacRooster;

class EzacRoosterSwitchForm extends FormBase {

  /**
   * @inheritdoc
   */
  public function getFormId()
  {
    return 'ezac_rooster_switch_form';
  }

  /**
   * Create rooster entries
   *
   * @param array $form
   * @param FormStateInterface $form_state
   * @param null $id
   *
   * @return array
   */
  function buildForm(array $form, FormStateInterface $form_state, int $id = null) {
    // @todo bouw om voor ruilen van dienst
    // dienst id moet worden geruild
    // zoek vervangende dienst van niet zelf
    // alleen diensten binnen kader of instructie groep ruilen
    // in settings definitie voor kader en instructie diensten (dienst in kaderDiensten | instructieDiensten)

    // initialize messenger
    $messenger = Drupal::messenger();

    // read settings
    $settings = Drupal::config('ezac_rooster.settings');
    //set up diensten
    $diensten = $settings->get('rooster.diensten');
    //ezac_rooster_diensten Id Dienst Omschrijving
    $diensten['-'] = '-'; //placeholder voor lege dienst
    $form['diensten'] = array(
      '#type' => 'value',
      '#value' => $diensten,
    );
    $instructieDiensten = $settings->get('rooster.instructie');
    $kaderDiensten = $settings->get('rooster.kader');

    //set up periode
    $periodes = $settings->get('rooster.periodes');
    //store header info for periodes reference in submit function
    $form['periodes'] = array(
      '#type' => 'value',
      '#value' => $periodes,
    );

    // selecteer vliegende leden
    $condition = [ // selecteer alle leden
      //'code' => 'VL',
      //'actief' => TRUE,
    ];
    $leden = EzacUtil::getLeden($condition);
    unset($leden['']); // remove 'Onbekend' lid
    // store leden_lijst in form for use in validate and submit function
    $form['leden'] = array(
      '#type' => 'value',
      '#value' => $leden,
    );

    //get current user details
    $user = $this->currentUser();
    // read own leden record
    $user_name = $user->getAccountName();
    $condition = [
      'user' => $user_name,
    ];
    $lidId = EzacLid::index($condition);
    if (count($lidId) == 1) {
      $lid = new EzacLid($lidId);
    }
    else {
      $messenger->addMessage("geen leden record gevonden voor gebruiker $user_name");
    }

    // read dienst to be switched in rooster1
    $rooster1 = new EzacRooster($id);
    if (!isset($rooster1)) { // niet gevonden
      $messenger->addError("dienst $id is niet gevonden");
      return [];
    }

    // bewaar te ruilen rooster id in form
    $form['ruilen_van'] = [
      '#type' => 'value',
      '#value' => $id,
    ];
    // placeholder voor te ruilen met id in form - gevuld in validatie
    $form['ruilen_met'] = [
      '#type' => 'value',
      '#value' => null,
    ];

    // prepare dienstSoort for rooster select
    if (in_array($rooster1->dienst, $instructieDiensten))
      $dienstSoort = $instructieDiensten;
    else $dienstSoort = $kaderDiensten;

    // get year from rooster1
    $year = substr($rooster1->datum, 0, 4);

    // read rooster for datum or datum range and dienstSoort
    EzacUtil::checkDatum($year, $datumStart, $datumEnd);
    $condition = [
      'datum' => [
        'value' => [$datumStart, $datumEnd],
        'operator' => 'BETWEEN',
      ],
      'dienst' => [
        'value' => $dienstSoort, // check if dienst in dienstSoort
        'operator' => 'IN',
      ],
    ];

    // read index of rooster datum
    $roosterData = array_unique(EzacRooster::index($condition, 'datum'));
    if (!isset($roosterData)) {
      $messenger->addError("Geen diensten gevonden om mee te ruilen");
      return NULL; // no entries for datum
    }

    $naam = $leden[$rooster1->naam];
    $d = $diensten[$rooster1->dienst];
    $dat = EzacUtil::showDate($rooster1->datum);
    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => "<H2>Ruil $naam's $d dienst op $dat in $rooster1->periode periode</H2>",
    ];
    $form['intro2'] = [
      '#type' => 'markup',
      '#markup' => "Selecteer hieronder de te ruilen dienst",
    ];

    //toon tabel met datum en diensten per periode
    //prepare header
    $header = array(t('Datum'));
    // voeg een kolom per periode toe
    foreach ($periodes as $periode => $omschrijving) {
      array_push($header, t($omschrijving));
    }

    $caption = t("Rooster voor " .EzacUtil::showDate($year));
    //show table with dienst entry field for each periode record per naam

    //vul tabel alleen voor te ruilen diensten
    $form['table'] = array(
      // Theme this part of the form as a table.
      '#type' => 'table',
      '#header' => $header,
      '#caption' => $caption,
      '#sticky' => TRUE,
      '#weight' => 5,
      '#prefix' => '<div id="table-div">',
      '#suffix' => '</div>',
    );

    // build table rows
    foreach ($roosterData as $rooster_dag) {

      // prepare table row
      $form['table'][$rooster_dag] = [];
      $form['table'][$rooster_dag]['datum'] = [
        '#type' => 'markup',
        '#markup' => EzacUtil::showDate($rooster_dag),
      ];

      // initialize periodes
      $dienstPeriodes = [];
      foreach ($periodes as $periode => $omschrijving) {
        $dienstPeriodes[$periode] = [];
      }

      // lees alle te ruilen diensten voor rooster_dag
      $condition = [
        'datum' => $rooster_dag,
        'dienst' => [ // toon alleen ruilbare diensten
          'value' => $dienstSoort,
          'operator' => 'IN',
        ],
        'naam' => [ // toon niet de eigen diensten
          'value' => $rooster1->naam,
          'operator' => '!=',
        ]
      ];
      $roosterIndex = EzacRooster::index($condition);
      foreach ($roosterIndex as $roosterId) {
        // add dienst to table for datum
        $rooster = new EzacRooster($roosterId);
        //opmaken dienst beschrijving voor tabel
        $t = $diensten[$rooster->dienst] .':' .$leden[$rooster->naam] .'<br>';
        // zet beschrijving in tabel met als index dienst id
        $dienstPeriodes[$rooster->periode][$rooster->id] = $t;
      }

      // fill columns for diensten
      foreach ($periodes as $periode => $omschrijving) {
        if ($dienstPeriodes[$periode] != []) {
          // er zijn te ruilen diensten in deze periode
          $options = [];
          foreach ($dienstPeriodes[$periode] as $roosterId => $t) {
            // plaats dienst beschrijving als radios item
            $options[$roosterId] = $t;
          }
          //dpm($options, 'options'); //debug
          $form['table'][$rooster_dag][$periode] = [
            '#type' => 'checkboxes',
            '#options' => $options,
          ];
        }
        else {
          $form['table'][$rooster_dag][$periode] = [
            '#type' => 'markup',
            '#markup' => t(''),
          ];
        }
      }

    }
    // @todo implement submit button
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Ruilen'),
      '#weight' => 31
    ];

    return $form;
  }

  function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    // check op meer dan 1 geselecteerde checkbox
    $table = $form_state->getValue('table');
    $periodes = $form_state->getValue('periodes');
    $checked = [];
    foreach ($table as $datum => $diensten) {
      foreach ($periodes as $periode => $omschrijving) {
        if (key_exists($periode, $table[$datum])) { // diensten in deze periode aanwezig?
          foreach ($table[$datum][$periode] as $dienst => $check) {
            if ($check != 0) {
              $checked[$check] = $check;
              $last_check = $check;
            }
          }
        }
      }
    }
    switch (count($checked)) {
      case 0:
        // geen dienst geselecteerd
        $form_state->setError($table, 'Geen dienst geselecteerd');
        break;
      case 1:
        // 1 dienst geselecteerd: Ok
        $form_state->setValue('ruilen_met', $last_check);
        break;
      default:
        // meer dan 1 dienst geselecteerd
        $form_state->setError($table, "Meer dan 1 dienst geselecteerd");
    }
  }

  function submitForm(array &$form, FormStateInterface $form_state) {
    // @TODO: Implement submitForm() method.
    // switch diensten
    $messenger = Drupal::messenger();
    $ruilen_van = $form_state->getValue('ruilen_van');
    $ruilen_met = $form_state->getValue('ruilen_met');

    $diensten = $form_state->getValue('diensten');
    $leden = $form_state->getValue('leden');

    // ruil diensten
    $rooster1 = new EzacRooster($ruilen_van);
    $naam1 = $rooster1->naam;
    $lid1 = new EzacLid(EzacLid::getId($naam1));
    $datum1 = EzacUtil::showDate($rooster1->datum);
    $dienst1 = $diensten[$rooster1->dienst];

    $rooster2 = new EzacRooster($ruilen_met);
    $naam2 = $rooster2->naam;
    $lid2 = new EzacLid(EzacLid::getId($naam2));
    $datum2 = EzacUtil::showDate($rooster2->datum);
    $dienst2 = $diensten[$rooster2->dienst];

    // verwissel namen en update diensten
    $rooster1->naam = $naam2;
    $rooster1->mutatie = date('Y-m-d h:m:s');
    $rooster1->geruild = $naam1;
    $nr_updated = $rooster1->update();
    if ($nr_updated != 1) {
      // update 1 mislukt
      $messenger->addError("Ruilen van dienst $ruilen_van is niet gelukt");
      return;
    }
    $rooster2->naam = $naam1;
    $rooster2->mutatie = date('Y-m-d h:m:s');
    $rooster2->geruild = $naam2;
    $nr_updated = $rooster2->update();
    if ($nr_updated != 1) {
      // update 2 mislukt
      $messenger->addError("Ruilen met dienst $ruilen_met is niet gelukt");
      // draai wijziging in rooster1 terug
      $rooster1->naam = $naam1;
      $rooster1->geruild = '';
      $nr_updated = $rooster1->update();
    }

    $message = "De $dienst1 dienst van $leden[$naam1] in de $rooster1->periode periode op $datum1 ";
    $message .= "is geruild met ";
    $message .= "de $dienst2 dienst van $leden[$naam2] in de $rooster2->periode periode op $datum2 ";
    $messenger->addMessage($message);

    // @todo mail bericht over ruil aan iedereen die op die dag een dienst heeft
    //Verstuur mail berichten voor EZAC roosterwijzigingen

    /* recipients */
    $recipient = $lid1->e_mail ."; " .$lid2->e_mail;

    /* subject */
    $subject = "Wijziging EZAC Dienstrooster op " . $datum1;
    if ($datum1 != $datum2) {
      $subject .= " en " . $datum2;
    }

    /* message */
    $message  = "";
    $message .= "<H1>Geruild:</H1>\n";
    $message .= $leden[$naam1] . " heeft de ";
    $message .= $dienst1     . " dienst in de ";
    $message .= $rooster1->periode    . " periode van ";
    $message .= $datum1 . " geruild met ";
    $message .= $leden[$naam2]  . "'s ";
    $message .= $dienst2     . " dienst in de ";
    $message .= $rooster2->periode    . "  periode ";
    if ($datum1 != $datum2) {
      $message .= "van " . $datum2 . "<p>\n";
    }

    $message .= "<H1>Overzicht van de diensten op " . $datum1;
    if ($datum1 <> $datum2) {
      $message .= " en " . $datum2;
    }
    $message .= "</H1>\n";
    $message .= "<TABLE border=1>\n";
    $message .= "<THEAD><b>";
    $message .= "<TR><TD>Datum</TD>\t";
    $message .=     "<TD>Periode</TD>\t";
    $message .=     "<TD>Dienst</TD>\t";
    $message .=     "<TD>Naam</TD>\t";
    $message .= "</TR></b></THEAD>\n";

    $condition = [
      'datum' => [
        'value' => [$datum1, $datum2],
        'operator' => 'BETWEEN',
      ],
    ];
    $roosterIndex = EzacRooster::index($condition, 'id', 'datum');
    /*
    foreach ($roosterIndex as $roosterId) {
      $r = new EzacRooster($roosterId);
      $rooster[$r->datum] = $r;
    }
    */
    foreach ($roosterIndex as $id) {
      $r = new EzacRooster($id);
      $dat = EzacUtil::showDate(substr($r->datum,0, 10));
      $message .= "<TR><TD>$dat</TD>\t";
      $message .= "<TD>$r->periode</TD>\t";
      $message .= "<TD>" .$diensten[$r->dienst]."</TD>\t";
      $message .= "<TD>" . $leden[$r->naam] . "</TD>\t";
      $message .= "</TR>\n";
    }
    $message .= "</TABLE>\n";

    /* you can add a stock signature */
    $message .= "<p>--<br>\r\n"; //Signature delimiter
    $message .= "<i>EZAC dienstrooster systeem</i>";

    /* additional header pieces for errors, From cc's, bcc's, etc */
    $headers = [
      //'From' => "webmaster@ezac.nl",
      'Bcc' => "webmaster@ezac.nl",
      //'X-Mailer' => "PHP",
      'Content-Type' => "text/html; charset=iso-8859-1",
      //'Return-Path' => "webmaster@ezac.nl",
      ];

    /* and now mail it */
    //mail alleen als er ook recipients zijn...
    if (isset($recipient)) {
      $mailed = mail($recipient, $subject, $message, $headers);
      //   Mailen van bevestiging
      if (!$mailed) $messenger->addMessage("Bevestiging kon niet worden verstuurd aan $recipient",'error');
    }

    // redirect naar rooster overzicht
    //go back to rooster overzicht
    $redirect = Url::fromRoute(
      'ezac_rooster_overzicht',
      ['datum' => substr($rooster1->datum, 0, 4)]
    );
    $form_state->setRedirectUrl($redirect);

  }

}