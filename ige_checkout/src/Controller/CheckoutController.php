<?php

namespace Drupal\ige_checkout\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\eck\Entity\EckEntity;

use Drupal\ige_checkout\Helper;
use Drupal\commerce_order\Entity\Order;
use Drupal\profile\Entity\Profile;

use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\commerce_price\Price;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Dompdf\Dompdf;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Access\AccessResult;

/**
 * The controller for rendering a quote form.
 */
class CheckoutController extends ControllerBase {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;


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
    EntityManagerInterface $entity_manager,
    EntityTypeManagerInterface $entity_type_manager,
    Helper $checkout_helper
  ) {
    $this->entityManager = $entity_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->checkoutHelper = $checkout_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('entity_type.manager'),
      $container->get('ige_checkout.checkout_helper')
    );
  }

  /**
   * Delets a request entity.
   */
  public function downloadInvoice(Order $commerce_order) {
    $user = \Drupal::currentUser();
    $currency_formatter = \Drupal::service('commerce_price.currency_formatter');
    if (!$user->hasPermission('access commerce administration pages')) {
      if (\Drupal::currentUser()->id() != $commerce_order->getCustomerId()) {
        throw new AccessDeniedHttpException();
      }
    }

    $class_title = $title = [];
    $user = $commerce_order->getCustomer();
    if ($user->field_consultant->entity) {
      $data['consultant'] = $user->field_consultant->entity->field_name->value;
    }
    if ($commerce_order->getBillingProfile()) {
      $data['billing_profile'] = $commerce_order->getBillingProfile()->id();
    }
    $order_items = $commerce_order->getItems();
    $adjustments = $commerce_order->getAdjustments();

    foreach ($order_items as $order_item) {
      $class_title = $title = [];
      $line_item_id = $order_item->id();
      $type = $order_item->field_trademark_type->getString();
      foreach ($order_item->field_product->referencedEntities() as $product) {
        $title[] = $product->getTitle();
      }

      foreach ($order_item->field_class_data->referencedEntities() as $class) {
        $class_title[] = $class->field_class_name->value;
      }

      if ($class_title) {
        $label = ucfirst($type) . ' - ' . implode(", ", $title) . ' - ' . ' Class: ' . implode(", ", $class_title);
      }
      else {
        $label = ucfirst($order_item->getTitle()) . ' - ' . implode(", ", $title);
      }
      $data['line_items'][$line_item_id]['label'] = $label;
      $data['line_items'][$line_item_id]['price'] = $currency_formatter->format(
        $order_item->getTotalPrice()->getNumber(),
        $order_item->getTotalPrice()->getCurrencyCode()
      );
      $data['line_items'][$line_item_id]['currency'] = $order_item->getTotalPrice()->getCurrencyCode();
      $data['trademark_name'][] = $order_item->field_trademark_text->value;
      $data['trademark_type'] = $order_item->field_trademark_type->getString();
    }
    if ($adjustments) {
      foreach ($adjustments as $adjustment) {
        if ($adjustment->getAmount()->getNumber() != 0 ) {
          $data['line_items']['discount']['label'] = t('Discount');
          $data['line_items']['discount']['price'] = $currency_formatter->format(
            $adjustment->getAmount()->getNumber(),
            $adjustment->getAmount()->getCurrencyCode()
          );
          $data['line_items']['discount']['currency'] = $adjustment->getAmount()->getCurrencyCode();
        }
      }
    }
    $data['number'] = $commerce_order->created->value . '_' . $commerce_order->id();
    $filename = preg_replace('@[^a-z0-9-]+@', '-', strtolower($data['trademark_name'])) . '-igerent-trademark-' . $data['trademark_type'] . '-' . $commerce_order->id() . '-invoice';

    $data['order_total'] = $currency_formatter->format(
      $commerce_order->getTotalPrice()->getNumber(),
      $commerce_order->getTotalPrice()->getCurrencyCode()
    );

    $data['date'] = date('d/m/Y', $commerce_order->created->value);
    $data['document_root'] = $_SERVER["DOCUMENT_ROOT"];
    $markup = [
      '#theme' => 'commerce_order_invoice',
      '#data' => $data,
    ];
    $markup = \Drupal::service('renderer')->render($markup);

    $dompdf = new DOMPDF();
    $dompdf->load_html($markup);
    $dompdf->render();

    $response = new Response();
    $response->setContent($dompdf->output());
    $response->headers->set('Content-Type', 'application/pdf');
    $response->headers->set('Content-Disposition', "attachment; filename={$filename}.pdf");

    return $response;
  }

  /**
   * Delets a request entity.
   */
  public function deleteRequest($id, $order_id = NULL) {
    $request = $this->entityManager
      ->getStorage('trademark_request')
      ->load($id);
    $request->delete();

    drupal_set_message(t('Successfully deleted the trademark item'));

    if (!$order_id) {
      // Redirect to pending payments page if no trademark request associated
      // with the order.
      $url = Url::fromRoute(
        'ige_checkout.cart',
        ['user' => \Drupal::currentUser()->id()]
      )->toString();
      return new RedirectResponse($url);
    }

    $commerce_order = Order::load($order_id);
    $pending_requests = $commerce_order->field_trademark_request->getValue();
    foreach ($pending_requests as $request) {
      $request = $this->entityManager
        ->getStorage('trademark_request')
        ->load($request['target_id']);

      if (!empty($request)) {
        // Redirect to checkout.
        $url = Url::fromRoute(
          'commerce_checkout.form',
          ['commerce_order' => $order_id]
        )->toString();
        return new RedirectResponse($url);
      }
    }

    // Redirect to pending payments page if no trademark request associated
    // with the order.
    $url = Url::fromRoute(
      'ige_checkout.cart',
      ['user' => \Drupal::currentUser()->id()]
    )->toString();
    return new RedirectResponse($url);
  }

  /**
   * Add request to order.
   *
   * @return array
   *   Returns the quote form.
   */
  public function setBilling($id, $pid) {
    $order = Order::load($id);
    $profile = Profile::load($pid);

    // Update order billing information.
    $order->setBillingProfile($profile);
    $order->save();

    return new JsonResponse([
      'data' => TRUE,
      'method' => 'GET',
    ]);
  }

  /**
   * Add request to order.
   *
   * @return array
   *   Returns the quote form.
   */
  public function addRequest(Order $commerce_order, EckEntity $trademark_request) {

    if (!$trademark_request) {
      return new JsonResponse([
        'data' => TRUE,
        'method' => 'GET',
      ]);
    }

    $request_ids = $commerce_order->field_trademark_request->getValue();
    foreach ($request_ids as $request_id) {
      if ($request_id['target_id'] == $trademark_request->id()) {
        return new JsonResponse([
          'data' => FALSE,
          'method' => 'GET',
        ]);
      }
    }

    if ($trademark_request->field_admin_added->value) {
      $order_item = $this->checkoutHelper->createAdditionalServiceOrderItem($trademark_request);
    }
    else {
      $order_item = $this->checkoutHelper->createOrderItem($trademark_request);
    }

    // Append trademark request to order object.
    $commerce_order->get('field_trademark_request')->appendItem([
      'target_id' => $trademark_request->id(),
    ]);
    if ($order_item) {
      $commerce_order->get('order_items')->appendItem([
        'target_id' => $order_item->id(),
      ]);
    }
    $commerce_order->save();

    return new JsonResponse([
      'data' => TRUE,
      'method' => 'GET',
    ]);

  }

  /**
   * Load the quote form.
   *
   * @return array
   *   Returns the quote form.
   */
  public function removeRequest(Order $commerce_order, $trademark_request) {
    // Loop through the request ids and remove the selected request from order.
    $request_ids = $commerce_order->field_trademark_request->getValue();

    foreach ($request_ids as $key => $request_id) {
      if ($request_id['target_id'] == $trademark_request) {
        unset($request_ids[$key]['target_id']);
      }
    }
    $commerce_order->field_trademark_request = $request_ids;

    // Loop through order items and remove the order item associated with the
    // selected request.
    $order_items = $commerce_order->order_items->referencedEntities();
    foreach ($order_items as $key => $commerce_order_item) {
      $order_item_request = $commerce_order_item
        ->field_trademark_request->getValue();

      if ($order_item_request['0']['target_id'] == $trademark_request) {
        $commerce_order->removeItem($commerce_order_item);
        $commerce_order_item->delete();
      }
    }

    $commerce_order->save();

    return new JsonResponse([
      'data' => TRUE,
      'method' => 'GET',
    ]);
  }

  /**
   * Searches for classes.
   *
   * @return array
   *   Returns the quote form.
   */
  public function classSearch(Request $request) {
    $output = $term_desc_1 = $read_more = NULL;
    $popup_desc = [];
    $lang = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $keyword = $request->get('keyword');
    if ($lang == 'en') {
      $keyword = trim(str_replace('class', '', $keyword));
    }
    elseif ($lang == 'es') {
      $keyword = trim(str_replace('clase', '', $keyword));
    }
    elseif ($lang == 'fr') {
      $keyword = trim(str_replace('classe', '', $keyword));
    }
    $query = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery()
      ->condition('status', 1)
      ->condition('vid', 'class');

    if ($keyword) {
      $group = $query->orConditionGroup()
        ->condition('description.value', $keyword, 'CONTAINS')
        ->condition('field_sub_class', $keyword, 'CONTAINS')
        ->condition('name', $keyword, 'CONTAINS');
      $query->condition($group);
    }
    $query->range(0, 45);
    $tids = $query->execute();

    $terms = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadMultiple($tids);

    foreach ($terms as $term) {
      $popup_desc = [];
      $desc = $read_more = $term_desc_1 = $dots = NULL;

      if ($term->hasTranslation($lang)) {
        $term = $term->getTranslation($lang);
      }
      $class_number = $term->getName();

      $tid = $term->id();
      $term_name = t('Class') . ' ' . $class_number;

      if ($keyword) {
        $popup_desc = [];
        $subclass = $term->field_sub_class->getValue();
        foreach ($subclass as $key => $class_text) {
          if (strpos($class_text['value'], $keyword) !== FALSE) {
            $popup_desc[] = $class_text['value'];
          }
        }
      }

      if (!$popup_desc) {
        $popup_desc[] = strip_tags($term->getDescription());
      }

      $desc = implode(", ", $popup_desc);

      if ($desc) {
        $term_desc = Unicode::substr($desc, 0, 70);

        if (strlen($desc) > 70) {
          $term_desc_1 = '<span class = "class-popup-more-text" id="more-' . $tid . '">' . substr($desc, 100) . '</span>';
          $read_more = '<a data-tid = "' . $tid . '" id="class-popup-more-button-' . $tid . '" class = "readmore-class class-desc" >' . t('Read more') . '</a>';
          $dots = '<span class = "class-popup-readmore-dot" id="dots-' . $tid . '">...</span>';
        }

        $term_name = t('Class') . ' ' . $class_number;
        $output .= '<div class = "js-form-item form-item js-form-type-checkbox form-item-class-' . $class_number . ' js-form-item-class-' . $class_number . '">
          <input data-drupal-selector="edit-class-' . $class_number . '" type="checkbox" id="edit-class-' . $class_number . '" name="class[' . $class_number . ']" value="' . $class_number . '" class="trademark-class-select form-checkbox">
          <label for="edit-class-' . $class_number . '" class="option"><div><b>' . $term_name . ': </b><span class = "class-desc">' .
          $term_desc . $dots . ' ' . $term_desc_1 . '</span>
          ' . $read_more . '</div></label>
        </div>';
      }
    }
    if (!$output) {
      $output = t('Sorry, we could not find any class matching your search criteria');
    }

    return new JsonResponse([
      'data' => $output,
      'method' => 'GET',
    ]);
  }

  /**
   * Provides the trademark summary block.
   *
   * @return array
   *   Returns the quote form.
   */
  public function trademarkSummary(Request $request) {
    $type = $service = $products = [];
    $search = $request->get('search');
    $application = $request->get('application');

    if ($search === 'true') {
      $type[] = t('Trademark Search');
      $service['search'] = 'search';
    }

    if ($application === 'true') {
      $type[] = t('Trademark Application');
      $service['application'] = 'application';
    }

    $label[] = implode(', ', $type);

    $countries = $request->get('countries');
    kint($request);
    //dpm($countries);
    if ($countries) {
      $products = $this->checkoutHelper->getProduct($countries);
      $label[] = implode(", ", $this->checkoutHelper->getProducts($products));
    }
    else {
      $label[] = t('No country selected');
    }

    $class = $request->get('class');
    if ($class) {
      $class_count = count($class);
      $class_label = t('Class');
      if ($class_count > 1) {
        $class_label = t('Classes');
      }
      $class = implode(", ", $class);
      $label[] =  $class_label . ' ' .  $class;
    }

    if (!$class) {
      $label[] = '1 ' . t('Class (Default)');
      $class_count = 1;
    }

    $data['label'] = implode(" | ", $label);

    $data['currency'] = ige_cart_get_currency();
    $price = new Price('0', "USD");
    $price = ige_cart_currency_convert($price);

    if ($products) {
      $data['price'] = $this->checkoutHelper->getTotalServicePrice(
        $products,
        $class_count,
        $service,
        $request->get('image')
      );
      $price = new Price($data['price'], $data['currency']);
    }

    $data['price'] = $price->__toString();

    $output = [
      '#theme' => 'trademark_summary',
      '#data' => $data,
    ];
    $renderer = \Drupal::service('renderer');
    $html = $renderer->render($output);

    return new JsonResponse([
      'data' => $html,
      'method' => 'GET',
    ]);
  }

  public function createTracker(Order $commerce_order) {
    $checkout_helper = \Drupal::service('ige_checkout.checkout_helper');
    $checkout_helper->addTracker($commerce_order);

    \Drupal::messenger()->addMessage(t('Service trackers has been created.'), 'status');

    return $this->redirect('entity.commerce_order.canonical', ['commerce_order' => $commerce_order->id()]);
  }

  public function accessOrder($commerce_order) {
    $user = \Drupal::currentUser();
    if ($user->hasPermission('access commerce administration pages')) {
      return AccessResult::allowed();
    }

    $commerce_order = Order::load($commerce_order);
    if (\Drupal::currentUser()->id() != $commerce_order->getCustomerId()) {
      // Return 403 Access Denied page.
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

  public function accessRequest($id, $order_id) {
    $user = \Drupal::currentUser();
    if ($user->hasPermission('access commerce administration pages')) {
      return AccessResult::allowed();
    }

    $request = $this->entityManager
      ->getStorage('trademark_request')
      ->load($id);

    if (\Drupal::currentUser()->id() != $request->getOwnerId()) {
      // Return 403 Access Denied page.
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

  public function authenticatedUser() {
    if (\Drupal::currentUser()->id() == '0') {
      // Return 403 Access Denied page.
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

  public function accessUser($user) {
    if (\Drupal::currentUser()->hasPermission('access commerce administration pages')) {
      return AccessResult::allowed();
    }

    if (\Drupal::currentUser()->id() != $user) {
      // Return 403 Access Denied page.
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

  public function pendingPayments() {
    if (\Drupal::currentUser()->id() == '0') {
      return $this->redirect('user.login', ['destination' => '/user/pending-payments']);
    }
    return $this->redirect('ige_checkout.cart', ['user' => \Drupal::currentUser()->id()]);
  }

  public function myPortfolio() {
    if (\Drupal::currentUser()->id() == '0') {
      return $this->redirect('user.login', ['destination' => '/user/my-portfolio']);
    }
    return $this->redirect('view.my_portfolio.page_1', ['user' => \Drupal::currentUser()->id()]);
  }

  public function myOrders() {
    if (\Drupal::currentUser()->id() == '0') {
      // Return 403 Access Denied page.
      return $this->redirect('user.login', ['destination' => '/user/orders']);
    }
    return $this->redirect('view.commerce_user_orders.order_page', ['user' => \Drupal::currentUser()->id()]);
  }
}
