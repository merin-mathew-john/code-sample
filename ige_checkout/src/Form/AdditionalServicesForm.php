<?php

namespace Drupal\ige_checkout\Form;

use Drupal\ige_checkout\Helper;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\EntityManagerInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides trademark checkout form.
 */
class AdditionalServicesForm extends FormBase {

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
    EntityManagerInterface $entity_manager,
    AccountProxyInterface $currentUser
  ) {
    $this->checkoutHelper = $checkout_helper;
    $this->entityManager = $entity_manager;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ige_checkout.checkout_helper'),
      $container->get('entity.manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'additional_services_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $id = NULL,
    $order_id = NULL
  ) {

    $form['header'] = [
      '#type' => 'markup',
      '#markup' => '<div class = "payment-progress-bar">
        <ul>
        <li class="active">
          <span class="text"> ' . $this->t('Add Additional service') . '
        </li>
        </ul>
      </div>',
    ];

    $form['user'] = [
      '#type' => 'select',
      '#title' => $this->t('User'),
      '#options' => $this->checkoutHelper->getUsers(),
      '#default_value' => $this->currentUser()->id(),
    ];

    // Services wrapper.
    $form['service'] = [
      '#type' => 'details',
      '#title' => $this->t('Services selection'),
      '#open' => TRUE,
    ];

    $form['service']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Service Title'),
      '#required' => TRUE,
    ];

    // Country wrapper.
    $mapper = \Drupal::service('flags.mapping.country');
    $options = $this->checkoutHelper->getProducts();
    $form['country'] = [
      '#type' => 'select',
      '#title' => $this->t('Country'),
      '#multiple' => TRUE,
      '#prefix' => '<div class="country-selction">' . $this->t('Select the countries in which you want to register your trademark') . '</div>',
      '#options' => $options,
      '#options_attributes' => $mapper->getOptionAttributes(
          array_keys($options)
      ),
      '#attached' => ['library' => ['flags/flags', 'select2boxes/widget']],
      '#attributes' => [
        'class' => ['trademark-countries'],
      ],
    ];
    foreach (array_keys($options) as $key) {
      $flags[$key] = [
        'flag',
        'flag-' . $mapper->map($key),
        $mapper->getExtraClasses()[0],
      ];
    }
    $form['country']['#attached']['drupalSettings']['flagsClasses'] = [];
    $form['country']['#attached']['drupalSettings']['flagsClasses'] += $flags;
    $form['country']['#attached']['drupalSettings']['flagsFields']['country'] = TRUE;
    $form['country']['#attached']['library'][] = 'flags/flags';

    if (!empty($products)) {
      $form['country_wrap']['country']['#default_value'] = array_keys($this->checkoutHelper->getProducts($products));
    }

    $form['service_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Service Type'),
      '#required' => TRUE,
      '#options' => [
        'additional_service' => $this->t('Trademark Additional Service'),
        'industrial_design' => $this->t('Industrial Design'),
        'patent' => $this->t('Patent'),
        'copyright' => $this->t('Copyright'),
        'domain_name_dispute' => $this->t('Domain Name Dispute'),
      ],
    ];

    $form['date'] = [
      '#type' => 'date',
      '#title' => $this->t('Deadline'),
    ];

    $form['attorney'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Attorney in charge'),
      '#target_type' => 'user',
      '#selection_settings' => [
        'include_anonymous' => FALSE,
        'filter' => [
          'role' => ['provider'],
        ],
      ],
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
    ];

    $form['price'] = [
      '#type' => 'commerce_price',
      '#title' => $this->t('Price'),
      '#default_value' => [
        'number' => 0,
        'currency_code' => $this->entityManager->getStorage('commerce_store')->loadDefault()->getDefaultCurrencyCode(),
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
    ];

    return $form;
  }

  /**
   * Saves the data from the multistep form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $products = $values['country'];
    $products = $this->checkoutHelper->getProduct($products);
    $request = \Drupal::entityTypeManager()
      ->getStorage('trademark_request')
      ->create(
        [
          'type' => 'trademark_request',
          'title' => $values['title'],
          'field_product' => $products,
          'field_additional_service' => TRUE,
          'field_admin_added' => TRUE,
          'field_description' => $values['description'],
          'field_dead_line' => $values['date'],
          'field_attorney' => $values['attorney'],
          'field_service_type' => $values['service_type'],
          'field_trademark_type' => $values['service_type'],
          'field_trademark_text' => $values['title'],
          'field_price' => $values['price'],
          'author' => $values['user'],
          'uid' => $values['user'],
        ]
    );
    $request->save();

    $order = $this->checkoutHelper->createOrder([$request]);

    // Redirect to checkout.
    $url = Url::fromRoute(
      'entity.commerce_order.edit_form',
      ['commerce_order' => $order->id()]
    );
    $form_state->setRedirectUrl($url);
  }

}
