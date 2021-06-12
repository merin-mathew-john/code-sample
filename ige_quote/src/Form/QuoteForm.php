<?php

namespace Drupal\ige_quote\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Builds a form for adding quotes.
 */
class QuoteForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ige_quote_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $name = $email = $ticket = NULL;
    $currency = $country = $classes = $consultant = NULL;
    $quote = \Drupal::request()->attributes->get('quote');
    if (!empty($quote)) {
      $name = $quote->field_name->value;
      $email = $quote->field_email->value;
      $ticket = $quote->field_zendesk_ticket->value;
      $currency = $quote->field_currency->value;
      $country = $quote->field_country->referencedEntities();
      foreach ($country as $product) {
        $country[] = $product->field_country_code->value;
      }
      $classes = $quote->field_number_of_class->value;
      $consultant = $quote->field_consultant->entity;
    }
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $name,
    ];
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#default_value' => $email,
    ];
    $form['ticket_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Ticket'),
      '#default_value' => $ticket,
    ];
    $form['classes'] = [
      '#type' => 'select',
      '#title' => $this->t('Classes'),
      '#default_value' => $classes,
      '#options' => array_combine(range(1, 45), range(1, 45)),
    ];
    $allowed_roles = ['consultant', 'administrator'];
    $form['consultant'] = [
      '#title' => $this->t('Consultant'),
      '#type' => 'entity_autocomplete',
      '#required' => TRUE,
      '#target_type' => 'user',
      '#default_value' => $consultant,
      '#selection_handler' => 'default:user',
      '#selection_settings' => [
        'include_anonymous' => FALSE,
        'filter' => [
          'type' => 'role',
          'role' => $allowed_roles,
        ],
      ],
    ];

    $mapper = \Drupal::service('flags.mapping.country');
    $options = $this->getProducts();

    $form['country'] = [
      '#type' => 'select',
      '#title' => $this->t('Country'),
      '#multiple' => TRUE,
      '#options' => array_filter($options),
      '#required' => TRUE,
      '#options_attributes' => $mapper->getOptionAttributes(
          array_keys($options)
      ),
      '#attached' => ['library' => ['flags/flags', 'select2boxes/widget']],
    ];

    if ($country) {
      $form['country']['#default_value'] = $country;
    }

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

    $user = \Drupal::currentUser();

    // Users who have permission to add quote can only create zendesk ticket.
    if ($user->hasPermission('add quote') && !empty($quote)) {
      // Zendesk ticket create link.
      $params = [];
      // Get quote data.
      $quote_data = \Drupal::service('ige_quote.quote_data')->getQuoteData($quote);
      $params['name'] = $quote->field_name->value;
      $params['countries'] = implode(", ", $quote_data['countries']);
      $params['email'] = $quote->field_email->value;
      $params['consultant'] = $quote->field_consultant->target_id;
      if ($quote->field_number_of_class) {
        $number_of_classes = $quote->field_number_of_class->getValue()['0']['value'];
      }
      $params['class_number'] = $number_of_classes;
      $params['type'] = 'quote';
      $params['total_study_price'] = $quote_data['total']['search'];
      $params['total_application_price'] = $quote_data['total']['application'];
      if ($quote) {
        $params['id'] = $quote->id();
      }
      if (!is_null($quote->field_zendesk_ticket) && !empty($quote->field_zendesk_ticket->getValue()['0']['value'])) {
        $params['zendesk_ticket'] = $quote->field_zendesk_ticket->getValue()['0']['value'];
      }
      $zendesk_ticket_url = Url::fromRoute('ige_zendesk.create_ticket', $params)->toString();
      $form['zendesk_link'] = [
        '#type' => 'markup',
        '#markup' => '<a href = ' . $zendesk_ticket_url . '>' . $this->t('Create Zendesk Ticket') . '</a>',
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // $entity_manager = \Drupal::entityManager();
    $name = $form_state->getValue('name');
    $email = $form_state->getValue('email');
    $ticket_number = $form_state->getValue('ticket_number');
    $currency = $form_state->getValue('currency');
    $consultant = $form_state->getValue('consultant');
    $language = $form_state->getValue('language');

    $country_codes = $form_state->getValue('country');
    $countries = $this->getProduct($country_codes);
    $classes = $form_state->getValue('classes');

    $quote = \Drupal::request()->attributes->get('quote');
    if (empty($quote)) {
      $entity_type_mgr = \Drupal::getContainer()->get('entity_type.manager');
      $created = date('d-M-Y');
      $title = t('iGERENT - Trademark Registration - @name - @date', ['@name' => $name, '@date' => $created]);
      $quote = $entity_type_mgr->getStorage('quote')->create(
            [
              'type' => 'quote',
              'title' => $title,
            ]
        );
    }
    $quote->field_consultant = $consultant;
    $quote->field_country = [];
    $quote->save();

    foreach ($countries as $country) {
      $quote->field_country[] = $country;
    }
    $quote->field_currency = $currency;
    $quote->field_email = $email;
    $quote->field_language = $language;
    $quote->field_name = $name;
    $quote->field_number_of_class = $classes;
    $quote->field_zendesk_ticket = $ticket_number;
    $quote->save();

    $form_state->setRedirect(
          'entity.quote.canonical',
          ['quote' => $quote->id()]
      );
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
  public function getProducts() {
    $output = [];
    $query = \Drupal::entityQuery('commerce_product');
    $query->condition('type', 'default', '=');
    $entity_ids = $query->execute();
    $products = \Drupal::entityTypeManager()
      ->getStorage('commerce_product')
      ->loadMultiple($entity_ids);
    foreach ($products as $product) {
      $output[$product->field_country_code->value] = $product->title->value;
    }
    return $output;
  }

}
