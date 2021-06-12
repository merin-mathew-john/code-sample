<?php

namespace Drupal\ige_quote\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Quote Form' block.
 *
 * @Block(
 *  id = "quote_form_block",
 *  admin_label = @Translation("Quote Form block"),
 * )
 */
class QuoteFormBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $user = \Drupal::currentUser();
    if ($user->hasPermission('add quote')) {
      $build['form'] = \Drupal::formBuilder()->getForm('\Drupal\ige_quote\Form\QuoteForm');
    }
    return $build;
  }

}
