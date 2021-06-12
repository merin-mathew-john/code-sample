<?php

namespace Drupal\ige_checkout\Plugin\views\field;

use Drupal\paragraphs\Entity\Paragraph;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Plugin\views\field\UncacheableFieldHandlerTrait;
use Drupal\views\ResultRow;

use Drupal\Core\Entity\EntityTypeManagerInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to display the class data for a order.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("order_items_class_data")
 */
class ClassData extends FieldPluginBase {

  use UncacheableFieldHandlerTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('entity_type.manager')
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
    $order_item = $values->_entity;
    $markup = '<ul>';

    foreach ($order_item->get('field_class_data')->getValue() as $class) {
      $class_data = Paragraph::load($class['target_id']);
      $class_id = (int) trim($class_data->field_class_name->value);
      $default_classes[$class_id] = $class_data->field_class_description->value;
      $markup = $markup . '<li><b>Class ' . $class_id . ':</b>' .  $class_data->field_class_description->value . '</li>';
    }

    $markup = $markup . '</ul>';
    $render = [
      '#type' => 'markup',
      '#markup' => $markup,
    ];
    return $render;
  }

}
