<?php

namespace Drupal\ige_checkout\Form;

use Drupal\ige_checkout\Helper;

use Drupal\Core\Url;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityManagerInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides trademark checkout form.
 */
class CountrySearchForm extends FormBase {

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
    return 'country_search_form';
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
    $form['country'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'commerce_product',
      '#attributes' => [
        'class' => ['search-country-home'],
      ],
      '#title' => $this->t('Country'),
      '#title_display' => FALSE,
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Go!'),
      '#attributes' => [
        'class' => ['search-country-home-submit'],
      ],
    ];

    return $form;
  }

  /**
   * Saves the data from the multistep form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $country = $form_state->getValue('country');

    // Redirect to product page.
    $link = Url::fromRoute(
      'entity.commerce_product.canonical',
      ['commerce_product' => $country]
    );
    $form_state->setRedirectUrl($link);
  }

}
