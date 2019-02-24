<?php

namespace Drupal\login_with_salesforce\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class SettingsForm.
 *
 * @package Drupal\login_with_salesforce\Form
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'login_with_salesforce_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'login_with_salesforce.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('login_with_salesforce.settings');

    $form['login_url'] = [
      '#type' => 'textfield',
      '#title' => t('Login with Salesforce'),
      '#required' => TRUE,
      '#default_value' => $config->get('login_url'),
    ];

    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => t('Consumer key'),
      '#required' => TRUE,
      '#default_value' => $config->get('client_id'),
    ];

    $form['client_secret'] = [
      '#type' => 'textfield',
      '#title' => t('Consumer secret'),
      '#required' => TRUE,
      '#default_value' => $config->get('client_secret'),
    ];

    $form['redirect_uri'] = [
      '#type' => 'textfield',
      '#title' => t('Redirect Uri'),
      '#disabled' => TRUE,
      '#required' => TRUE,
      '#default_value' => \Drupal::request()->getSchemeAndHttpHost() . '/salesforce/callback',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $this->config('login_with_salesforce.settings')
      ->set('login_url', $values['login_url'])
      ->set('client_id', $values['client_id'])
      ->set('client_secret', $values['client_secret'])
      ->set('redirect_uri', $values['redirect_uri'])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
