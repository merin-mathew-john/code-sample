<?php

namespace Drupal\ige_quote;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class ServiceProvider.
 */
class ServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('entity_print.filename_generator');
    $definition->setClass('Drupal\ige_checkout\FilenameGenerator')
      ->addArgument(new Reference('transliteration'));
  }

}
