<?php
/**
 * @file
 * Contains \Drupal\demo\Form\Multistep\MultistepFormBase.
 */

namespace Drupal\ige_checkout\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ige_checkout\Helper;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Url;

class CartForm extends FormBase {

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
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ige_checkout_cart_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $trademark_request = NULL
  ) {
    $requests = [];
    $user = \Drupal::routeMatch()->getParameter('user');
    if (!$user) {
      $user = $this->currentUser;
    }
    $requests = $this->checkoutHelper->getTrademarkRequests(NULL, $user->id());

    $header = [
      'services' => $this->t('Pending Services'),
      'class' => $this->t('Classes'),
      'country' => $this->t('Country'),
      'total' => $this->t('Price'),
      'edit' => '',
      'delete' => '',
    ];

    $form['table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $requests,
      '#title' => t("Pending Services"),
      '#empty' => $this->t('No items in your cart'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Proceed to Payment'),
    ];

    if ($this->currentUser->id() == '0') {
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Please login to proceed'),
      ];
    }

    if (empty($requests)) {
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Place new order'),
      ];
    }

    $variables['page']['#attached']['library'][] = 'modal/modal';
    $variables['page']['#attached']['library'][] = 'core/drupal.ajax';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // If triggering element is place new order redirect to trademark checkout
    // page.
    if ($form_state->getTriggeringElement()['#value'] == "Place new order") {
      $url = Url::fromRoute(
        'ige_checkout.trademark_checkout'
      );
      $form_state->setRedirectUrl($url);
      return;
    }

    // Ask users to login if they are not already logged in.
    if ($this->currentUser->id() == '0') {
      $url = Url::fromRoute(
        'user.login'
      );
      $form_state->setRedirectUrl($url);
      return;
    }

    $order_items = $request_ids = [];
    $owner = NULL;

    $values = $form_state->getValues()['table'];

    // If no request is selected ask users to select one.
    $request = array_filter($values);
    if (empty($request)) {
      \Drupal::messenger()->addMessage(t('Please select a service to proceed.'), 'error');
      return;
    }
    $requests = $this
      ->entityManager
      ->getStorage('trademark_request')
      ->loadMultiple($request);

    foreach ($requests as $request) {
      $request_ids[] = $request->id();
      $owner = $request->field_owner->entity;
    }

    if (!$owner) {
      $url = Url::fromRoute(
        'ige_checkout.trademark_checkout_owner',
        [
          'trademark_request' => $request->id(),
          'request_ids' => $request_ids,
        ]
      );
      $form_state->setRedirectUrl($url);
      return;
    }

    $order = $this->checkoutHelper->createOrder($requests);

    $url = Url::fromRoute(
      'commerce_checkout.form',
      ['commerce_order' => $order->id()]
    );
    $form_state->setRedirectUrl($url);
  }

}

