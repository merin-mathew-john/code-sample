<?php

namespace Drupal\ige_quote\Controller;

use Mpdf\Mpdf;
use Drupal\Core\Controller\ControllerBase;

/**
 * The controller for rendering a quote form.
 */
class QuoteController extends ControllerBase {

  /**
   * Load the quote form.
   *
   * @return array
   *   Returns the quote form.
   */
  public function addQuote() {
    $build = [];
    $build['form'] = \Drupal::formBuilder()->getForm('\Drupal\ige_quote\Form\QuoteForm');
    return $build;
  }

  /**
   * Update the class number of a quote.
   *
   * @param int $quote_id
   *   The quote id.
   * @param int $class_number
   *   The updated number of class.
   *
   * @return quote
   *   The updated quote.
   */
  public function updateQuote($quote_id, $class_number) {
    $storage = \Drupal::service('entity.manager')->getStorage('quote');
    $quote = $storage->load($quote_id);
    $quote->field_number_of_class->value = (int) $class_number;
    $quote->save();
    return $this->redirect('entity.quote.canonical', ['quote' => $quote_id]);
  }

}
