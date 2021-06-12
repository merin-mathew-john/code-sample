<?php

namespace Drupal\ige_quote\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form to select number of class fromsidebar in quote tool.
 */
class QuoteAdditionalClassForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ige_quote_additional_class_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $quote = \Drupal::request()->attributes->get('quote');
    $classes = 1;
    if (!empty($quote)) {
      $classes = $quote->field_number_of_class->value;
    }
    $form['classes'] = [
      '#type' => 'select',
      '#title' => $this->t('In how many classes do you wish to register your trademark?'),
      '#default_value' => $classes,
      '#options' => array_combine(range(1, 45), range(1, 45)),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}
