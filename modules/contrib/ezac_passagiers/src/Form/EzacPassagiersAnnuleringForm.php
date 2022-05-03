<?php

namespace Drupal\ezac_passagiers\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ezac\Util\EzacUtil;
use Drupal\ezac_passagiers\Controller\EzacPassagiersController;
use Drupal\ezac_passagiers\Model\EzacPassagier;

/**
 * UI to show passagiers verwijder form
 * Verwijdering van een reservering door passagier zelf via web link
 */
class EzacPassagiersAnnuleringForm extends FormBase {

  /**
   * @inheritdoc
   */
  public function getFormId(): string {
    return 'ezac_passagiers_annulering_form';
  }

  /**
   * buildForm for passagiers dagen
   *
   * @param array $form
   * @param FormStateInterface $form_state
   * @param int|null $id
   * @param string|null $hash
   *
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state, int $id = NULL, string $hash = NULL): array {
    $messenger = Drupal::messenger();

    // Wrap the form in a div.
    $form = [
      '#prefix' => '<div id="annuleringform">',
      '#suffix' => '</div>',
    ];

    if (isset($id) and is_numeric($id)) {
      $reservering = new EzacPassagier($id);
    }
    else {
      $messenger->addMessage("Ongeldig id $id", 'error');
      $form_state->setRedirect('ezac_passagiers');
      return $form;
    }
    if ($reservering == NULL) {
      $messenger->addMessage("Reservering $id niet gevonden", 'error');
      $form_state->setRedirect('ezac_passagiers');
      return $form;
    }

    // build form for passagier edit
    // velden: id datum tijd naam telefoon mail aangemaakt aanmaker soort
    $id = $reservering->id;
    $datum = substr($reservering->datum, 0, 10);
    $tijd = substr($reservering->tijd, 0, 5); //skip seconds
    $naam = $reservering->naam;
    $mail = $reservering->mail;
    $telefoon = $reservering->telefoon;
    $show_datum = EzacUtil::showDate($datum);
    //ask user for mail address used with the reservation
    //this is the confirmation that we have the user has access rights for this reservation

    //check mail entered with mail on record in validate
    //when ok proceed to delete option

    $form[0]['#type'] = 'markup';
    $form[0]['#markup'] = "<p><h2>Reservering $id van $show_datum $tijd verwijderen voor $naam</h2></p>";
    $form[0]['#weight'] = 1;
    $form[0]['#prefix'] = '<div class="ezacpass-delete-div">';
    $form[0]['#suffix'] = '</div>';

    $form['id'] = [
      '#type' => 'value',
      '#value' => $id,
    ];
    $form['datum'] = [
      '#type' => 'value',
      '#value' => $datum,
    ];
    $form['tijd'] = [
      '#type' => 'value',
      '#value' => $tijd,
    ];
    $form['naam'] = [
      '#type' => 'value',
      '#value' => $naam,
    ];
    $form['mail'] = [
      '#type' => 'value',
      '#value' => $mail,
    ];
    $form['telefoon'] = [
      '#type' => 'value',
      '#value' => $telefoon,
    ];
    $form['hash'] = [
      '#type' => 'value',
      '#value' => $hash,
    ];
    $form['mail_check'] = [
      '#title' => t('Geef ter bevestiging je E-mail adres'),
      '#type' => 'textfield',
      '#description' => t('E-mail adres'),
      '#maxlength' => 50,
      '#required' => TRUE,
      '#size' => 50,
      '#weight' => 2,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Verwijder reservering'),
      '#weight' => 3,
    ];

    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (strcasecmp($form_state->getValue('mail_check'), $form_state->getValue('mail')) !== 0) {
      $form_state->setErrorByName('mail_check', "Mail adres is onjuist");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $messenger = Drupal::messenger();

    $id = $form_state->getValue('id');
    $naam = $form_state->getValue('naam');

    $hash_fields = [
      'id' => $id,
      'datum' => $form_state->getValue('datum'),
      'tijd' => $form_state->getValue('tijd'),
      'naam' => $naam,
      'mail' => $form_state->getValue('mail'),
      'telefoon' => $form_state->getValue('telefoon'),
    ];
    $data = implode('/', $hash_fields);
    $calculated_hash = hash('sha256', $data, FALSE);

    if ($calculated_hash != $form_state->getValue('hash')) {
      $messenger->addError("Onjuiste code voor passagier $id");
    }
    else {
      $op = $form_state->getValue('op');
      if ($op == 'Verwijder reservering') {
        $reden = "Verwijderd door passagier $naam";
        // verwijder reservering en mail passagier
        EzacPassagiersController::verwijderen($id, $reden);
      }
      else {
        $messenger->addMessage("Geen reservering verwijderd", 'status');
      }
      $form_state->setRedirect('ezac_passagiers');
    }
  }

}
