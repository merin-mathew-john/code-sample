<?php

namespace Drupal\ige_checkout\Plugin\QueueWorker;

use Drupal\ige_checkout\Helper;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\QueueFactory;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deletes expired carts.
 *
 * @QueueWorker(
 *  id = "ige_checkout_create_tracker",
 *  title = @Translation("Create Service Tracker"),
 *  cron = {"time" = 30}
 * )
 */
class CreateTracker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The checkout helper class.
   *
   * @var \Drupal\ige_checkout\Helper
   */
  protected $checkoutHelper;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new CartExpiration object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\ige_checkout\Helper $checkout_helper
   *   The checkout helper.
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
   * {@inheritdoc}
   */
  public function processItem($data) {
    $order_item = $data['order_item'];
    $order = $data['order'];

    $class_numbers = [];
    $class_data = [];
    $status = "in_process";

    $service_types = $order_item->field_trademark_type->getValue();
    foreach ($service_types as $type) {
      $types[] = $type['value'];
    }
    // Continue creating trackers if we do not have a tracker for orderitem.
    $tracker_ids = $order_item->field_tracker->entity;
    if (!$tracker_ids) {
      $products = $order_item->field_product->referencedEntities();
      foreach ($products as $product) {

        $product_variations = $product->variations->referencedEntities();

        // Loop through each product variations.
        foreach ($product_variations as $product_variation) {
          $trademark_service = $product_variation
            ->get('attribute_trademark_service')->entity->getName();
          $trademark_service = strtolower($trademark_service);
          $variations[$trademark_service] = $product_variation;
        }

        // We would need tracker for each product variation.
        foreach ($types as $type) {
          if (!isset($variations[$type])) {
            continue;
          }
          $variation = $variations[$type];
          $service_data['provider'] = $variation->get('field_default_provider')->entity;
          $service_data['customer'] = $order->getCustomer();
          $service_data['order'] = $order;
          $service_data['order_item'] = $order_item;
          $service_data['owner'] = $order_item->field_owner->entity;
          $service_data['trademark_type'] = $type;
          $service_data['trademark_logo'] = $order_item->field_image->getValue();
          $service_data['products'] = $product;
          $service_data['text'] = $order_item->field_trademark_text->getValue();
          $service_data['filling_date'] = NULL;
          $service_data['filling_number'] = NULL;
          $service_data['status'] = $status;
          $service_data['reg_date'] = NULL;
          $service_data['reg_number'] = NULL;
          $service_data['consultant'] = NULL;

          $duration = $variation->field_timeframe->value;
          $date = REQUEST_TIME;
          $date = strtotime("+$duration day", $date);
          $service_data['timeframe'] = date("Y-m-d", $date);
          foreach ($order_item->get('field_class_data')->referencedEntities() as $class_fc) {
            $class_id = $class_fc->field_class_name->value;
            $class_data[$class_id] = $class_fc->field_class_description->value;
            $class_numbers[$class_id] = $class_id;
          }

          $combined_class_text = implode(', ', $class_numbers);

          if ($variation->field_divides_by_class->value == '0') {
            $service_data['title'] = ucfirst($service_data['trademark_type']) . ' - ' . $order_item->field_trademark_text->value . ' - ' . $product->title->value . ' - Class:' . $combined_class_text;
            $service_data['class'] = $class_data;
            $tracker = $this->checkoutHelper->createServiceTracker($service_data);
            $order_item->field_tracker[] = $tracker;
            $order->field_tracker[] = $tracker;
          }
          else {
            foreach ($class_data as $key => $class) {
              $service_data['title'] = t(
                '@service @trademark_text - @country_name - Class: @class_term',
                [
                  '@service' => ucfirst($service_data['trademark_type']),
                  '@trademark_text' => $order_item->field_trademark_text->value,
                  '@country_name' => $product->title->value,
                  '@class_term' => $key,
                ]
              );
              $service_data['class'] = [];
              $service_data['class'][$key] = $class_data[$key];
              $tracker = $this->checkoutHelper->createServiceTracker($service_data);
              $order_item->field_tracker[] = $tracker;
              $order->field_tracker[] = $tracker;
            }
          }
        }
      }

      $logStorage = \Drupal::entityTypeManager()->getStorage('commerce_log');
      $logStorage->generate(
        $order,
        'commerce_order_admin_comment',
        ['comment' => 'System: Created service tracker']
      )->save();

      $order_item->save();
      $order->save();
    }
  }

}
