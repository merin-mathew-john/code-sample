<?php

namespace Drupal\ige_checkout\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ige_checkout\Helper;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\profile\Entity\Profile;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

use CommerceGuys\Addressing\AddressFormat\AddressField;
use CommerceGuys\Addressing\AddressFormat\FieldOverride;

class CheckoutOwnerForm extends FormBase {

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
    return 'trademark_owner_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $trademark_request = NULL
  ) {
    $uid = \Drupal::currentUser()->id();
    if ($uid == '0') {
      return $form;
    }

    $trademark = $this->entityManager
      ->getStorage('trademark_request')
      ->load($trademark_request);
    if ($trademark) {
      $uid = $trademark->getOwnerId();
    }

    $form['header'] = [
      '#type' => 'markup',
      '#markup' => '<div class = "payment-progress-bar">
        <ul>
        <li class="active completed">
         <span class = "text"> ' . $this->t('Place your order') . '
        </li>
        <li class="active">
         <span class = "text"> ' . $this->t('Owner information') . '
        </li>
        <li>
         <span class = "text">' . $this->t('Shopping cart & Payment') . '
        </li>
        </ul>
      </div>',
    ];

    $form_state->set('trademark_request', $trademark_request);

    $form['trademark_owner']['type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Owner Information'),
      '#options' => [
        'existing_owner' => $this->t('Select Existing Owner'),
        'new_owner' => $this->t('New Owner'),
      ],
      '#default_value' => 'existing_owner',
    ];

    $customer_profiles = $this->checkoutHelper->getCustomerProfiles($uid);
    $form['trademark_owner']['existing_owner_help'] = [
      '#type' => 'details',
      '#title' => t('Trademark Owner'),
      '#open' => TRUE,
      '#title_display' => 'invisible',
      '#markup' => '
        <div class = "address-book-helptext">'
        . '<p>' . $this->t('Select <strong>ONE</strong> of the following owners') . '</p>'
        . '<p>' . $this->t('You may edit your owner profiles in your address book') . '</p>'
        . '</div>',
      '#states' => [
        'visible' => [
          ':input[name="type"]' => [
            'value' => 'existing_owner',
          ],
        ],
      ],
    ];

    $form['trademark_owner']['existing_owner'] = [
      '#type' => 'radios',
      '#title' => $this->t('Existing Profile'),
      '#title_display' => 'invisible',
      '#options' => $this->checkoutHelper->getCustomerProfiles($uid),
      '#states' => [
        'visible' => [
          ':input[name="type"]' => [
            'value' => 'existing_owner',
          ],
        ],
      ],
    ];

    if (empty($customer_profiles)) {
      $form['trademark_owner']['existing_owner_help']['#markup'] = '<div class = "address-book-helptext">'
      . '<p>' . $this->t('There are no existing addresses associated to your account. Please click on “New Owner” to enter the owner details.') . '</p>'
      . '</div>';
    }

    $form['trademark_owner']['new_owner'] = [
      '#type' => 'details',
      '#attributes' => ['id' => 'checkout-new-owner'],
      '#open' => TRUE,
      '#title_display' => FALSE,
      '#title' => $this->t('New owner'),
      '#collapsible' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="type"]' => [
            'value' => 'new_owner',
          ],
        ],
      ],
    ];
    $form['trademark_owner']['new_owner']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title/Reference'),
      '#description' => $this->t('Choose a reference for this profile'),
      '#states' => [
        'visible' => [
          ':input[name="type"]' => [
            'value' => 'new_owner',
          ],
        ],
      ],
    ];
    $form['trademark_owner']['new_owner']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Complete Name'),
      '#states' => [
        'visible' => [
          ':input[name="type"]' => [
            'value' => 'new_owner',
          ],
        ],
      ],
    ];
    $form['trademark_owner']['new_owner']['tax_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tax ID Number'),
      '#states' => [
        'visible' => [
          ':input[name="type"]' => [
            'value' => 'new_owner',
          ],
        ],
      ],
    ];
    $form['trademark_owner']['new_owner']['address'] = [
      '#type' => 'address',
      '#title' => $this->t('New Owner'),
      '#validated' => TRUE,
      '#default_value' => [
        'country_code' => 'US',
        'administrative_area' => 'CA',
      ],
      '#field_overrides' => [
        AddressField::ORGANIZATION => FieldOverride::HIDDEN,
        AddressField::ADDRESS_LINE2 => FieldOverride::HIDDEN,
        AddressField::GIVEN_NAME => FieldOverride::HIDDEN,
        AddressField::FAMILY_NAME => FieldOverride::HIDDEN,
        AddressField::POSTAL_CODE => FieldOverride::OPTIONAL,
        AddressField::ADDITIONAL_NAME => FieldOverride::OPTIONAL,
        AddressField::ADDRESS_LINE1 => FieldOverride::OPTIONAL,
        AddressField::LOCALITY => FieldOverride::OPTIONAL,
      ],
      '#states' => [
        'visible' => [
          ':input[name="type"]' => [
            'value' => 'new_owner',
          ],
        ],
      ],
    ];

    $form['trademark_owner']['new_owner']['tax_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tax ID number'),
      '#states' => [
        'visible' => [
          ':input[name="type"]' => [
            'value' => 'new_owner',
          ],
        ],
      ],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Continue'),
      '#button_type' => 'primary',
      '#weight' => 10,
    ];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $profile_id = $values['existing_owner'];
    if ($values['type'] === 'new_owner') {
      if (empty($values['name'])) {
        $form_state->setErrorByName('name', $this->t('Please enter a name for the owner profile'));
      }
      if (empty($values['title'])) {
        $form_state->setErrorByName('title', $this->t('Please enter a valid reference for the owner profile'));
      }
      if ($this->checkoutHelper->getCustomerProfilesByTitle(NULL, $values['title'])) {
        $form_state->setErrorByName('title', $this->t('Please enter a unique title/reference value'));
      }
    }
    else {
      if (empty($values['existing_owner'])) {
        $form_state->setErrorByName('existing_owner', $this->t('Please select an existing owner profile'));
      }
    }
  }

  /**
   * Saves the data from the multistep form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $trademark_request_array = [];

    $request_ids[$form_state->get('trademark_request')] = $form_state->get('trademark_request');
    $values = $form_state->getValues();
    $profile_id = $values['existing_owner'];
    $name_array = explode(" ", $values['name']);
    $values['address']['given_name'] = $name_array['0'];
    if (isset($name_array['1'])) {
      unset($name_array['0']);
      $values['address']['family_name'] = implode(" ", $name_array);
    }

    if (isset($_GET['request_ids'])) {
      foreach ($_GET['request_ids'] as $id) {
        $request_ids[$id] = $id;
      }
    }
    $trademark_request = $this->entityManager->getStorage('trademark_request')
      ->load($form_state->get('trademark_request'));

    if ($values['type'] === 'new_owner') {
      $profile_storage = \Drupal::entityTypeManager()->getStorage('profile');
      $profile = $profile_storage->create([
        'type' => 'customer',
        'uid' => $trademark_request->getOwnerId(),
        'field_user' => $trademark_request->getOwnerId(),
        'address' => $values['address'],
        'status' => TRUE,
        'field_title' => $values['title'],
        'field_name' => $values['name'],
        'field_tax_id' => $values['tax_id'],
      ]);
      $profile->setDefault(TRUE);
      $profile->setActive(TRUE);
      $profile->setData('copy_to_address_book', TRUE);
      $profile->save();
      $profile_id = $profile->id();
    }

    foreach ($request_ids as $request_id) {
      $trademark_request = $this->entityManager->getStorage('trademark_request')
        ->load($request_id);

      // Set the owner profile in Order entity.
      $trademark_request->field_owner->target_id = $profile_id;
      $trademark_request->save();
      $trademark_request_array[] = $trademark_request;
    }

    $order = $this->checkoutHelper->createOrder($trademark_request_array);

    if ($trademark_request->getOwnerId() != \Drupal::currentUser()->id()) {
      // Redirect to checkout.
      $url = Url::fromRoute(
        'entity.commerce_order.edit_form',
        ['commerce_order' => $order->id()]
      );
      $form_state->setRedirectUrl($url);
    }
    else {
      // Redirect to checkout.
      $url = Url::fromRoute(
        'commerce_checkout.form',
        ['commerce_order' => $order->id()]
      );
      $form_state->setRedirectUrl($url);
    }
  }

}
