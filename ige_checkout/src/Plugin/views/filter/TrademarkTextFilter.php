<?php

namespace Drupal\ige_checkout\Plugin\views\filter;

use Drupal\ige_checkout\Helper;

use Drupal\views\Plugin\views\filter\StringFilter;
use Drupal\views\Plugin\views\field\UncacheableFieldHandlerTrait;
use Drupal\views\ResultRow;

use Drupal\Core\Entity\EntityTypeManagerInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to display the voucher availability for a course.
 *
 * Displays the vouchers still assigned to a particular user out of the total
 * quantity purchased for that course.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("ige_checkout_trademark_text_filter")
 */
class TrademarkTextFilter extends StringFilter {

  /**
   * The current display.
   *
   * @var string
   *   The current display of the view.
   */
  protected $currentDisplay;

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->valueTitle = t('Filter by trademark text');
    $this->currentDisplay = $view->current_display;
  }

  /**
   * Helper function that builds the query.
   */
  public function query() {
    if (!empty($this->value)) {
      $configuration = [
        'table' => 'commerce_order_item',
        'field' => 'order_id',
        'left_table' => 'commerce_order',
        'left_field' => 'order_id',
        'operator' => '=',
      ];
      $join = Views::pluginManager('join')->createInstance('standard', $configuration);
      $this->query->addRelationship('commerce_order_item', $join, 'commerce_order');

      $configuration = [
        'table' => 'commerce_order_item',
        'field' => 'order_item_id',
        'left_table' => 'commerce_order_item__field_trademark_text',
        'left_field' => 'entity_id',
        'operator' => '=',
      ];
      $join = Views::pluginManager('join')->createInstance('standard', $configuration);
      $this->query->addRelationship('commerce_order_item', $join, 'commerce_order_item__field_trademark_text');

      $this->query->addWhere('AND', 'commerce_order_item__field_trademark_text.field_trademark_text_value', $this->value, 'CONTAINS');
    }
  }

}
