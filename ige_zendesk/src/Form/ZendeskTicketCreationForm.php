<?php

namespace Drupal\ige_zendesk\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;

/**
 * Builds a form for adding zendesk ticket.
 */
class ZendeskTicketCreationForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ige_zendesk_zendesk_create_ticket_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $data = [];
    $params = \Drupal::request()->query->all();
    $description = \Drupal::service('ige_zendesk.email_processor')->getEnglishAttorneyOrderEmail($data);

    // Set default values if coming from Quote.
    if (isset($params['type']) && $params['type'] == "quote") {
      $consultant = User::load($params['consultant']);
      $data['consultant'] = $consultant->getUsername();
      $data['requester_mail'] = $params['email'];
      $data['zendesk_ticket'] = $params['zendesk_ticket'];
      $data['requester_name'] = $params['name'];
      $data['class_number'] = $params['class_number'];
      $data['countries'] = $params['countries'];
      $data['quote_link'] = $params['download_pdf'];
      $data['total_study_price'] = $params['total_study_price'];
      $data['total_application_price'] = $params['total_application_price'];
      $description = \Drupal::service('ige_zendesk.email_processor')->getQuoteEmail($data);
      $form_state->set('type', 'quote');
      $form_state->set('quote_id', $params['id']);
      $data['subject'] = $this->t('Trademark Registration Quote for @countries', ['@countries' => $data['countries']]);
    }
    if (isset($params['type']) && $params['type'] == "service_tracker") {
      $data['requester_name'] = $params['attorney'];
      $data['requester_mail'] = $params['attorney_mail'];
      $data['trademark_name'] = $params['trademark_text'];
      $data['trademark_type'] = $params['trademark_type'];
      $data['service_type'] = $params['service_type'];
      $data['order_number'] = $params['order_id'];
      $data['notes'] = $params['notes'];
      $data['image_link'] = $params['field_trademark_logo'];
      $data['image_text'] = $params['image_alt'];
      $data['country'] = $params['country'];
      $data['classes'] = $params['class'];
      $data['filing_date'] = $params['filing_date'];
      $data['filing_number'] = $params['filing_number'];
      $data['registration_number'] = $params['registration_number'];
      // $country[] = explode(',', $params['country']);
      if (isset($params['priority'])) {
        $data['priority'] = 'Yes';

      }
      else {
        $data['priority'] = 'No';

      }

      $data['description'] = $params['description'];
      $data['language'] = $params['lang_code'];
      $data['subject'] = $params['trademark_text'] . '- Trademark Search Order -' . $params['order_id'];
      if ($data['service_type'] == 'Application' && $data['country'] == 'United States') {
        $description = \Drupal::service('ige_zendesk.email_processor')->getUsaApplicationAttorneyOrderEmail($data);
      }
      elseif ($data['service_type'] == 'search') {

        $description = \Drupal::service('ige_zendesk.email_processor')->getEnglishAttorneyOrderEmail($data);

      }
      elseif ($data['service_type'] == 'Application') {

        $description = \Drupal::service('ige_zendesk.email_processor')->getApplicationAttorneyOrderEmail($data);

      }
    }

    $form['zendesk_ticket'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Zendesk Ticket Number'),
      '#default_value' => $data['zendesk_ticket'],
    ];
    $form['requester'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Requester Name'),
      '#default_value' => $data['requester_name'] ? $data['requester_name'] : NULL,
    ];
    $form['requester_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Requester Email'),
      '#default_value' => $data['requester_mail'] ? $data['requester_mail'] : NULL,
    ];
    $form['assignee_group'] = [
      '#type' => 'select',
      '#title' => $this->t('Assignee Group'),
      '#options' => [
        '28277907' => $this->t('Attorney English'),
        '28277917' => $this->t('Attorney Spanish'),
        '28241987' => $this->t('Client English'),
        '28241997' => $this->t('Client Spanish'),
        '28242007' => $this->t('Client French'),
      ],
    ];

    $user_ids = \Drupal::entityQuery('user')
      ->condition('status', 1)
      ->condition('roles', 'consultant')
      ->execute();
    $users = User::loadMultiple($user_ids);

    foreach ($users as $user) {
      if (!empty($user->field_zendesk_id->value)) {
        $assignees[$user->field_zendesk_id->value] = $user->getUsername();
      }
    }

    $form['assignee'] = [
      '#type' => 'select',
      '#title' => $this->t('Assignee'),
      '#options' => $assignees,
    ];
    if (isset($param['consultant'])) {
      $consultant = User::load($params['consultant']);
      $form['assignee']['default_value'] = $consultant->field_zendesk_id->value;
    }
    $form['trademark_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Trademark'),
      '#default_value' => $data['trademark_name'],
    ];
    $form['order_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Order Number'),
      '#default_value' => $data['order_number'],
    ];
    $form['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notes'),
      '#default_value' => $data['notes'],
    ];
    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#default_value' => $data['subject'],
    ];
    $form['description'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Description'),
      '#default_value' => $description,
      '#format' => 'full_html',
      '#rows' => 40,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Ticket'),
      '#attributes' => ['class' => ['btn', 'btn--primary']],
      '#prefix' => '<div class="form-item">',
      '#suffix' => '</div>',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // dpm($form['assignee_group']);.
    $requester = $form_state->getValue('requester');
    $requester_email = $form_state->getValue('requester_email');
    $assignee_group = $form_state->getValue('assignee_group');
    $assignee = $form_state->getValue('assignee');
    $trademark_name = $form_state->getValue('trademark_name');
    $order_number = $form_state->getValue('order_number');
    $notes = $form_state->getValue('notes');
    $subject = $form_state->getValue('subject');
    $description = $form_state->getValue('description');
    $storage = $form_state->getStorage();
    $ticket_number = $form_state->getValue('zendesk_ticket');
    $data['ticket']['comment']['html_body'] = $description['value'];
    $data['ticket']['subject'] = $subject;
    $data['ticket']['assignee_id'] = $assignee;
    $data['ticket']['group_id'] = $assignee_group;
    $data['ticket']['priority'] = 'normal';
    $data['ticket']['requester']['name'] = $requester;
    $data['ticket']['requester']['email'] = $requester_email;

    // Trademark text.
    $data['ticket']['custom_fields'][] = [
      'id' => '31464407',
      'value' => $trademark_name,
    ];

    // Order Number.
    $data['ticket']['custom_fields'][] = [
      'id' => '31464417',
      'value' => $order_number,
    ];

    // Notes.
    $data['ticket']['custom_fields'][] = [
      'id' => '31844788',
      'value' => $notes,
    ];

    $zendesk_key = 'test_zendesk_key';
    $zendesk_url = 'https://igerent.zendesk.com';
    $zendesk_user = 'operations@igerent.com';
    $json = json_encode($data);
    $zendesk_key = 'test_zendesk_key';
    $zendesk_url = 'https://igerent.zendesk.com';
    $zendesk_user = 'operations@igerent.com';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    if (empty($ticket_number)) {
      curl_setopt($ch, CURLOPT_URL, $zendesk_url . '/api/v2/tickets.json');
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    }
    else {
      $data = [];
      $data['ticket']['comment']['html_body'] = $description['value'];
      $json = json_encode($data);
      curl_setopt($ch, CURLOPT_URL, $zendesk_url . '/api/v2/tickets/' . $ticket_number . '.json');
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    }
    curl_setopt($ch, CURLOPT_USERPWD, "$zendesk_user/token:$zendesk_key");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-type: application/json']);
    curl_setopt($ch, CURLOPT_USERAGENT, 'MozillaXYZ/1.0');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $output = curl_exec($ch);
    curl_close($ch);
    $response = json_decode($output);
    if (!isset($response->error)) {
      $ticket_id = 0;
      if (!empty($response->ticket->id)) {
        $ticket_id = $response->ticket->id;
      }
      drupal_set_message($this->t('Your support request has been submitted and has been assigned ticket number @id.',
        ['@id' => $ticket_id]));

      if (isset($storage['quote_id'])) {
        $quote_id = $storage['quote_id'];
        $quote = \Drupal::entityTypeManager()->getStorage('quote')->load($quote_id);
        $quote->field_zendesk_ticket = $ticket_id;
        $quote->save();
      }
    }
    else {
      drupal_set_message($this->t('Your support request was not properly submitted. Please try again later.'), 'error');
    }
  }

}
