<?php

namespace Drupal\ige_quote;

use Drupal\commerce_price\Price;

/**
 * Class for fetching the quote related data.
 */
class QuoteData {

  /**
   * Gathers data for a quote.
   *
   * @param $entity
   *   The quote entity.
   *
   * @return mixed
   *   Returns the quote.
   */
  public function getQuoteData($entity) {
    if (!$entity) {
      return;
    }
    $request = \Drupal::request();
    // Get cookies.
    $cookies = $request->cookies;
    $target_currency = $cookies->get('commerce_currency');
    if (empty($target_currency)) {
      $target_currency = 'USD';
    }

    $grant_total_application = $grant_study_total = $grant_total_final_fee = 0;

    $number_of_classes = $entity->field_number_of_class->getValue()['0']['value'];
    $number_of_additional_classes = $number_of_classes;

    $country = array_column($entity->field_country->getValue(), 'target_id');

    $results = \Drupal::service('ige_cart.trademarks')->getServicePrice($country, $number_of_additional_classes);

    $quote['timeframes'] = \Drupal::service('ige_cart.trademarks')->getTimeFrames($country);
    $quote['requirement'] = \Drupal::service('ige_cart.trademarks')->getRequirements($country);
    $quote['price_notes'] = \Drupal::service('ige_cart.trademarks')->getPriceNotes($country);
    $quote['search_price_notes'] = \Drupal::service('ige_cart.trademarks')->isFigurativeEnabled($country);
    foreach ($results as $key => $country) {
      $quote['countries'][$country['country_name']] = $country['country_name'];
      $quote['final_fee_details'][$key]['first_final_fee'] = t('N/A');
      $quote['data']['final_fee'][$country['country_name']]['first_price'] = t('N/A');
      $quote['final_fee_details'][$key]['additional_final_fee'] = t('N/A');
      $quote['data']['final_fee'][$country['country_name']]['additional_class_price'] = t('N/A');
      if (!empty($results[$key]['first_final_fee'])) {
        $quote['final_fee_details'][$key]['first_final_fee'] = new Price($results[$key]['first_final_fee'], $target_currency);

        $quote['data']['final_fee'][$country['country_name']]['first_price'] = new Price($results[$key]['first_final_fee'], $target_currency);

      }
      if (!empty($results[$key]['additional_final_fee'])) {
        $quote['final_fee_details'][$key]['additional_final_fee'] = new Price($results[$key]['additional_final_fee'], $target_currency);

        $quote['data']['final_fee'][$country['country_name']]['additional_class_price'] = new Price($results[$key]['additional_final_fee'], $target_currency);

      }
      foreach ($country as $trademark_service => $item) {
        $trademark_service = entity_load('commerce_product_attribute_value', $trademark_service);
        if (!empty($trademark_service)) {
          $trademark_service_name = $trademark_service->getName();
          foreach ($item as $trademark_type => $services) {
            if (is_numeric($services['total_service_price']['price'])) {
              $currency = $services['total_service_price']['currency'];
              $trademark_type = entity_load('commerce_product_attribute_value', $trademark_type);
              $trademark_type_name = $trademark_type->getName();
              if ($trademark_type_name == 'Wordmark' && $trademark_service_name == "Search") {
                $grant_study_total += $services['total_service_price']['price'];
                $total_search = new Price($services['total_service_price']['price'], $services['total_service_price']['currency']);
                $quote['search'][] = ige_cart_currency_convert($total_search, $services['total_service_price']['currency']);

                $quote['data']['search'][$country['country_name']]['total_price'] = new Price($services['total_service_price']['price'], $services['total_service_price']['currency']);
                $quote['data']['search']['prices'][] = $quote['data']['search'][$country['country_name']]['total_price'];

                $quote['data']['search'][$country['country_name']]['first_price'] = new Price($services['price'], $services['total_service_price']['currency']);

                $quote['additional']['search'][$country['country_name']]['first_price'] = new Price($services['price'], $services['total_service_price']['currency']);

                $additional_class_price = end($services['additional_class_price']);
                $quote['additional']['search'][$country['country_name']]['additional_price'] = new Price($additional_class_price, $services['total_service_price']['currency']);

                $quote['data']['search'][$country['country_name']]['additional_class_price'] = new Price($additional_class_price, $services['total_service_price']['currency']);
              }
              if ($trademark_service_name == "Application") {
                $grant_total_application += $services['total_service_price']['price'];
                $total_application_price = new Price($services['total_service_price']['price'], $services['total_service_price']['currency']);
                $quote['application'][] = $total_application_price;

                $quote['data']['application'][$country['country_name']]['total_price'] = new Price($services['total_service_price']['price'], $services['total_service_price']['currency']);
                $quote['data']['application']['prices'][] = $quote['data']['application'][$country['country_name']]['total_price'];
                $quote['additional']['application'][$country['country_name']]['first_price'] = new Price($services['price'], $services['total_service_price']['currency']);

                $quote['data']['application'][$country['country_name']]['first_price'] = new Price($services['price'], $services['total_service_price']['currency']);

                $additional_class_price = end($services['additional_class_price']);
                $quote['additional']['application'][$country['country_name']]['additional_price'] = new Price($additional_class_price, $services['total_service_price']['currency']);

                $quote['data']['application'][$country['country_name']]['additional_class_price'] = new Price($additional_class_price, $services['total_service_price']['currency']);
              }
            }
          }
        }
      }
      $grant_total_final_fee += $country['total_final_fee']['price'];
      $quote['final_fee'][$key]['final_fee'] = t('N/A');
      $quote['data']['final_fee'][$country['country_name']]['total_price'] = t('N/A');
      if (!empty($country['total_final_fee']['price'])) {
        $quote['final_fee'][$key]['final_fee'] = new Price($country['total_final_fee']['price'], $currency);
        $quote['data']['final_fee'][$country['country_name']]['total_price'] = new Price($country['total_final_fee']['price'], $currency);
        $quote['final_fee']['prices'][] = $quote['data']['final_fee'][$country['country_name']]['total_price'];

      }
      if (!empty($country['time_frames'])) {
        $quote['time_frame'][$key]['time_frame'] = $country['time_frames'];
      }
    }

    $grant_study_total = new price('0', $target_currency);
    foreach ($quote['data']['search']['prices'] as $search_total) {
      $grant_study_total = $grant_study_total->add($search_total);
    }
    $quote['total']['search'] = $grant_study_total;

    $grant_total_application = new price('0', $target_currency);
    foreach ($quote['data']['application']['prices'] as $application_total) {
      $grant_total_application = $grant_total_application->add($application_total);
    }
    $quote['total']['application'] = $grant_total_application;

    $quote['grant_total'] = $grant_study_total->add($grant_total_application);

    $grant_total_final_fee = new price('0', $target_currency);
    foreach ($quote['final_fee']['prices'] as $final_fee_total) {
      $grant_total_final_fee = $grant_total_final_fee->add($final_fee_total);
    }
    $quote['grant_total_final_fee'] = $grant_total_final_fee;
    if ($grant_total_final_fee->getNumber() == 0) {
      $quote['grant_total_final_fee'] = t('N/A');
    }

    unset($quote['data']['search']['prices']);
    unset($quote['data']['application']['prices']);
    unset($quote['data']['application']['prices']);

    return $quote;
  }

}
