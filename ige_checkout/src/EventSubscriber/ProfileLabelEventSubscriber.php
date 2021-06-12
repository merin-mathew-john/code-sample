<?php
namespace Drupal\ige_checkout\EventSubscriber;

use Drupal\profile\Event\ProfileEvents;
use Drupal\profile\Event\ProfileLabelEvent;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProfileLabelEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  public static function getSubscribedEvents() {
    return [
      ProfileEvents::PROFILE_LABEL => 'profileLabel'
    ];
  }

  /**
   * Subscribe to the user login event dispatched.
   */
  public function profileLabel(ProfileLabelEvent $event) {
    $event->setLabel($event->getProfile()->field_title->value);
    return $event;
  }

}
