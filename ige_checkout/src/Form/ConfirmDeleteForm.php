<?php

namespace Drupal\ige_checkout\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\commerce_order\Entity\Order;

/**
 * Defines a confirmation form to confirm deletion of trademark request by id.
 */
class ConfirmDeleteForm extends ConfirmFormBase {

  /**
   * ID of the item to delete.
   *
   * @var int
   */
  protected $id;

  /**
   * Order id to delete.
   *
   * @var int
   */
  protected $orderId;

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $id = NULL,
    $order_id = NULL
  ) {
    $this->id = $id;
    $this->orderId = $order_id;

    $request = \Drupal::entityTypeManager()
      ->getStorage('trademark_request')
      ->load($this->id);
    $this->request = $request;

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($this->request) {
      $this->request->delete();
    }

    drupal_set_message(t('The service has been successfully deleted.'));

    if (!$this->orderId) {
      $form_state->setRedirect('ige_checkout.cart',
        ['user' => \Drupal::currentUser()->id()]);
      return;
    }

    $commerce_order = Order::load($this->orderId);
    $pending_requests = $commerce_order->field_trademark_request->getValue();
    foreach ($pending_requests as $request) {
      $request = \Drupal::entityTypeManager()
        ->getStorage('trademark_request')
        ->load($request['target_id']);

      if (!empty($request)) {
        $form_state->setRedirect('commerce_checkout.form',
          ['commerce_order' => $this->orderId]);
        return;
      }
    }

    // Redirect to pending payments page if no trademark request associated
    // with the order.
    $form_state->setRedirect('ige_checkout.cart',
      ['user' => \Drupal::currentUser()->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() : string {
    return "confirm_delete_form";
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    if ($this->orderId) {
      return new Url('commerce_checkout.form', ['commerce_order' => $this->orderId]);
    }

    return new Url('ige_checkout.cart', ['user' => \Drupal::currentUser()->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $name = $this->request->field_trademark_type->value . " " . $this->request->field_trademark_text->value;
    return t('Are you sure you wish to delete %name permanently from your shopping cart? This action cannot be undone.', ['%name' => $name]);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $name = $this->request->field_trademark_type->value . " " . $this->request->field_trademark_text->value;
    return t('Are you sure you wish to delete %name permanently from your shopping cart? This action cannot be undone.', ['%name' => $name]);
  }

}
