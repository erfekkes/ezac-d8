<?php

namespace Drupal\ezac_passagiers\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ezac\Util\EzacUtil;
use Drupal\ezac\Util\EzacMail;
use Drupal\ezac_passagiers\Model\EzacPassagier;

/**
 * UI to show free slots
 */
class EzacPassagiersBoekingForm extends FormBase {

  /**
   * @inheritdoc
   */
  public function getFormId() {
    return 'ezac_passagiers_boeking_form';
  }

  /**
   * buildForm for passagiers boeking
   *
   * @param array $form
   * @param FormStateInterface $form_state
   * @param string $datum
   * @param string $tijd
   *
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $datum = null, string $tijd = null) {
    $messenger = Drupal::messenger();

    // read settings
    $settings = Drupal::config('ezac_passagiers.settings');
    $slots = $settings->get('slots');
    $texts = $settings->get('texts');
    $parameters = $settings->get('parameters');

    // Wrap the form in a div.
    $form = [
      '#prefix' => '<div id="boekingform">',
      '#suffix' => '</div>',
    ];

    if ($texts['mededeling'] != '') {
      $form['mededeling'] = [
        '#type' => 'markup',
        '#markup' => t($texts['mededeling']),
        '#weight' => 0,
      ];
    }
    $datum_delen = explode('-', $datum);
    $jaar  = $datum_delen[0];
    if (isset($datum_delen[1])) $maand = $datum_delen[1];
    if (isset($datum_delen[2])) $dag   = $datum_delen[2];

    $dat_string = EzacUtil::showDate($datum); // dag van de week

    $form['datum'] = array(
      '#type' => 'hidden',
      '#value' => $datum,
    );
    $form['tijd'] = array(
      '#type' => 'hidden',
      '#value' => $tijd,
    );

    // create intro
    $form[0]['#type'] = 'markup';
    $form[0]['#markup'] = '<p><h2>Reserveren meevliegen bij de EZAC</h2></p>';
    $form[0]['#markup'] .= "<p><h3>Datum: $dat_string om $tijd</h3></p>";
    $form[0]['#markup'] .= $texts['advies'];
    $form[0]['#weight'] = 0;
    $form[0]['#prefix'] = '<div class="reserveer-intro-div">';
    $form[0]['#suffix'] = '</div>';

    $form['naam'] = array(
      '#title' => t('Naam van de passagier'),
      '#type' => 'textfield',
      '#description' => t('De naam voor op de reserveringslijst'),
      '#maxlength' => 30,
      '#required' => TRUE,
      '#size' => 30,
      '#weight' => 1,
      '#prefix' => '<div class="reserveer-naam-div">',
      '#suffix' => '</div>',
    );
    $form['telefoon'] = array(
      '#title' => t('Telefoonnummer contactpersoon'),
      '#type' => 'textfield',
      '#description' => t('Het nummer waarop je het best bereikbaar bent voor eventuele wijzigingen'),
      '#maxlength' => 20,
      '#required' => TRUE,
      '#size' => 20,
      '#weight' => 2,
      '#prefix' => '<div class="reserveer-telefoon-div">',
      '#suffix' => '</div>',
    );
    $form['email'] = array(
      '#title' => t('E-mail'),
      '#type' => 'email',
      '#description' => t('E-mail adres voor de bevestiging'),
      '#maxlength' => 50,
      '#required' => TRUE,
      '#size' => 50,
      '#weight' => 3,
      '#prefix' => '<div class="reserveer-mail-div">',
      '#suffix' => '</div>',
    );
    $form['gevonden'] = array(
      '#title' => t('Hoe heb je ons gevonden?'),
      '#type' => 'textfield',
      '#description' => t('Geef svp aan hoe je de EZAC hebt gevonden'),
      '#maxlength' => 30,
      '#required' => FALSE,
      '#size' => 30,
      '#weight' => 4,
      '#prefix' => '<div class="reserveer-mail-div">',
      '#suffix' => '</div>',
    );
    $form['mail_list'] = array(
      '#title' => t('Wil je in de toekomst ook berichten van de EZAC ontvangen?'),
      '#type' => 'checkbox',
      '#default_value' => 0,
      '#weight' => 5,
      '#prefix' => '<div class="reserveer-mail-div">',
      '#suffix' => '</div>',
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Reserveer deze vlucht'),
      '#weight' => 10,
      '#prefix' => '<div class="reserveer-submit-div">',
      '#suffix' => '</div>',
    );
    
    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $messenger = Drupal::messenger();
    //validate naam
    //validate telefoon
    $telefoon = $form_state->getValue('telefoon');
    if (strlen($telefoon) > 0) {
      $telefoon = str_replace (' ', '', $telefoon);
      //$telefoon = str_replace ('-', '', $telefoon);
      $telefoon = str_replace ('(', '', $telefoon);
      $telefoon = str_replace (')', '', $telefoon);
      $telefoon = str_replace ('[', '', $telefoon);
      $telefoon = str_replace (']', '', $telefoon);
      $telefoon = str_replace ('{', '', $telefoon);
      $telefoon = str_replace ('}', '', $telefoon);
      $form_state->setValue('telefoon', $telefoon); // clean up number
    }
    //validate mail

    $email = $form_state->getValue('email');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName('email', t("$email is een ongeldig mail adres"));
    }
  }

  /**
   * {@inheritdoc}
   * @throws \Exception
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $messenger = Drupal::messenger();

    $settings = Drupal::config('ezac_passagiers.settings');
    $texts = $settings->get('texts');
    $parameters = $settings->get('parameters');

    // start D7 code
    // vastleggen reservering
    $naam = $form_state->getValue('naam');
    $telefoon = $form_state->getValue('telefoon');
    $email = $form_state->getValue('email');
    $datum = $form_state->getValue('datum');
    $tijd = $form_state->getValue('tijd');
    $gevonden = $form_state->getValue('gevonden');
    $mail_list = $form_state->getValue('mail_list');

    $afkorting = EzacUtil::getUser();
    if ($afkorting != '') {
      $status = $parameters['reservering_bevestigd']; 
      // indien door EZAC lid ingegeven is bevestiging niet nodig
    }
    else {
      $status = $parameters['reservering_optie']; 
      // indien door gast ingegeven is bevestiging wel nodig
    }

    $passagier = new EzacPassagier();
    $passagier->datum = $datum;
    $passagier->tijd = $tijd;
    $passagier->naam = $naam;
    $passagier->telefoon = $telefoon;
    $passagier->mail = $email;
    $passagier->aangemaakt = date('Y-m-d H:m:s');
    $passagier->aanmaker = ($afkorting != '') ? $afkorting : 'anoniem';
    $passagier->soort = 'passagier';
    $passagier->status = $status;
    $passagier->gevonden = $gevonden;
    $passagier->mail_list = $mail_list;
    
    $mail_keuze = ($mail_list == 1) ? t("WEL") : t("NIET");

    // aanmaken reservering
    $id = $passagier->create();

    // versturen bevestiging met link en sleutel voor wijziging / annulering
    //   aanmaken sleutel met hash functie
    $hash_fields = array(
      'id' => $id,
      'datum' => $datum,
      'tijd' => $tijd,
      'naam' => $naam,
      'mail' => $email,
      'telefoon' => $telefoon,
    );
    $data = implode('/', $hash_fields);
    //$hash = drupal_hash_base64($data);
    $hash = hash('sha256', $data, FALSE);

    $eindtijd = date('G:i', strtotime('+1H')); // 1 uur na nu

    $url_bevestiging = Url::fromRoute(
      'ezac_passagiers_bevestiging',
      [
        'id' => $id,
        'hash' => $hash,
      ],
    )->toString();

    $url_verwijderen = Url::fromRoute(
      'ezac_passagiers_annulering',
      [
        'id' => $id,
        'hash' => $hash,
      ],
    )->toString();

    $show_datum = EzacUtil::showDate($datum);
    // Maak boarding card met disclaimer tekst
    $subject = "Reservering meevliegen EZAC op $show_datum $tijd";
    unset($body);
    $body  = "<html lang='nl'><body>";
    $body .= "<p>Er is voor $naam een reservering voor meevliegen bij de EZAC aangemaakt";
    $body .= "<br>Deze reservering geldt voor 1 persoon";
    $body .= "<br>";
    $body .= "<br>De reservering is voor $show_datum om $tijd";
    $body .= "<br>Graag een kwartier van tevoren aanwezig zijn (Justaasweg 5 in Axel)";
    $body .= "<br>";
    if ($status == $parameters['reservering_optie']) {
      $body .= "<br>Deze reservering dient <strong>voor $eindtijd</strong> te worden bevestigd, anders vervalt deze.";
      $body .= "<br>Bevestig via <a href=https://www.ezac.nl$url_bevestiging>DEZE LINK</a>";
      $body .= "<br>";
    }
    $body .= "<br>Mocht het niet mogelijk zijn hiervan gebruik te maken, dan kan deze reservering";
    $body .= "<br>via <a href=https://www.ezac.nl$url_verwijderen>DEZE LINK</a> worden geannuleerd ";
    $body .= "<br>";
    $body .= "<br>Je hebt aangegeven $mail_keuze op de EZAC mailing list te willen";
    $body .= "<br>";
    $body .= "<br>Voor verdere contact gegevens: zie de <a href=https://www.ezac.nl>EZAC website</a>";
    $body .= "<br>";
    $body .= "<br>Met vriendelijke groet,";
    $body .= "<br>Eerste Zeeuws Vlaamse Aero Club";
    $body .= "</body></html>";

    //   Mailen van bevestiging
    $headers = [
      //'From' => "webmaster@ezac.nl",
      'Bcc' => "webmaster@ezac.nl",
      //'X-Mailer' => "PHP",
      'Content-Type' => "text/html; charset=iso-8859-1",
      //'Return-Path' => "webmaster@ezac.nl",
    ];
    $mailed = mail($email, $subject, $body, $headers);

    if ($mailed) $messenger->addMessage("Bevestiging verstuurd aan $email",'status');
    else $messenger->addMessage("Bevestiging aan $email kon niet worden verstuurd", 'error');

    if ($status == $parameters['reservering_optie']) {
      $messenger->addMessage($texts['nog_bevestigen']);
    }
    else $messenger->addMessage($texts['reservering_geplaatst']);
    $form_state->setRedirect('ezac_passagiers_reservering');
  } //submitForm

}
