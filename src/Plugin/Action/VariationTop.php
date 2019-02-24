<?php

namespace Drupal\commerce_bulk\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Move variations to the top of the list.
 *
 * @Action(
 *   id = "commerce_bulk_variation_top",
 *   label = @Translation("Move to top"),
 *   type = "commerce_product_variation"
 * )
 */
class VariationTop extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $variations) {
    if ($count = count($variations)) {
      $variation = reset($variations);
      $product = $variation->getProduct();
      $all_variations = $product->getVariations();
      foreach ($all_variations as $index => $variation) {
        if (in_array($variation, $variations)) {
          unset($all_variations[$index]);
          $count--;
          if (!$count) {
            break;
          }
        }
      }
      $all_variations = array_values(array_merge($variations, $all_variations));
      $product->setVariations($all_variations);
      $product->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function execute($variation = NULL) {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function access($variation, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $variation->access('update', $account, TRUE);

    return $return_as_object ? $result : $result->isAllowed();
  }

}
