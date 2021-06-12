<?php

namespace Drupal\ige_checkout\Plugin\views\field;

use Drupal\ige_checkout\Helper;

use Drupal\views\Plugin\views\field\FieldPluginBase;
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
 * @ViewsField("ige_checkout_trademark_text")
 */
class TrademarkText extends FieldPluginBase {

  use UncacheableFieldHandlerTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The checkout helper class.
   *
   * @var \Drupal\ige_checkout\Helper
   */
  protected $checkoutHelper;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    Helper $checkout_helper
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->checkoutHelper = $checkout_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('ige_checkout.checkout_helper')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function query() {
  }

  /**
   * {@inheritDoc}
   */
  public function render(ResultRow $values) {
    $trademark_text = [];
    $commerce_order = $values->_entity;
    $order_items = $commerce_order->getItems();

    foreach ($order_items as $order_item) {
      if ($order_item->field_trademark_text->value) {
        $trademark_text[] = $order_item->field_trademark_text->value;
      }
      if ($order_item->field_admin_added->value) {
        $trademark_text[] = $order_item->getTitle();
      }
    }

    return implode(", ", $trademark_text);

  }

}
