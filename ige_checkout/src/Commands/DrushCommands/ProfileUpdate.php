<?php

namespace Drupal\ige_checkout\Commands\DrushCommands;

use Drush\Commands\DrushCommands;
use Drupal\profile\Entity\Profile;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
class ProfileUpdate extends DrushCommands {

  /**
   * Update the owner of profile entities.
   *
   * @command ige_checkout:updateprofileowner
   * @aliases ige_checkout_upo
   * @usage ige_checkout:updateprofileowner
   */
  public function getProfileOrder() {
    $profiles = db_query('
      SELECT profile_id
      FROM profile
    ')->fetchAll();
    foreach ($profiles as $profile) {
      $prof_entity = Profile::load($profile->profile_id);
      if ($prof_entity) {
        $prof_entity->setDefault(TRUE);
        $prof_entity->setActive(TRUE);
        $prof_entity->set('field_user', $prof_entity->getOwnerId());
        $prof_entity->setData('copy_to_address_book', TRUE);
        $prof_entity->save();
        echo 'Updated ' . $profile->profile_id;
      }
    }
    return TRUE;
  }

  /**
   * Update the owner of profile entities.
   *
   * @command ige_checkout:deletedraftorder
   * @aliases ige_checkout_ddo
   * @usage ige_checkout:deletedraftorder
   */
  public function deleteDraftOrder() {
    $orders = \Drupal::entityTypeManager()->getStorage('commerce_order')
      ->loadByProperties(['state' => 'draft']);
    foreach ($orders as $order) {
      $order->delete();
      echo 'Updated ' . $order->id;
    }
    return TRUE;
  }

  /**
   * Update the owner of profile entities.
   *
   * @command ige_checkout:updatetracker
   * @aliases ige_checkout_ut
   * @usage ige_checkout:updatetracker
   */
  public function trackerUpdate() {
    $trackers = db_query('
      SELECT field_legacy_type_value, entity_id
      FROM service_tracker__field_legacy_type
    ')->fetchAll();

    foreach ($trackers as $item) {
      $tracker = \Drupal::entityManager()
        ->getStorage('service_tracker')
        ->load($item->entity_id);
      $legacy_id = $item->field_legacy_type_value;

      $orders = db_query('
        SELECT entity_id
        FROM commerce_order__field_legacy_order_id
        WHERE field_legacy_order_id_value = :value
      ', [':value' => $legacy_id])->fetchAll();

      if ($orders['0']->entity_id) {
        $tracker->field_order->target_id = $orders['0']->entity_id;
        print_r($orders['0']->entity_id);
        $tracker->save();
        print_r('updated ' . $tracker->id());
      }
    }

  }

}
