<?php

namespace Drupal\ige_checkout\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Class OrderCompleteSubscriber.
 */
class OrderCompleteSubscriber implements EventSubscriberInterface {

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
  static function getSubscribedEvents() {
    $events['commerce_order.place.post_transition'] = ['orderCompleteHandler'];
    $events['commerce_order.pending.post_transition'] = ['orderCompleteHandler'];

    return $events;
  }

  /**
   * Called whenever commerce_order.place.post_transition event is dispatched.
   *
   * @param Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The workflow transition event.
   */
  public function orderCompleteHandler(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();

    $entity_manager = \Drupal::entityManager();

    // Delete trademark requests.
    $pending_requests = $order->field_trademark_request->getValue();
    foreach ($pending_requests as $request) {
      $request = $entity_manager
        ->getStorage('trademark_request')
        ->load($request['target_id']);
      if ($request) {
        $request->delete();
      }
    }

    // Do not create service trackers if payment type is wiretransfer.
    if ($order->get('payment_gateway')->first()) {
      if ($order->get('payment_gateway')->first()->entity->id() === 'wire_transfer') {
        $order_state = $order->getState();
        $order_state_transitions = $order_state->getTransitions();
        $order_state->applyTransition($order_state_transitions['pending']);

        $order->save();

        $logStorage = \Drupal::entityTypeManager()->getStorage('commerce_log');
        $logStorage->generate(
          $order,
          'commerce_order_admin_comment',
          ['comment' => 'System: Order Status marked as pending as its wiretrasfer payment']
        )->save();

        return;
      }
    }

    $logStorage = \Drupal::entityTypeManager()->getStorage('commerce_log');
    $logStorage->generate(
      $order,
      'commerce_order_admin_comment',
      ['comment' => 'System: Order Completed']
    )->save();

    $checkout_helper = \Drupal::service('ige_checkout.checkout_helper');
    $checkout_helper->addTracker($order);
  }

}
