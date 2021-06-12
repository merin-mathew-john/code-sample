<?php

namespace Drupal\ige_checkout\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ige_checkout\Helper;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Url;

class PendingPaymentsFrom extends FormBase {

  /**
   * The checkout helper class.
   *
   * @var \Drupal\ige_checkout\Helper
   */
  protected $checkoutHelper;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * Constructor.
   */
  public function __construct(
    Helper $checkout_helper,
    AccountProxyInterface $currentUser,
    EntityManagerInterface $entity_manager
  ) {
    $this->checkoutHelper = $checkout_helper;
    $this->currentUser = $currentUser;
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ige_checkout.checkout_helper'),
      $container->get('current_user'),
      $container->get('entity.manager')
    );
  }

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'trademark_pending_payments_form';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $trademark_request = NULL
  ) {

    $requests = $this->checkoutHelper->getTrademarkRequests();

    $form['header'] = [
      '#type' => 'markup',
      '#markup' => '<div class = "payment-progress-bar">
        <ul>
        <li class="active completed">
         <span class="text"> '  . $this->t('Place your order') . '
        </li>
        <li class="active completed">
         <span class="text"> ' . $this->t('Owner information') . '
        </li>
        <li class="active">
         <span class = "text">' . $this->t('Shopping cart & Payment') . '
        </li>
        </ul>
      </div>',
    ];

    $header = [
      'services' => $this->t('Current Order'),
      'class' => $this->t('Classes'),
      'country' => $this->t('Country'),
      'price' => $this->t('Price'),
    ];

    $form['table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $requests,
      '#empty' => $this->t('No items in your cart'),
    ];

    $form['footer'] = [
      '#type' => 'markup',
      '#markup' => '<div class = "cart-total">'
        . $this->t('Total in') . ' ' . ige_cart_get_currency() .
        '</div>
        <div class = "total">
          $1,500
        </div>',
    ];

    $form['footer_text'] = [
      '#type' => 'markup',
      '#markup' => '<div>' . $this->t('If any documentation or additional information is
        required to proceed with any service, we will contact you once you have
        placed your order'
      ) . '</div>',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Proceed to Payment'),
    ];
    return $form;
  }

  /**
   * Saves the data from the multistep form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues()['table'];
    $request = array_filter($values);
    $requests = $this
      ->entityManager
      ->getStorage('trademark_request')
      ->loadMultiple($request);

    $order = $this->checkoutHelper->createOrder($requests);

    $url = Url::fromRoute(
      'commerce_checkout.form',
      ['commerce_order' => $order->id()]
    );
    $form_state->setRedirectUrl($url);
  }

}

