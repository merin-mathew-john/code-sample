<?php

namespace Drupal\ige_checkout\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\commerce_stripe\Event\TransactionDataEvent;
use Drupal\commerce_stripe\Event\StripeEvents;
use Drupal\Core\Entity\EntityTypeManager;

/**
 * Send metadata to stripe.
 */
class StripeMetadataSubscriber implements EventSubscriberInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManager $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[StripeEvents::TRANSACTION_DATA] = ['stripeMetaData'];

    return $events;
  }

  /**
   * Called whenever commerce_stripe.transaction_data event is dispatched.
   *
   * @param Drupal\commerce_stripe\Event\TransactionDataEvent $event
   *   The stripe transaction event.
   */
  public function stripeMetaData(TransactionDataEvent $event) {
    $order = $event->getPayment()->getOrder();
    $metadata['user'] = $order->getCustomer()->field_name->value;
    $metadata['mail'] = $order->getCustomer()->getEmail();
    if ($order->getCustomer()->field_consultant->entity) {
      $metadata['consultant'] = $order->getCustomer()->field_consultant->entity->field_name->value;
    }

    foreach ($order->getItems() as $item) {
      $country[$item->field_product->entity->title->value] = $item->field_product->entity->title->value;
      $trademark_text[$item->field_trademark_text->value] = $item->field_trademark_text->value;
      $trademark_type[$item->field_trademark_type->value] = $item->field_trademark_type->value;
    }

    $metadata['countries'] = implode(", ", $country);
    $metadata['trademark_text'] = implode(", ", $trademark_text);
    $metadata['service'] = implode(", ", $trademark_type);
    $event->setMetadata($metadata);
  }

}
