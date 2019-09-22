<?php

namespace Drupal\commerce_bulk;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\commerce_product\Entity\ProductAttributeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\taxonomy\VocabularyInterface;

/**
 * Manipulates entity type information.
 *
 * This class contains primarily bridged hooks for compile-time or
 * cache-clear-time hooks. Runtime hooks should be placed in EntityOperations.
 */
class BulkEntityTypeInfo implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * EntityTypeInfo constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user.
   */
  public function __construct(AccountInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * Adds commerce_bulk operations on an entity that supports it.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity on which to define an operation.
   *
   * @return array
   *   An array of operation definitions.
   *
   * @see hook_entity_operation()
   */
  public function entityOperation(EntityInterface $entity) {
    $operations = [];
    if ($entity instanceof ProductAttributeInterface && $this->currentUser->hasPermission("administer commerce_product_attribute")) {
      $url = $entity->toUrl();
      $route = 'view.commerce_bulk_attributes.attribute_page';
      $route_parameters = $url->getRouteParameters();
      $options = $url->getOptions();
      $operations['commerce_bulk_operations'] = [
        'title' => $this->t('Bulk'),
        'weight' => -100,
        'url' => $url->fromRoute($route, $route_parameters, $options),
      ];
    }
    elseif ($entity instanceof VocabularyInterface && $this->currentUser->hasPermission("administer taxonomy")) {
      $url = $entity->toUrl();
      $route = 'view.commerce_bulk_taxonomy.vocabulary_page';
      $route_parameters = $url->getRouteParameters();
      $options = $url->getOptions();
      $operations['commerce_bulk_operations'] = [
        'title' => $this->t('Bulk'),
        'weight' => -100,
        'url' => $url->fromRoute($route, $route_parameters, $options),
      ];
    }

    return $operations;
  }

}
