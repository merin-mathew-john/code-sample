<?php

namespace Drupal\ige_checkout;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_price\Price;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\profile\Entity\Profile;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;

use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Component\Serialization\Json;

/**
 * Checkout helper class. Responsible for order related operations.
 */
class Helper {

  /**
   * Create Tracker queue worker.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $currentUser,
    QueueFactory $queue_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $currentUser;
    $this->queue = $queue_factory->get('ige_checkout_create_tracker');
  }

  /**
   * Returns the product variation based on the product and trademark type.
   *
   * If trademark type is selected as search and if a logo is uploaded, then
   * take the combined variation, if logo is not provided then use wordmark
   * variaton.
   *
   * @param array $products
   *   Array of products whose variations are to be fetched.
   * @param array $trademark_types
   *   The array of trademark types.
   * @param string $logo
   *   The logo file path.
   */
  public function getProductVariations(
    array $products,
    array $trademark_types,
    $logo
  ) {
    $output = [];

    $products = $this
      ->entityTypeManager
      ->getStorage('commerce_product')
      ->loadMultiple($products);

    // Loop through each products to get the product variations.
    foreach ($products as $product) {
      $product_variations = $product->variations->getValue();

      // Loop through each product variations.
      foreach ($product_variations as $variation) {
        $product_variation = $this->entityTypeManager
          ->getStorage('commerce_product_variation')
          ->load($variation['target_id']);

        // Get trademark type and trademark service attribute names.
        $trademark_type = $product_variation
          ->get('attribute_trademark_type')->entity->getName();
        $trademark_service = $product_variation
          ->get('attribute_trademark_service')->entity->getName();

        $trademark_service = strtolower($trademark_service);

        // Proceed only if the selected trademark service is available
        // in product.
        if (!isset($trademark_types[$trademark_service])) {
          continue;
        }

        if ($trademark_service != "search") {
          $output[$product_variation->id()] = $product_variation;
          continue;
        }

        if (!$logo && strtolower($trademark_type) === 'wordmark') {
          $output[$product_variation->id()] = $product_variation;
          continue;
        }

        if (strtolower($trademark_type) === 'combined') {
          $output[$product_variation->id()] = $product_variation;
          continue;
        }
      }
    }

    return $output;
  }

  /**
   * Updates a trademark request entity.
   *
   * @param string $id
   *   The id of the entity being updated.
   * @param array $products
   *   The products associated with the request.
   * @param array $trademark_types
   *   The trademark types associated with the request.
   * @param string $logo
   *   The image associated with the request.
   * @param string $name
   *   The trademark name.
   * @param array $class
   *   The trademark classes.
   * @param string $discount
   *  Discount associated with the request.
   */
  public function UpdateTrademarkRequest(
    $id,
    array $products,
    array $trademark_types,
    $logo,
    $name,
    array $class,
    $discount = NULL
  ) {
    $trademark_types = array_keys($trademark_types);

    $request = $this->entityTypeManager
      ->getStorage('trademark_request')
      ->load($id);

    $request->field_product = $products;
    $request->field_trademark_type = $trademark_types;
    $request->field_trademark_text = $name;
    $request->field_image = $logo;
    $request->field_class_data = [];
    if ($discount) {
      $request->field_discount = $discount;
    }
    $request->save();

    // Add class field collection.
    if (!empty($class)) {
      foreach ($class as $key => $class_text) {
        $class_field = Paragraph::create(
          [
            'type' => 'class_data',
            'field_class_name' => $key,
            'field_class_description' => $class_text,
          ]
        );
        $class_field->save();
        $request->field_class_data[] = $class_field;
      }
    }

    $request->save();

    return $request;
  }

  /**
   * Updates a trademark request entity.
   *
   * @param array $products
   *   The products associated with the request.
   * @param array $trademark_types
   *   The trademark types associated with the request.
   * @param string $logo
   *   The image associated with the request.
   * @param string $name
   *   The trademark name.
   * @param array $class
   *   The trademark classes.
   * @param string $user
   *  User associated with the request.
   * @param string $discount
   *  Discount associated with the request.
   */
  public function createTrademarkRequest(
    array $products,
    array $trademark_types,
    $logo,
    $name,
    array $class,
    $user = NULL,
    $discount = NULL
  ) {
    $title = 'Trademark Request';
    $trademark_types = array_keys($trademark_types);
    $uid = \Drupal::currentUser()->id();

    if ($user) {
      $uid = $user;
    }

    if (!$discount) {
      $discount = new Price(0, 'USD');
    }

    $request = $this->entityTypeManager
      ->getStorage('trademark_request')
      ->create(
        [
          'type' => 'trademark_request',
          'title' => $title,
          'field_product' => $products,
          'field_trademark_type' => $trademark_types,
          'field_additional_service' => FALSE,
          'field_admin_added' => FALSE,
          'field_trademark_text' => $name,
          'field_image' => $logo,
          'author' => $uid,
          'uid' => $uid,
          'field_discount' => $discount,
        ]
      );
    $request->save();
    if ($uid == '0') {
      // Store in tempstore.
      $tempstore = \Drupal::service('user.private_tempstore')->get('ige_checkout');
      $user_requests = $tempstore->get('trademark_requests');
      $user_requests[] = $request->id();
      $tempstore->set('trademark_requests', $user_requests);
    }

    // Add class field collection.
    if (!empty($class)) {
      foreach ($class as $key => $class_text) {
        $class_field = Paragraph::create(
          [
            'type' => 'class_data',
            'field_class_name' => $key,
            'field_class_description' => $class_text,
          ]
        );
        $class_field->save();
        $request->field_class_data[] = $class_field;
      }
      $request->save();
    }

    return $request;
  }

  /**
   * Creates an additional service Order Item.
   *
   * @param $request
   *   The request entity.
   */
  public function createAdditionalServiceOrderItem(
    $request
  ) {
    $product_ids = $request->field_product->getValue();
    foreach ($product_ids as $item) {
      $products[$item['target_id']] = $item['target_id'];
    }
    $data = $this->getAdditionalTrademarkRequestData([$request]);
    $order_item = OrderItem::create([
      'type' => 'default',
      'purchased_entity' => NULL,
      'quantity' => '1',
      'unit_price' => $data[$request->id()]['price_object'],
      'field_product' => $products,
      'field_trademark_request' => $request,
      'title' => $request->title->value,
      'field_admin_added' => $request->field_admin_added->value,
      'field_trademark_type' => $request->field_trademark_type->value,
      'field_owner' => $request->field_owner->entity,
    ]);
    $order_item->save();

    return $order_item;
  }

  /**
   * Creates an Order Item.
   *
   * @param $request
   *   The request entity.
   */
  public function createOrderItem(
    $request
  ) {
    $default_classes = $products = [];
    $data = $this->getTrademarkRequestData([$request]);
    $product_ids = $request->field_product->getValue();
    foreach ($product_ids as $item) {
      $products[$item['target_id']] = $item['target_id'];
    }

    $service = $request->field_trademark_type->getValue();
    $trademark_types = [];
    if (!empty($service)) {
      $trademark_types[$service['0']['value']] = $service['0']['value'];
    }
    if (!empty($service['1']['value'])) {
      $trademark_types[$service['1']['value']] = $service['1']['value'];
    }

    foreach ($request->get('field_class_data')->getValue() as $class) {
      $class_data = Paragraph::load($class['target_id']);
      $class_id = (int) trim($class_data->field_class_name->value);
      $default_classes[$class_id] = $class_data->field_class_description->value;
    }

    if (empty($default_classes)) {
      $default_classes['1'] = '1';
    }

    $trademark_types = array_keys($trademark_types);

    $order_item = OrderItem::create([
      'type' => 'default',
      'purchased_entity' => NULL,
      'quantity' => '1',
      'unit_price' => $data[$request->id()]['price_object'],
      'field_product' => $products,
      'field_trademark_text' => $request->field_trademark_text->getValue(),
      'field_image' => $request->field_image->getValue(),
      'field_trademark_type' => $trademark_types,
      'field_trademark_request' => $request,
      'field_owner' => $request->field_owner->entity,
      'title' => $request->field_trademark_text->getValue(),
    ]);
    $order_item->save();

    // Add class field collection.
    if (!empty($default_classes)) {
      foreach ($default_classes as $key => $class_text) {
        $class_field = Paragraph::create(
          [
            'type' => 'class_data',
            'field_class_name' => $key,
            'field_class_description' => $class_text,
          ]
        );
        $class_field->save();
        $order_item->field_class_data[] = $class_field;
      }

      $order_item->save();
    }

    return $order_item;
  }

  /**
   * Get customer profiles by id.
   *
   * @param string $uid
   *   The user id associated with the profile.
   * @param string $title
   *   The profile title.
   *
   * @return array
   *   Array of customer profiles with key as ID and HTML markup as value.
   */
  public function getCustomerProfilesByTitle(
    $uid = NULL,
    $title = NULL
  ) {
    if (!$uid) {
      $uid = $this->currentUser->id();
    }

    return $this->entityTypeManager
      ->getStorage('profile')->getQuery()
      ->condition('type', 'customer')
      ->condition('field_user.entity.uid', $uid)
      ->condition('field_title.value', $title)
      ->execute();
  }

  /**
   * Get all customer profiles.
   *
   * @return array
   *   Array of customer profiles with key as ID and HTML markup as value.
   */
  public function getCustomerProfiles($uid = NULL) {
    if (!$uid) {
      $uid = $this->currentUser->id();
    }

    $output = [];
    $profiles = $this->entityTypeManager
      ->getStorage('profile')->getQuery()
      ->condition('type', 'customer')
      ->condition('field_user.entity.uid', $uid)
      ->execute();

    foreach ($profiles as $profile_id) {
      $profile = Profile::load($profile_id);
      if (!$profile->address->getValue()) {
        continue;
      }

      $address = $profile->address->getValue()['0'];
      $name = $address['given_name'] . ' ' . $address['family_name'];
      unset($address['given_name']);
      unset($address['family_name']);
      unset($address['langcode']);
      if ($address) {
        $address = array_reverse($address);
        $country_code = $address['country_code'];
        $pin = $address['postal_code'];
        unset($address['postal_code']);
        $country = \Drupal::service('country_manager')->getList()[$country_code]->__toString();
        $address['country_code'] = $country;
        $address['postal_code'] = $pin;

        $address_string = implode(", ", array_filter($address));
        $output[$profile->id()] =
          '<div class = "address">
            <div class = "title">' . $profile->field_title->value . '</div>
            <div class = "address-item"><p>'
            . t('<strong>Name:</strong>') . ' ' . $name . '</p><p>'
            . t('<strong>Address:</strong>') . ' ' . $address_string
            . '</p></div>
          </div>';
      }

    }

    return array_unique($output);
  }

  /**
   * Fetch all trademark requests associated with the logged in user.
   *
   * Returns a formatted list of trademark requests.
   *
   * @return array
   *   A list of trademark request.
   */
  public function getTrademarkRequests($order_id = NULL, $user_id = NULL) {
    if (!$user_id) {
      $user_id = $this->currentUser->id();
    }
    if ($this->currentUser->id() == '0') {
      $tempstore = \Drupal::service('user.private_tempstore')->get('ige_checkout');
      $user_requests = $tempstore->get('trademark_requests');
      if (!$user_requests) {
        return [];
      }

      $requests = $this->entityTypeManager
        ->getStorage('trademark_request')
        ->loadMultiple($user_requests);

      return $this->getTrademarkRequestData($requests, $order_id);
    }

    $requests = $this->entityTypeManager
      ->getStorage('trademark_request')
      ->loadByProperties([
        'uid' => $user_id,
        'type' => 'trademark_request',
      ]);

    $requests = $this->getTrademarkRequestData($requests, $order_id)
      + $this->getAdditionalTrademarkRequestData($requests, $order_id);
    return $requests;
  }

  public function getAdditionalTrademarkRequestData($requests, $order_id = NULL) {
    $currency_formatter = \Drupal::service('commerce_price.currency_formatter');
    $output = [];

    foreach ($requests as $request) {
      if (!$request->field_admin_added->value) {
        continue;
      }
      $price_array = $request->field_price->getvalue();

      $product_data = $this->getRequestCountry($request);

      $url = Url::fromRoute('ige_checkout.trademark_delete', [
        'id' => $request->id(),
        'order_id' => $order_id,
      ]);
      $url->setOption('attributes', [
        'class' => [
          'use-ajax',
        ],
        // This attribute tells it to use our kind of dialog
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'width' => '700',
          'title' => t('Delete'),
        ]),
      ]);
      $delete_link = Link::fromTextAndUrl(t('Delete'), $url);

      $price = new price($price_array['0']['number'], $price_array['0']['currency_code']);

      $price = ige_cart_currency_convert(
        $price,
        $price_array['0']['currency_code'],
        $ceil = 1
      );

      $output[$request->id()] = [
        'services' => $request->title->value,
        'class' => '-',
        'country' => implode(", ", $product_data),
        'price_object' => $price,
        'price' => $currency_formatter->format(
          $price->getNumber(),
          $price->getCurrencyCode()
        ),
        'edit' => '',
        'delete' => $delete_link,

        'discount' => $request->field_discount->getValue(),
        'total' => $currency_formatter->format(
          $price->getNumber(),
          $price->getCurrencyCode()
        ),
      ];
    }

    return $output;
  }

  /**
   * Provides details of the provided trademark requests in a table format.
   *
   * @param array $requests
   *   The requests array.
   * @param string $order_id
   *   The order ID, If provided the delete links will be taken
   *   to order checkout page.
   *
   * @return array
   *   A list of requestes with its data.
   */
  public function getTrademarkRequestData(array $requests, $order_id = NULL) {
    $link = $delete_link = NULL;
    $output = $class_data = [];
    $services = $product_ids = [];
    $class_count = 1;
    $currency_formatter = \Drupal::service('commerce_price.currency_formatter');
    $currency = \Drupal::request()->cookies->get('commerce_currency');
    if (!$currency) {
      $currency = 'USD';
    }
    // Loop through each request item and create an array for table listing.
    foreach ($requests as $request) {
      $allowed_values = $request->getFieldDefinition('field_trademark_type')->getFieldStorageDefinition()->getSetting('allowed_values');
      if ($request->field_admin_added->value) {
        continue;
      }
      $class_data = [];
      $total = 0;
      $product_ids = [];

      $service = $request->field_trademark_type->getValue();

      $trademark_text = $request->field_trademark_text->getValue();

      // Loop throguh each class to generate an array of class numbers.
      $class = $request->field_class_data->getValue();
      foreach ($class as $value) {
        $class_fc = Paragraph::load($value['target_id']);
        if (!empty($class_fc->field_class_name->value)) {
          $name = $class_fc->field_class_name->value;
          $class_data[$name] = $name;
        }
      }

      if (!$class_data) {
        $class_data = ['1' => '1'];
      }

      $services = [];

      if (!empty($service['0']['value'])) {
        $services[$service['0']['value']] = t($allowed_values[$service['0']['value']]);
      }
      if (!empty($service['1']['value'])) {
        $services[$service['1']['value']] = t($allowed_values[$service['1']['value']]);
      }

      $product_data = $this->getRequestCountry($request);
      $products = $request->field_product->getValue();
      foreach ($products as $item) {
        if (isset($item['target_id'])) {
          $product_ids[] = $item['target_id'];
        }
      }

      if ($product_ids) {
        $class_count = count($class_data);
        $total = $this->getTotalServicePrice(
          $product_ids,
          $class_count,
          $services,
          $request->field_image->entity
        );
        if (!empty($services)) {
          $services = implode(", ", $services);
          if (isset($trademark_text['0']['value'])) {
            $services = $services . ' - ' . $trademark_text['0']['value'];
          }

          $url = Url::fromRoute('ige_checkout.trademark_edit',
            [
              'id' => $request->id(),
              'order_id' => $order_id,
            ]
          );
          $link = Link::fromTextAndUrl(t('Edit'), $url);

          $url = Url::fromRoute('ige_checkout.trademark_delete', [
            'id' => $request->id(),
            'order_id' => $order_id,
          ]);
          $url->setOption('attributes', [
            'class' => [
              'use-ajax',
            ],
            // This attribute tells it to use our kind of dialog
            'data-dialog-type' => 'modal',
            'data-dialog-options' => Json::encode([
              'width' => '700',
              'title' => t('Delete'),
            ]),
          ]);
          $delete_link = Link::fromTextAndUrl(t('Delete'), $url);

          $price = new price($total, $currency);
          $total = $price;
          $total = $currency_formatter->format(
            $total->getNumber(),
            $total->getCurrencyCode()
          );
          if ($request->field_discount->first() && $request->field_discount->first()->toPrice()->getNumber() != 0) {
            $discount_array = $request->field_discount->getValue();
            $discount = new price(-($discount_array['0']['number']), $discount_array['0']['currency_code']);
            if ($discount_array['0']['currency_code'] != $currency) {
              $discount = ige_cart_currency_convert($discount, $discount_array['0']['currency_code'], '1');
            }

            $total = $price->add($discount);
            $total = $currency_formatter->format(
              $total->getNumber(),
              $total->getCurrencyCode()
            );
            $array = [
              '#theme' => 'item_list',
              '#list_type' => 'ul',
              '#attributes' => [
                'class' => [
                  'cart__price',
                ],
              ],
              '#items' => [
                [
                  '#markup' => $currency_formatter->format(
                    $price->getNumber(),
                    $price->getCurrencyCode()
                  ),
                  '#wrapper_attributes' => [
                    'class' => [
                      'price__strike',
                    ],
                  ],
                ],
                ['#markup' => $total],
              ],
            ];
            $total = \Drupal::service('renderer')
              ->render($array);
          }

          $output[$request->id()] = [
            'services' => $services,
            'class' => implode(", ", $class_data),
            'country' => implode(", ", $product_data),
            'price_object' => $price,
            'price' => $currency_formatter->format(
              $price->getNumber(),
              $price->getCurrencyCode()
            ),
            'edit' => $link,
            'delete' => $delete_link,
            'total' => $total,
          ];
        }
      }
      else {
        $url = Url::fromRoute('ige_checkout.trademark_delete', [
          'id' => $request->id(),
          'order_id' => $order_id,
        ]);
        $url->setOption('attributes', [
          'class' => [
            'use-ajax',
          ],
          // This attribute tells it to use our kind of dialog
          'data-dialog-type' => 'modal',
        ]);
        $delete_link = Link::fromTextAndUrl(t('Delete'), $url);
        $number = $request->field_price->getValue();
        if (!empty($number['0']['number'])) {
          $output[$request->id()] = [
            'services' => $request->field_trademark_text->value,
            'class' => '-',
            'country' => '-',
            'price' => new price($number['0']['number'], $number['0']['currency_code']),
            'edit' => '-',
            'delete' => $delete_link,
          ];
        }
      }
    }

    return $output;
  }

  public function getOwnerData($order) {
    $output = [];
    $order_items = $order->getItems();
    $output['id'] = NULL;
    $output['data'] = t('Please select a address from existing profile link or add a new one by clicking Nre profile link');
    foreach ($order_items as $order_item) {
      $profile = $order_item->field_owner->entity;
      if (!empty($profile)) {
        $output['id'] = $profile->id();
        if (isset($profile->address->getValue()['0'])) {
          $address = $profile->address->getValue()['0'];
          $name = $address['given_name'] . ' ' . $address['family_name'];

          unset($address['given_name']);
          unset($address['family_name']);
          unset($address['langcode']);
          $address = array_reverse($address);
          $country_code = $address['country_code'];
          $pin = $address['postal_code'];
          unset($address['postal_code']);
          $country = \Drupal::service('country_manager')->getList()[$country_code]->__toString();
          $address['country_code'] = $country;
          $address['postal_code'] = $pin;

          $address_string = implode(", ", array_filter($address));

          $output['data'] =
            '<div class = "address">
            <div class = "address-item"><p>'
            . t('<strong>Reference:</strong>') . ' ' . $profile->field_title->value . '</p><p>'
            . t('<strong>Name:</strong>') . ' ' . $name . '</p><p>'
            . t('<strong>Address:</strong>') . ' ' . $address_string
            . '</p></div>
          </div>';
        }
      }
    }

    return $output;
  }

  private function getRequestClasses($ids) {
    $class_data = [];

    if (!empty($ids)) {
      $class_term  = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadMultiple($ids);
      if (!empty($class_term)) {
        foreach ($class_term as $class) {
          $class_data[] = $class->getName();
        }
      }
    }
    return $class_data;
  }

  private function getRequestCountry($request) {
    $lang = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $product_data = [];

    $products = $request->field_product->getValue();
    foreach ($products as $item) {
      if (isset($item['target_id'])) {
        $ids[] = $item['target_id'];
      }
    }

    if (!empty($ids)) {
      $products  = $this->entityTypeManager
        ->getStorage('commerce_product')
        ->loadMultiple($ids);
      if (!empty($products)) {
        foreach ($products as $product) {
          if ($product->hasTranslation($lang)) {
            $product = $product->getTranslation($lang);
          }
          $product_data[] = $product->getTitle();
        }
      }
    }
    return $product_data;
  }

  public function createOrder($requests) {
    if (empty($requests)) {
      return;
    }
    $products = $no_logo = [];

    $uid = $this->currentUser->id();

    // Loop through each request and create order item based on each request.
    foreach ($requests as $request) {
      $service = $request->field_trademark_type->getValue();
      $services = [];
      if (!empty($service['0']['value'])) {
        $services[$service['0']['value']] = $service['0']['value'];
      }
      if (!empty($service['1']['value'])) {
        $services[$service['1']['value']] = $service['1']['value'];
      }

      $no_logo = [];
      $product_ids = $request->field_product->getValue();
      foreach ($product_ids as $item) {
        $products[$item['target_id']] = $item['target_id'];
      }

      $image = $request->field_image->entity;

      // If a client uploads a logo for a search service in a country that does
      // not have the variation “combined_search”, the client should be able to
      // place the order, but a information message should appear.
      $trademark_data = \Drupal::service('ige_cart.trademarks')
        ->getServicePrice($products, '1');

      foreach ($trademark_data as $product_id => $result) {
        if (!empty($services['search'])) {
          // @to-do Modify the hardcoded values.
          if ($image && !isset($result['3']['2']['total_service_price']['price'])) {
            $no_logo[] = $product_id;
          }
        }
      }

      if ($no_logo) {
        $names = $this->getProducts($no_logo);
        $names = implode(", ", $names);
        drupal_set_message(t('
          Searches for figurative elements are not available in the following
          countries: @names. Therefore, you will be charged for a wordmark
          search in those countries', [
            '@names' => $names,
          ]
        ), 'warning');
      }

      $uid = $request->getOwnerId();

      // Remove the current card order before proceeding.
      $this->removeCurrentCartOrders($uid);


      if ($request->field_admin_added->value) {
        $order_items[] = $this->createAdditionalServiceOrderItem($request);
      }
      else {
        $order_items[] = $this->createOrderItem(
          $request
        );
      }
    }

    if (empty($order_items)) {
      return;
    }

    $order = Order::create([
      'type' => 'default',
      'uid' => $uid,
      'state' => 'draft',
      'store_id' => $this->entityTypeManager
        ->getStorage('commerce_store')->loadDefault(),
      'order_items' => $order_items,
      'placed' => time(),
      'field_trademark_request' => $requests,
    ]);
    $order->save();

    $logStorage = \Drupal::entityTypeManager()->getStorage('commerce_log');
    $logStorage->generate(
      $order,
      'commerce_order_admin_comment',
      ['comment' => 'System: Order created in draft state']
    )->save();

    $order_total = $order->getTotalPrice()->getNumber();
    foreach ($requests as $request) {
      if ($request->field_discount->getValue()) {
        $price_array = $request->field_discount->getValue();
        if ($order_total > $price_array['0']['number']) {
          $price = new price(-($price_array['0']['number']), $price_array['0']['currency_code']);
          $price = ige_cart_currency_convert($price, $price_array['0']['currency_code']);
          $order->addAdjustment(new Adjustment([
            'type' => 'custom',
            'label' => t('Discount'),
            'amount' => $price,
            'included' => FALSE,
          ]));
        }
      }
    }
    $order->save();

    return $order;
  }

  /**
   * Gathers the name of the country from country code.
   *
   * @param array $country_codes
   *   Array of selected country codes.
   *
   * @return array
   *   List of countries.
   */
  public function getProduct($country_codes) {
    $output = [];
    $query = \Drupal::entityQuery('commerce_product');
    $query->condition('type', 'default', '=');
    $query->condition('field_country_code', $country_codes, 'IN');
    $entity_ids = $query->execute();
    foreach ($entity_ids as $id) {
      $output[$id] = $id;
    }
    return $output;
  }

  /**
   * Gathers the list of countries.
   *
   * @return array
   *   List of country codes.
   */
  public function getProducts($entity_ids = []) {
    $output = [];
    $lang = \Drupal::languageManager()->getCurrentLanguage()->getId();
    if (empty($entity_ids)) {
      $query = \Drupal::entityQuery('commerce_product');
      $entity_ids = $query->execute();
    }

    $products = \Drupal::entityTypeManager()->getStorage('commerce_product')
      ->loadMultiple($entity_ids);
    foreach ($products as $product) {
      if ($product->hasTranslation($lang)) {
        $product = $product->getTranslation($lang);
      }
      if ($product->hasField('field_country_code') &&
        !$product->get('field_country_code')->isEmpty()
      ) {
        $output[$product->field_country_code->value] = $product->title->value;
      }
    }
    return $output;
  }

  public function getTotalServicePrice(
    $product_ids,
    $class_count,
    $services,
    $image = NULL
  ) {
    $total = 0;
    $results = \Drupal::service('ige_cart.trademarks')
      ->getServicePrice($product_ids, $class_count);
    foreach ($results as $product_id => $result) {
      if (!empty($services['search'])) {
        // @to-do Modify the hardcoded values.
        if ($image && isset($result['3']['2']['total_service_price']['price'])) {
          $total += $result['3']['2']['total_service_price']['price'];
        }
        else {
          $total += $result['3']['1']['total_service_price']['price'];
          $no_logo[] = $product_id;
        }
      }
      if (!empty($services['application'])) {
        if (isset($result['4']['5']['total_service_price']['price'])) {
          $total += $result['4']['5']['total_service_price']['price'];
        }
      }
    }

    return $total;
  }

  public function addTracker($order) {
    $items = $order->getItems();

    // Loop through each item to create trackers.
    foreach ($items as $item) {
      $tracker_ids = $item->field_tracker->getValue();

      if ($tracker_ids) {
        continue;
      }

      $this->queue->createItem([
        'order_item' => $item,
        'order' => $order,
      ]);

      $logStorage = \Drupal::entityTypeManager()->getStorage('commerce_log');
      $logStorage->generate(
        $order,
        'commerce_order_admin_comment',
        ['comment' => 'System: Queued to create service tracker']
      )->save();
    }

  }

  /**
   * Creates service tracker corresponding to the data provided.
   *
   * @param array $data
   *   The data needed for creating tracker.
   */
  public function createServiceTracker(array $data) {
    $title = t('Service Tracker');
    if (isset($data['title'])) {
      $title = $data['title'];
    }

    $values = [
      'title' => $title,
      'type' => 'service_tracker',
      'uid' => $data['customer'],
      'language' => \Drupal::languageManager()->getCurrentLanguage()->getId(),
      'created' => strtotime("now"),
      'changed' => strtotime("now"),
      'status' => 1,
      'author' => $data['customer'],
      'field_trademark_logo' => $data['trademark_logo'],
      'field_attorney' => $data['provider'],
    ];

    $service_tracker = $this->entityTypeManager->getStorage('service_tracker')->create(
      $values
    );
    $service_tracker->field_service_type = $data['trademark_type'];
    $service_tracker->field_consultant = $data['consultant'];
    $service_tracker->field_trademark_text = $data['text'];
    $service_tracker->field_customer = $data['customer'];
    $service_tracker->field_tracker_user = $data['customer'];
    $service_tracker->field_deadline_date = $data['timeframe'];
    $service_tracker->field_trademark_owner = $data['owner'];
    $service_tracker->field_trademark_type = 'wordmark';
    if ($data['trademark_logo']) {
      $service_tracker->field_trademark_type = 'combined';
    }
    $service_tracker->field_image = $data['trademark_logo'];
    $service_tracker->field_service_status = $data['status'];
    $service_tracker->field_filling_number = $data['filling_number'];
    $service_tracker->field_filling_date = $data['filling_date'];
    $service_tracker->field_registration_date = $data['reg_date'];
    $service_tracker->field_registration_number = $data['reg_number'];
    $service_tracker->field_order = $data['order'];
    $service_tracker->field_order_item = $data['order_item'];
    $service_tracker->field_country = [];
    $service_tracker->save();
    $service_tracker->field_country[] = $data['products'];

    // Add class field collection.
    if (!empty($data['class'])) {
      foreach ($data['class'] as $key => $class_text) {
        $class_field = Paragraph::create(
          [
            'type' => 'class_data',
            'field_class_name' => $key,
            'field_class_description' => $class_text,
          ]
        );
        $class_field->save();
        $service_tracker->field_class_data[] = $class_field;
      }
    }

    $service_tracker->save();

    return $service_tracker;
  }

  public function getUsers() {
    $output = [];
    $users = $this->entityTypeManager
      ->getStorage('user')
      ->loadByProperties([
        'status' => TRUE,
      ]);

    foreach ($users as $user) {
      $output[$user->uid->value] = $user->mail->value;
    }

    return $output;
  }

  /**
   * Removes the current draft and cart orders of the user.
   *
   * @param string $uid
   *   The id of the user whose draft orders are to be removed.
   */
  protected function removeCurrentCartOrders($uid) {
    $orders = $this->entityTypeManager
      ->getStorage('commerce_order')
      ->loadByProperties([
        'state' => ['draft', 'cart'],
        'uid' => $uid,
      ]);
    foreach ($orders as $order) {
      $order->delete();
    }
  }

}

