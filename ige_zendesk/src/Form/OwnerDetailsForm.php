<?php

namespace Drupal\ige_zendesk\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\profile\Entity\Profile;

class OwnerDetailsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ige_zendesk_reassign_details_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $target_user = $end_user = NULL;
    $form['target_user'] = [
      '#title' => $this->t('Select user to reassign'),
      '#type' => 'entity_autocomplete',
      '#required' => TRUE,
      '#target_type' => 'user',
      '#default_value' => $target_user,
      '#selection_handler' => 'default:user',
      '#selection_settings' => [
        'include_anonymous' => FALSE,
      ],
    ];
    $form['end_user'] = [
      '#title' => $this->t('Select End User'),
      '#type' => 'entity_autocomplete',
      '#required' => TRUE,
      '#target_type' => 'user',
      '#default_value' => $end_user,
      '#selection_handler' => 'default:user',
      '#selection_settings' => [
        'include_anonymous' => FALSE,
      ],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reassign'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $target_user = $form_state->getValue('target_user');
    $end_user = $form_state->getValue('end_user');
    $order_count = $tracker_count = $request_count = 0;
    //load all orders of target user
    $orders = \Drupal::entityTypeManager()
       ->getStorage('commerce_order')
       ->loadByProperties(['uid' => $target_user]);
    foreach($orders as $order) {
      $order->set('uid', $end_user);
      $order->save();
      $order_count++;
    }
    //load all trackers of target user
    $trackers = \Drupal::entityTypeManager()
      ->getStorage('service_tracker')
      ->loadByProperties(['field_customer' => $target_user]);
    foreach($trackers as $tracker) {
      $tracker->set('field_customer', $end_user);
      $tracker->save();
      $tracker_count++;
    }
    //load all pending payments of target user
    $trademark_requests = \Drupal::entityTypeManager()
      ->getStorage('trademark_request')
      ->loadByProperties(['uid' => $target_user]);
    foreach($trademark_requests as $trademark_request) {
      $trademark_request->set('uid', $end_user);
      $trademark_request->save();
      $request_count++;
    }
    $this->messenger()->addStatus($this->t(' %order order(s), %tracker tracker(s) and %pending pending payemnt(s) were reassigned.', [
       '%order' => $order_count,
       '%tracker' => $tracker_count,
      '%pending' => $request_count,
    ]));
  }
}
