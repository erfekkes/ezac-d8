<?php

namespace Drupal\ezac_leden\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ezac\Util\EzacUtil;
use Drupal\ezac_leden\Model\EzacLid;
use Drupal\user\Entity\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

/**
 * UI to update leden record
 * tijdelijke aanpassing
 */

class EzacLedenUpdateForm extends FormBase
{

    /**
     * @inheritdoc
     */
    public function getFormId()
    {
        return 'ezac_leden_update_form';
    }

    /**
     * buildForm for LEDEN update with ID parameter
     * This is also used to CREATE new leden record (no ID param)
     * @param array $form
     * @param FormStateInterface $form_state
     * @param null $id
     * @return array
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL)
    {
        // Wrap the form in a div.
        $form = [
            '#prefix' => '<div id="updateform">',
            '#suffix' => '</div>',
        ];

        // Query for items to display.
        // if $id is set, perform UPDATE else CREATE
        if (isset($id)) {
            $lid = new EzacLid($id); // using constructor
            $newRecord = FALSE;
        } else { // prepare new record
            $lid = new EzacLid(); // create empty lid occurrence
            $newRecord = TRUE;
        }

        //store indicator for new record for submit function
        $form['new'] = [
            '#type' => 'value',
            '#value' => $newRecord, // TRUE or FALSE
        ];

        $options_yn = [t('Nee'), t('Ja')];

        //Naam Type Omvang
        //VOORVOEG Tekst 11
        $form = EzacUtil::addField($form,'voorvoeg', 'textfield','Voorvoeg', 'Voorvoegsel', $lid->voorvoeg, 11, 11, FALSE, 1);
        //ACHTERNAAM Tekst 35
        $form = EzacUtil::addField($form,'achternaam', 'textfield','Achternaam', 'Achternaam', $lid->achternaam, 35, 35, TRUE, 2);
        //AFKORTING Tekst 9
        $form = EzacUtil::addField($form,'afkorting', 'textfield','Afkorting', 'UNIEKE afkorting voor startadministratie', $lid->afkorting, 9, 9, FALSE, 3);
        //VOORNAAM Tekst 13
        $form = EzacUtil::addField($form,'voornaam', 'textfield','Voornaam', 'Voornaam', $lid->voornaam, 13, 13, FALSE, 4);
        //VOORLETTER Tekst 21
        $form = EzacUtil::addField($form,'voorletter', 'textfield','Voorletters', 'Voorletters', $lid->voorletter, 21, 21, FALSE, 5);
        //ADRES Tekst 26
        $form = EzacUtil::addField($form,'adres', 'textfield','Adres', 'Adres', $lid->adres, 26, 26, TRUE, 6);
        //POSTCODE Tekst 9
        $form = EzacUtil::addField($form,'postcode', 'textfield','Postcode', 'Postcode', $lid->postcode, 9, 9, TRUE, 7);
        //PLAATS Tekst 24
        $form = EzacUtil::addField($form,'plaats', 'textfield','Plaats', 'Plaats', $lid->plaats, 24, 24, TRUE, 8);
        //TELEFOON Tekst 14
        $form = EzacUtil::addField($form,'telefoon', 'textfield','Telefoon', 'Telefoon', $lid->telefoon, 14, 14, FALSE, 9);
        //Mobiel Tekst 50
        $form = EzacUtil::addField($form,'mobiel', 'textfield','Mobiel', 'Mobiel nummer', $lid->mobiel, 50, 14, FALSE, 10);
        //LAND Tekst 10
        $form = EzacUtil::addField($form,'land', 'textfield','Land', 'Land', $lid->land, 10, 10, FALSE, 11);
        //CODE Tekst 5
        $form = EzacUtil::addField($form,'code', 'select','Code', 'Code', $lid->code, 5, 1, FALSE, 12, EzacLid::$lidCode);
        // Tienrittenkaart
        $form = EzacUtil::addField($form,'tienrittenkaart', 'checkbox','Tienrittenkaart', 'Tienrittenkaarthouder', $lid->tienrittenkaart, 1, 1, FALSE, 12);
        //GEBOORTEDA Datum/tijd 8
        $gd = substr($lid->geboorteda, 0, 10);
        if ($gd != NULL) {
            $lv = explode('-', $gd);
            $gebdat = sprintf('%s-%s-%s', $lv[2], $lv[1], $lv[0]);
        } else $gebdat = '';
        $form = EzacUtil::addField($form,'geboorteda', 'textfield','Geboortedatum', 'Geboortedatum [dd-mm-jjjj]', $gebdat, 10, 10, FALSE, 13);
        //OPMERKING Tekst 27
        $form = EzacUtil::addField($form,'opmerking', 'textfield','Opmerking', 'Opmerking', $lid->opmerking, 27, 27, FALSE, 14);
        //INSTRUCTEU Tekst 9 ** foutief in database **
        //Actief Ja/nee 1
        $form = EzacUtil::addField($form,'actief', 'checkbox','actief', 'Nog actief lid?', $lid->actief, 1, 1, FALSE, 15);
        //LID_VAN Datum/tijd 8
        $ls = substr($lid->lid_van, 0, 10);
        if ($ls != NULL) {
            $lv = explode('-', $ls);
            $lid_van = sprintf('%s-%s-%s', $lv[2], $lv[1], $lv[0]);
        } else $lid_van = '';
        $form = EzacUtil::addField($form,'lid_van', 'textfield','Lid vanaf', 'Ingangsdatum lidmaatschap [dd-mm-jjjj]', $lid_van, 10, 10, FALSE, 16);
        //LID_EIND Datum/tijd 8
        $le = substr($lid->lid_eind, 0, 10);
        if ($le != NULL) {
            $lv = explode('-', $le);
            $lid_eind = sprintf('%s-%s-%s', $lv[2], $lv[1], $lv[0]);
        } else $lid_eind = '';
        $form = EzacUtil::addField($form,'lid_eind', 'textfield','Lid einde', 'Datum einde lidmaatschap [dd-mm-jjjj]', $lid_eind, 10, 10, FALSE, 17);
        // RT license
        $form = EzacUtil::addField($form,'rtlicense', 'checkbox','RT licentie', 'RT bevoegdheid (Ja/nee)', $lid->rtlicense, 1, 1, FALSE, 18);
        //leerling Ja/nee 0
        $form = EzacUtil::addField($form,'leerling', 'checkbox','Leerling', 'Leerling (Ja/nee)', $lid->leerling, 1, 1, FALSE, 18);
        //Instructie Ja/nee 1
        $form = EzacUtil::addField($form,'instructie', 'checkbox','Instructie', 'Instructeur (Ja/nee)', $lid->instructie, 1, 1, FALSE, 19);
        //E_mail Tekst 50
        $form = EzacUtil::addField($form,'e_mail', 'email','E-mail', 'E-mail adres', $lid->e_mail, 50, 30, FALSE, 20);
        //Babyvriend Ja/nee 1
        $form = EzacUtil::addField($form,'babyvriend', 'checkbox','Babyvriend', 'Vriend van Nico Baby(Ja/nee)', $lid->babyvriend, 1, 1, FALSE, 21);
        //Ledenlijstje Ja/nee 1
        $form = EzacUtil::addField($form,'ledenlijstje', 'checkbox','Ledenlijst', 'Vermelding op ledenlijst (Ja/nee)', $lid->ledenlijstje, 1, 1, FALSE, 21);
        //Etiketje Ja/nee 1
        $form = EzacUtil::addField($form,'etiketje', 'checkbox','Etiket', 'Etiket afdrukken (Ja/nee)', $lid->etiketje, 1, 1, FALSE, 22);
        //User Tekst 50
        $form = EzacUtil::addField($form,'user', 'textfield','Usercode website', 'Usercode website (VVAAAA)', $lid->user, 6, 6, FALSE, 23);
        //seniorlid Ja/nee 1
        $form = EzacUtil::addField($form,'seniorlid', 'checkbox','Senior lid', 'Senior lid (Ja/nee)', $lid->seniorlid, 1, 1, FALSE, 24);
        //jeugdlid Ja/nee 1
        $form = EzacUtil::addField($form,'jeugdlid', 'checkbox','Jeugd / inwonend lid', 'Jeugd / inwonend lid (Ja/nee)', $lid->jeugdlid, 1, 1, FALSE, 25);
        //PEonderhoud Ja/nee 1
        $form = EzacUtil::addField($form,'peonderhoud', 'checkbox','Prive Eigenaar onderhoud (CAMO)', 'Prive Eigenaar onderhoud(Ja/nee)', $lid->peonderhoud, 1, 1, FALSE, 26);
        //Slotcode varchar(8)
        $form = EzacUtil::addField($form,'slotcode', 'textfield','Slot code', 'Slotcode (nnnnnn)', $lid->slotcode, 8, 8, FALSE, 27);

        //Mutatie timestamp
        //maak tekstlabel met datum laatste wijziging (wordt automatisch bijgewerkt)

        //Id
        //Toon het het Id nummer van het record
        $form = EzacUtil::addField($form,'id', 'hidden','Record nummer (Id)', '', $lid->id, 8, 8, FALSE, 28);
        //WijzigingSoort
        //Toon de soort mutatie NIEUW WIJZIGING VERVALLEN
        $form = EzacUtil::addField($form,'wijzigingsoort', 'hidden','Soort wijziging', '', $lid->wijzigingsoort, 15, 25, FALSE, 29);
        //KenEZACvan
        //Hoe is EZAC ontdekt
        $form = EzacUtil::addField($form,'kenezacvan', 'textfield','Ken EZAC van', '', $lid->kenezacvan, 20, 20, FALSE, 30);

        $form['actions'] = [
            '#type' => 'actions',
        ];

        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $newRecord ? t('Invoeren') : t('Update'),
            '#weight' => 31
        ];

        //insert Delete button  gevaarlijk ivm dependencies
        if (\Drupal::currentUser()->hasPermission('EZAC_delete')) {
            if (!$newRecord) {
                $form['actions']['delete'] = [
                    '#type' => 'submit',
                    '#value' => t('Verwijderen'),
                    '#weight' => 32
                ];
            }
        }
        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {

        // perform validate for edit of record

        // Voorvoeg
        // Achternaam
        // Afkorting
        $afkorting = $form_state->getValue('afkorting');
        if ($afkorting <> $form['afkorting']['#default_value']) {
            if (EzacLid::counter(['afkorting' => $afkorting])) {
                $form_state->setErrorByName('afk', t("Afkorting $afkorting bestaat al"));
            }
        }
        // Code
        if (!array_key_exists($form_state->getValue('code'), EzacLid::$lidCode)) {
            $form_state->setErrorByName('code', t("Ongeldige code"));
        }
        // Voornaam
        // Voorletter
        // Adres
        // Postcode
        // Plaats
        // Telefoon
        // Mobiel
        // Land
        // Code
        // Geboorteda
        $dat = $form_state->getValue('geboorteda');
        if ($dat !== '') {
            $lv = explode('-', $dat);
            if (checkdate($lv[1], $lv[0], $lv[2]) == FALSE) {
                $form_state->setErrorByName('geboortedatum', t('Geboortedatum is onjuist'));
            }
        }
        // Opmerking
        // Instructeu
        // Actief
        // Lid_van
        $dat = $form_state->getValue('lid_van');
        if ($dat !== '') {
            $lv = explode('-', $dat);
            if (checkdate($lv[1], $lv[0], $lv[2]) == FALSE) {
                $form_state->setErrorByName('lidvan', 'Datum begin lidmaatschap is onjuist');
            }
        }

        // Lid_eind
        $dat = $form_state->getValue('lid_eind');
        if ($dat !== '') {
            $lv = explode('-', $dat);
            if (checkdate($lv[1], $lv[0], $lv[2]) == FALSE) {
                $form_state->setErrorByName('lideind', 'Datum einde lidmaatschap is onjuist');
            }
        }
        // RTlicense
        // E_mail
        // check e_mail does not exist yet
        $e_mail = $form_state->getValue('e_mail');
        if ($e_mail <> $form['e_mail']['#default_value']) {
          if (EzacLid::counter(['e_mail' => $e_mail])) {
            $form_state->setErrorByName('e_mail', t("E-mail adres $e_mail bestaat al"));
          }
        }
        // Babyvriend
        // Ledenlijst
        // Etiketje
        // User
        //  check user code does not exist yet
        $user = $form_state->getValue('user');
        if ($user <> $form['user']['#default_value']) {
          if (EzacLid::counter(['user' => $user])) {
            $form_state->setErrorByName('user', t("User naam $user bestaat al"));
          }
        }
        // seniorlid
        // jeugdlid
        // PEonderhoud
        // Slotcode
        // Mutatie
        // KenEZACvan
    }

  /**
   * change datum from DD-MM-JJJJ to YYYY-MM-DD unix format
   * @param $datum
   *
   * @return string
   */
  private function swap_datum($datum) {
    $d = explode('-', $datum);
    if (key_exists(2, $d)) return $d[2] .'-' .$d[1] .'-' .$d[0];
    else return null;
  }

    /**
     * {@inheritdoc}
     * @throws \Exception
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
      $messenger = \Drupal::messenger();

      // delete record
        if ($form_state->getValue('op') == 'Verwijderen') {
            if (!\Drupal::currentUser()->hasPermission('EZAC_delete')) {
                $messenger->addMessage('Verwijderen niet toegestaan', $messenger::TYPE_ERROR);
                return [];
            }
            $lid = new EzacLid; // initiate Lid instance
            $lid->id = $form_state->getValue('id');
            $count = $lid->delete(); // delete record in database
            $messenger->addMessage("$count record verwijderd");
        } else {
            // Save the submitted entry.
            $lid = new EzacLid;
            // get all fields
            foreach (EzacLid::$fields as $field => $description) {
                $lid->$field = $form_state->getValue($field);
            }
          //datum velden omzetten van DD-MM-JJJJ naar YYYY-MM-DD
          $lid->lid_van = self::swap_datum($lid->lid_van);
          $lid->lid_eind = self::swap_datum($lid->lid_eind);
          $lid->geboorteda = self::swap_datum($lid->geboorteda);

            //Check value newRecord to select insert or update
            if ($form_state->getValue('new') == TRUE) {
                $id = $lid->create(); // add record in database
                $messenger->addMessage("Leden record aangemaakt met id [$id]", $messenger::TYPE_STATUS);

              // add drupal user for www.ezac.nl
              if ($lid->user <> '') { // a user code for drupal was entered
                self::register_user($lid);
              }

            } else { // existing user record updated
                $count = $lid->update(); // update record in database
                $messenger->addMessage("$count record updated", $messenger::TYPE_STATUS);
            }
        }
        //go back to leden overzicht
        $redirect = Url::fromRoute(
            'ezac_leden'
        );
        $form_state->setRedirectUrl($redirect);
    } //submitForm

  /**
   * @param \Drupal\ezac_leden\Model\EzacLid $lid
   *
   * @return false|void
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  function register_user(EzacLid $lid) {
      $messenger = \Drupal::messenger();
      // read settings
      $settings = \Drupal::config('ezac_leden.settings');
      $base_uri = $settings->get('base_uri');
      $user_register = $settings->get('user_register');
      $user_patch = $settings->get('user');
      $CSRF_token = $settings->get('CSRF_Token');
      $auth_params = $settings->get('auth_params');

      // add drupal user for www.ezac.nl
      // get CSRF token
      $client = new Client(['base_uri' => $base_uri]);
      try {
        $response = $client->request('GET', $CSRF_token);
      }
      catch (ClientException $e) {
        $messenger->addMessage("Geen CSRF token ontvangen [$e]");
        return;
      }
      $token = (string) $response->getBody();

      // register user
      try {
        $response = $client->request(
          'POST',
          $user_register,
          [
            'auth' => [
              $auth_params['user'],
              $auth_params['password'],
            ],
            'headers' => [
              'Content-Type' => 'application/json',
              'Accept' => 'application/json',
              'X-CSRF_Token' => $token,
            ],
            'form_params' => [
              '_format' => 'hal_json',
            ],
            'json' => [
              'name' => [$lid->user],
              'mail' => [$lid->e_mail],
              'status' => [0], // blocked - in order to request password
            ],
          ],
        );
        $data = (string) $response->getBody();
      }
      catch (ClientException $e) {
        $messenger->addError("Register user $lid->user fout");
        return;
      }

      $statusCode = $response->getStatusCode();
      if ($statusCode <> '201') { // HTTP fout ontvangen
        $messenger->addError("Fout $statusCode bij registratie drupal user");
      }
      else { // goede HTTP response ontvangen
        $user = json_decode($data);
        $u = $user->uid[0]->value;
        $n = $user->name[0]->value;
        $m = $user->mail[0]->value;
        $messenger->addMessage("Drupal user $u aangemaakt voor [$n] met mail adres $m");

        //activate user
        $user_object = User::load((int) $u);
        $user_object->activate();
        $user_object->save();
      }
    } // register_user

}
