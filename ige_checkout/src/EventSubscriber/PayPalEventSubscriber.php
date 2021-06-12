<?php

namespace Drupal\ige_checkout\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\commerce_paypal\Event\CheckoutOrderRequestEvent;
use Drupal\commerce_paypal\Event\PayPalEvents;

/**
 * Send order data to paypal.
 */
class PayPalEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      PayPalEvents::CHECKOUT_CREATE_ORDER_REQUEST => 'addOrderData',
    ];
    return $events;
  }

  /**
   * Adds the order metadata to paypal.
   *
   * @param Drupal\commerce_paypal\Event\CheckoutOrderRequestEvent $event
   *   The checkout order request event.
   */
  public function addOrderData(CheckoutOrderRequestEvent $event) {
    // Get the customer's order using the event's getOrder() method.
    $order = $event->getOrder();

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

    // Get the array of parameters that will be transmitted to PayPal using the
    // event's getRequestBody() method.
    $params = $event->getRequestBody();

    // Add data value to that array (assumes value is properly formatted).
    $params['order'] = $metadata;

    // Set the API request body that will be transmitted to PayPal with the
    // updated array, using the event's setRequestBody() method.
    $event->setRequestBody($params);
  }

}
