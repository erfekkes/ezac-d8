<?php


namespace Drupal\ezac_starts\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

class EzacStartsUploadForm extends \Drupal\Core\Form\FormBase {

  /**
   * @inheritDoc
   */
  public function getFormId() {
    return 'ezac_starts_upload_form';
  }

  /**
   * @inheritDoc
   * see https://www.drupal.org/node/347251
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // build form with table for start records from file
    // table is filled in validate function
    // for invalid records - show error ? select duplicate entries

    $form = array();
    // If this #attribute is not present, upload will fail on submit
    $form['#attributes']['enctype'] = 'multipart/form-data';

    $validators = array(
      'file_validate_extensions' => array('csv'),
    );
    $form['csv_upload_file'] = array(
      '#type' => 'managed_file',
      //'#type' => 'file',
      '#name' => 'csv_upload_file',
      '#title' => t('File'),
      '#size' => 20,
      '#description' => t('CSV format only'),
      '#upload_validators' => $validators,
      '#upload_location' => 'public://my_files/',
    );
    /*
    $form['file_upload'] = array(
      '#title' => t('Upload start file'),
      '#type'  => 'file',
    );
    */
    $form['submit_upload'] = array(
      '#type'  =>  'submit',
      '#value'  =>  'Submit'
    );
    return $form;
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * process uploaded starts file
   * check for double entries - idempotency
   *
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state); // TODO: Change the autogenerated stub
  }


  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $file = $form_state->getValue('csv_upload_file')[0];
    $form_state->setRedirect(
      'ezac_starts_upload_process_form',
      ['file' => $file],
    );
  }

}