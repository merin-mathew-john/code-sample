<?php

namespace Drupal\ige_checkout\EventSubscriber;

use Drupal\user\Entity\User;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * Allows Smart IP to act on HTTP request event.
 *
 * @package Drupal\ige_checkout\EventSubscriber
 */
class GeoCurrency implements EventSubscriberInterface {

  /**
   * Initiate user geolocation.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The response event, which contains the current request.
   */
  public function geolocateUser(GetResponseEvent $event) {
    // Set currency based on user location.
    $currency_helper = \Drupal::service('commerce_currency_resolver.currency_helper');
    $cookie_name = $currency_helper->getCookieName();
    $cookies = \Drupal::request()->cookies;
    if (!$cookies->get($cookie_name)) {
      $currency_helper = \Drupal::service('commerce_currency_resolver.currency_helper');

      $user_id = \Drupal::currentUser()->id();
      if ($user_id != 0) {
        $user = User::load($user_id);
        if ($user->field_preferred_currency->value) {
          setrawcookie(
            $currency_helper->getCookieName(),
            $user->field_preferred_currency->value,
            \Drupal::time()->getRequestTime() + 86400, '/'
          );
          return;
        }
      }
      $location = \Drupal::service('smart_ip.smart_ip_location');
      $country_code = $location->get('countryCode');
      if (!$country_code) {
        return;
      }

      $matrix = \Drupal::config(
          'commerce_currency_resolver.currency_mapping'
        )->get('matrix');

      if (isset($matrix[$country_code])) {
        setrawcookie(
          $currency_helper->getCookieName(),
          $matrix[$country_code],
          \Drupal::time()->getRequestTime() + 86400, '/'
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['geolocateUser'];
    return $events;
  }

}
