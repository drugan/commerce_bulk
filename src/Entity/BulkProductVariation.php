<?php

namespace Drupal\commerce_bulk\Entity;

use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Overrides the product variation entity class.
 */
class BulkProductVariation extends ProductVariation {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['sku'] = BaseFieldDefinition::create('string')
      ->setLabel(t('SKU'))
      ->setDescription(t('The unique, machine-readable identifier for a variation.'))
      ->setDefaultValueCallback('Drupal\commerce_bulk\BulkVariationsCreator::getAutoSku')
      ->setRequired(TRUE)
      ->addConstraint('ProductVariationSku')
      ->setSetting('display_description', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'commerce_bulk_sku',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
