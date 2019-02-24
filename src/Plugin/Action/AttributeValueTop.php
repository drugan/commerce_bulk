<?php

namespace Drupal\commerce_bulk\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Move variations to the top of the list.
 *
 * @Action(
 *   id = "commerce_bulk_attribute_value_top",
 *   label = @Translation("Move to top"),
 *   type = "commerce_product_attribute_value"
 * )
 */
class AttributeValueTop extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $attributes) {
    if (($attribute = reset($attributes)) && $values = $attribute->getAttribute()->getValues()) {
      $attribute = reset($values);
      unset($values);
      $weight = $attribute->getWeight();
      foreach ($attributes as $attribute) {
        $weight--;
        $attribute->setWeight($weight)->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function execute($attribute = NULL) {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function access($attribute, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $attribute->access('update', $account, TRUE);

    return $return_as_object ? $result : $result->isAllowed();
  }

}
