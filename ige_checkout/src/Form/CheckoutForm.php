<?php

namespace Drupal\ige_checkout\Form;

use Drupal\ige_checkout\Helper;
use Drupal\paragraphs\Entity\Paragraph;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Component\Utility\Unicode;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides trademark checkout form.
 */
class CheckoutForm extends FormBase {

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
    return 'trademark_form';
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
    $default_classes = [];
    $image = NULL;

    $storage = [
      'id' => $id,
      'order_id' => $order_id,
    ];

    if ($id) {
      $request = $this
        ->entityManager
        ->getStorage('trademark_request')
        ->load($id);
      foreach ($request->get('field_trademark_type')->getValue() as $type) {
        $trademark_type[$type['value']] = $type['value'];
      }
      foreach ($request->get('field_product')->getValue() as $product) {
        $products[$product['target_id']] = $product['target_id'];
      }
      $trademark_text = $request->field_trademark_text->value;
      $trademark_logo = $request->field_image->entity;
      if ($trademark_logo) {
        $image = $trademark_logo->id();
      }

      foreach ($request->get('field_class_data')->getValue() as $class) {
        $class_data = Paragraph::load($class['target_id']);
        $class_id = $class_data->field_class_name->value;
        $default_classes[$class_id] = $class_id;
        $default_classes_desc[$class_id] = $class_data->field_class_description->value;
      }

      // Services wrapper.
      $form['edit-operation'] = [
        '#type' => 'markup',
        '#markup' => '<div id="edit-trademark-request"></div>',
      ];
    }

    $form_state->setStorage($storage);

    $form['header'] = [
      '#type' => 'markup',
      '#markup' => '<div class = "payment-progress-bar">
        <ul>
        <li class="active">
          <span class="text"> ' . $this->t('Place your order') . '
        </li>
        <li>
          <span class="text"> ' . $this->t('Owner information') . '
        </li>
        <li>
          <span class="text">' . $this->t('Shopping cart & payment') . '
        </li>
        </ul>
      </div>',
    ];

    if ($this->currentUser()->hasPermission('access commerce administration pages')) {
      $form['user'] = [
        '#type' => 'select',
        '#title' => $this->t('User'),
        '#options' => $this->checkoutHelper->getUsers(),
        '#default_value' => $this->currentUser()->id(),
        '#prefix' => '<div class = "trademark-checkout-admin">',
      ];

      $target_currency = \Drupal::request()->cookies->get('commerce_currency');
      if (empty($target_currency)) {
        $target_currency = 'USD';
      }

      $form['discount'] = [
        '#type' => 'commerce_price',
        '#title' => $this->t('Discount'),
        '#suffix' => '</div>',
        '#default_value' => [
          'number' => 0,
          'currency_code' => $target_currency,
        ],
        '#description' => t('Currency should be same as the selected currency in header'),
      ];
    }

    // Services wrapper.
    $form['service'] = [
      '#type' => 'details',
      '#title' => $this->t('Services selection'),
      '#open' => TRUE,
    ];

    $form['service']['type'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select the services you want to order'),
      '#options' => [
        'search' => '<div class = "checkout-form-service">'
        . $this->t('Trademark search')
        . '<div>' . $this->t('Determine the probabilities your trademark has of being successfully registered. This service is optional.')
        . '</div></div>',
        'application' => '<div class = "checkout-form-service">'
        . $this->t('Trademark application')
        . '<div>' . $this->t('Apply to have your trademark registered.')
        . '</div></div>',
      ],
      '#attributes' => [
        'class' => ['trademark-type'],
      ],
    ];

    if (!empty($trademark_type)) {
      $form['service']['type']['#default_value'] = $trademark_type;
    }

    if (!empty($_GET['service'])) {
      $form['service']['type']['#default_value'] = [$_GET['service']];
    }
    // Country wrapper.
    $mapper = \Drupal::service('flags.mapping.country');
    $options = $this->checkoutHelper->getProducts();
    $form['country_wrap'] = [
      '#type' => 'details',
      '#title' => $this->t('Country selection'),
      '#open' => TRUE,
    ];
    $form['country_wrap']['country'] = [
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

    if (!empty($_GET['country'])) {
      $form['country_wrap']['country']['#default_value'] = $_GET['country'];
      if (!is_array($_GET['country'])) {
        $form['country_wrap']['country']['#default_value'] = [$_GET['country']];
      }
    }
    // Trademark informations wrapper.
    $form['trademark_information'] = [
      '#type' => 'details',
      '#title' => $this->t('Trademark Information'),
      '#open' => TRUE,
    ];
    $form['trademark_information']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Enter your trademark name'),
      '#required' => TRUE,
    ];
    if (!empty($trademark_text)) {
      $form['trademark_information']['name']['#default_value'] = $trademark_text;
    }
    $form['trademark_information']['logo'] = [
      '#type' => 'managed_file',
      '#upload_location' => 'public://trademark/images/',
      '#multiple' => FALSE,
      '#description' => t('Accepted formats: gif png jpg jpeg'),
      '#title' => $this->t('Upload logo (optional)'),
      '#attributes' => [
        'class' => ['wizard-logo'],
      ],
      '#upload_validators' => [
        'file_validate_extensions' => ['gif png jpg jpeg'],
      ],
      '#theme' => 'image_widget',
      '#preview_image_style' => 'medium',
      '#default_value' => [$image],
    ];

    // Trademark class wrapper.
    $form['trademark_information']['class_wrap'] = [
      '#type' => 'markup',
      '#markup' => '<div class = "class-wrap"><div class="class-wrap-title">'
      . $this->t('Would you prefer to choose your class now or skip this step?')
      . '</div><div class = "alert-info"> <p>'
      . $this->t('If you select “Choose later”, you will be charged for one class; once your order is completed, a Consultant will contact you to help you select the class(es) and inform you of the final price if additional classes are required.') . '</p>'
      . '</div>
      </div>',
      '#open' => TRUE,
    ];

    $form['class_select'] = [
      '#type' => 'button',
      '#value' => $this->t('Choose Now'),
    ];
    $form['class_select_later'] = [
      '#type' => 'button',
      '#value' => $this->t('Choose Later'),
    ];

    $form['class_selection_popup'] = [
      '#type' => 'details',
      '#title' => $this->t('Class Selection'),
      '#open' => TRUE,
      '#attributes' => [
        'class' => ['wizard-class-selection-popup'],
      ],
    ];

    $form['class_selection_popup']['search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search'),
      '#attributes' => [
        'class' => ['wizard-class-popup-search-keyword'],
      ],
      '#title_display' => FALSE,
      '#prefix' => '<div class = "class-search"><div class = "class-selection-popup-prefix"><h3>'
      . t('Select the class(es) in which you want to register your trademark.')
      . '<br>' . t('You can use the search box to type in, one at a time, a product or service to determine which class it belongs to')
      . '</h3></div><div><div class="popup-help">'
      . t('*Please remember you can skip this step and select the class(es) with the help of our consultants once the order is completed')
      . '</div></div> <i class="fa fa-question-circle" aria-hidden="true"></i>',
    ];
    $form['class_selection_popup']['button'] = [
      '#type' => 'button',
      '#value' => $this->t('Search'),
      '#attributes' => [
        'class' => ['wizard-class-popup-search'],
      ],
      '#suffix' => '<a class = "wizard-class-popup-search-clear" href = "/ajax/class/search">' . t('Clear results') . '</a></div>',
    ];

    $class = $this->getClasses();
    $form['class_selection_popup']['class'] = [
      '#type' => 'checkboxes',
      '#options' => $class['classes_popup'],
      '#attributes' => [
        'class' => ['trademark-class-select'],
      ],
    ];

    if (!empty($default_classes)) {
      $form['class_selection_popup']['class']['#default_value'] = $default_classes;
    }

    $form['class_selection_popup']['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
      '#prefix' => '<div class = "class_selection_popup_button am">',
      '#attributes' => [
        'class' => ['wizard-class-popup-cancel'],
      ]
    ];

    $form['class_selection_popup']['add'] = [
      '#type' => 'button',
      '#value' => $this->t('Add Classes'),
      '#suffix' => '</div>',
      '#attributes' => [
        'class' => ['wizard-class-popup-choose'],
      ]
    ];

    $form['class_select'] = [
      '#type' => 'button',
      '#value' => $this->t('Choose Now'),
    ];

    $form['selected_class'] = [
      '#type' => 'details',
      '#title' => $this->t('Class Selection'),
      '#open' => TRUE,
      '#attributes' => [
        'class' => ['wizard-selected-class'],
      ]
    ];

    foreach ($class['classes'] as $key => $item) {
      $remove_class = 'remove-' . $key;
      $class = NULL;
      if (in_array($key, $default_classes)) {
        $form['selected_class'][$key]['#default_value'] = $default_classes_desc[$key];
        $class = "visible";
        $form['selected_class']['#attributes']['class'] = ['wizard-selected-class visible'];
      }
      $form['selected_class'][$key] = [
        '#type' => 'textfield',
        '#title' => $item,
        '#tree' => TRUE,
        '#attributes' => [
          'class' => [$key, $class],
          'data-class' => $key,
        ],
        '#maxlength' => 3000,
        '#prefix' => '<div class = "selected-class-items selected-class-' . $key . ' ' . $class . '">',
        '#suffix' => '<a href = "#" data-id = "' . $key . ' " id = "' . $remove_class . '" class = "remove-class">' . t('Remove') . '</a></div>',
      ];
      if (in_array($key, $default_classes)) {
        $form['selected_class'][$key]['#default_value'] = $default_classes_desc[$key];
      }
    }

    $form['summary'] = [
      '#type' => 'markup',
      '#markup' => '<div class = "trademark-wizard-summary"></div>
      ',
    ];

    $form['class_data'] = [
      '#type' => 'textfield',
      '#title' => 'selected_class',
      '#title_display' => FALSE,
      '#attributes' => [
        'class' => ['hidden'],
        'id' => 'selected_class_data',
      ],
    ];

    $form['base_url'] = [
      '#type' => 'hidden',
      '#value' => \Drupal::request()->getSchemeAndHttpHost(),
      '#attributes' => [
        'class' => ['hidden'],
        'id' => 'base_url',
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add to cart'),
    ];

    $form['#attached']['library'][] = 'ige_checkout/trademark';
    $form['#attached']['drupalSettings']['base_url'] = \Drupal::request()->getSchemeAndHttpHost();
    $form['#tree'] = TRUE;

    return $form;
  }

  /**
   * Saves the data from the multistep form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $vals = $form_state->getStorage();
    $selected_class['1'] = NULL;
    $user = $this->currentUser()->id();
    $discount = NULL;

    $values = $form_state->getValues();
    if (isset($values['user'])) {
      $user = $values['user'];
    }
    if (isset($values['discount'])) {
      $discount = $values['discount'];
    }
    $selected_class_data = $values['class_data'];
    if ($selected_class_data) {
      $selected_class = [];
      $selected_classes = explode(",", $selected_class_data);
      foreach ($selected_classes as $class) {
        $selected_class[$class] = $values['selected_class'][$class];
      }
    }
    $products = $values['country_wrap']['country'];
    $products = $this->checkoutHelper->getProduct($products);

    if (!empty($vals['id'])) {
      $trademark_request = $this->checkoutHelper->updateTrademarkRequest(
        $vals['id'],
        $products,
        array_filter($values['service']['type']),
        $values['trademark_information']['logo'],
        $values['trademark_information']['name'],
        $selected_class,
        $discount
      );

      $url = Url::fromRoute(
        'ige_checkout.trademark_checkout_owner',
        ['trademark_request' => $trademark_request->id()]
      );
      $form_state->setRedirectUrl($url);

      return;
    }
    $trademark_request = $this->checkoutHelper->createTrademarkRequest(
      $products,
      array_filter($values['service']['type']),
      $values['trademark_information']['logo'],
      $values['trademark_information']['name'],
      $selected_class,
      $user,
      $discount
    );

    $url = Url::fromRoute(
      'ige_checkout.trademark_checkout_owner',
      ['trademark_request' => $trademark_request->id()]
    );
    $form_state->setRedirectUrl($url);
  }

  /**
   * Gathers the list of countries.
   *
   * @return array
   *   List of country codes.
   */
  public function getClasses() {
    $output = ['classes_popup' => [], 'classes' => []];
    $vid = 'class';
    $lang = \Drupal::languageManager()->getCurrentLanguage()->getId();

    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', $vid);
    $entity_ids = $query->execute();

    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
      ->loadMultiple($entity_ids);

    foreach ($terms as $term) {
      if ($term->hasTranslation($lang)) {
        $term = $term->getTranslation($lang);
      }

      $tid = $term->id();
      $class_number = $term->getName();
      $term_name = t('Class') . ' ' . $class_number;
      $desc = strip_tags($term->getDescription());

      if (strlen($desc) > 80) {
        $term_desc = Unicode::substr($desc, 0, 80) . '<span class = "class-popup-readmore-dot" id="dots-' . $tid . '">...</span>';
        $term_desc_1 = '<span class = "class-popup-more-text" id="more-' . $tid . '">' . Unicode::substr($desc, 100) . '</span>';
      }
      else {
        $term_desc = $desc;
        $term_desc_1 = NULL;
      }

      $output['classes_popup'][$class_number] = '<div><b>' . $term_name . ': </b><span class = "class-desc">' .
        $term_desc . ' ' . $term_desc_1 . '</span>
        <a data-tid = "' . $tid . '" id="class-popup-more-button-' . $tid . '" class = "readmore-class class-desc" >' . t('Read more') . '</a></div>';

      $output['classes'][$class_number] = $term_name;
    }
    return $output;
  }

}
