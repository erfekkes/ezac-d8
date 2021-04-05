<?php

namespace Drupal\ezac\Form;

//use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;



/**
* UI to print main menu
*/
class EzacHoofdMenuForm extends FormBase {

  /**
  * @inheritdoc
  */
  public function getFormId() {
    return 'ezac_hoofd_menu_form';
  }

  /**
   * buildForm for EZAC hoofdmenu
   */
  
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Wrap the form in a div.
    $form = array(
      '#prefix' => '<div id="EzacHoofdMenuForm">',
      '#suffix' => '</div>',
    );

    $form['menu'] = self::buildMenu();

    // Don't cache this page.
    $form['#cache']['max-age'] = 0;
    
    return $form;
  } //buildForm

  /**
   * jaar_callback ajax form update
   */
  public function jaarCallback($form, &$form_state) {
    return self::buildMenu();
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // empty
  }


  /**
   * @inheritdoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // empty
  }
  
  /**
   * Build the EZAC main menu.
   * @return array menu table
   */
  public function buildMenu() {
    $content = array();

    $headers = array(
      t("Menu keuze"),
      t("Functie"), 
    );
    $rows[] = array(
      t("<b>Tabel beheer</b>"),
      t(""),
    );
    $rows[]= array(
      t("<a href= ezac/leden/>Leden administratie</a>"),
      t("Inzage en wijzigen leden informatie"),
    );
    $rows[] = array(
      t("<a href= ezac/leden/update>Lid toevoegen</a>"),
      t("Invoeren gegevens nieuw lid"),
    );
    $rows[] = array(
      t("<a href= ezac/kisten/>Vloot administratie</a>"),
      t("Inzage en wijzigen vloot informatie"),
    );
    $rows[] = array(
      t("<a href= ezac/kisten/update>Kist toevoegen</a>"),
      t("Invoeren gegevens nieuw vliegtuig"),
    );
    $rows[] = array(
      t("<b>Startadministratie</b>"),
      t(""),
    );
    $rows[] = array(
      t("<a href= ezac/starts>Startadministratie</a>"),
      t("Overzicht startadministratie"),
    );
    $rows[] = array(
      t("<a href= ezac/starts/create/>Start invoer</a>"),
      t("Invoeren nieuwe start"),
    );
    $rows[] = array(
      t("<b>Voortgang / Bevoegdheden administratie</b>"),
      t(""),
    );
    $rows[] = array(
      t("<a href= ezac/vba>Overzicht</a>"),
      t("Overzicht VBA gegevens"),
    );
    $rows[] = array(
      t("<a href= ezac/vba>Invoeren</a>"),
      t("Invoeren verslagen en bevoegdheden"),
    );

    $table = array(
      '#type' => 'table',
      '#caption' => t("EZAC administratie hoofdmenu"),
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => t('Geen gegevens beschikbaar.'),
      '#sticky' => TRUE,
      '#prefix' => '<div id="menu-div">',
      '#suffix' => '</div>',
      '#weight' => 2,
    );

    return $table;
  }
  
  
} // EzacHoofdMenuForm
  