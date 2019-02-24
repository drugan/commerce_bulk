<?php

namespace Drupal\commerce_bulk\Plugin\Action;

use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Delete variations.
 *
 * @Action(
 *   id = "commerce_bulk_variation_zdelete",
 *   label = @Translation("Delete variations"),
 *   type = "commerce_product_variation"
 * )
 */
class VariationDelete extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $request = \Drupal::request();
    $storage = \Drupal::service('entity_type.manager')->getStorage('commerce_product_variation');
    if ($ids = explode('|', $request->query->get('ids'))) {
      $variations = $storage->loadMultiple($ids);
      $form_state->set('variations', $variations);
      $list = '<ol>';
      foreach ($variations as $variation) {
        $list .= '<li><h5>' . $variation->getTitle() . '</h5></li>';
      }
      $list .= '</ol>';
      $form_state->set('product', $variation->getProduct());

      $form['warning'] = [
        '#markup' => new TranslatableMarkup('<h1>You are about to delete the following variations:</h1>' . $list),
      ];
      $form['strong_warning'] = [
        '#markup' => new TranslatableMarkup('<h2 style="color:red">After this operation your life will never be the same.</h2>'),
      ];
      $form['cancel'] = [
        '#type' => 'submit',
        '#value' => 'CANCEL AND BACK',
        '#weight' => 1000,
      ];
      // Remove the "Action was applied to N items" message.
      \Drupal::messenger()->deleteByType('status');
      // TODO: delete the fix below after a while.
      $storage = \Drupal::entityTypeManager()->getStorage('commerce_product_variation');
      $product = $variation->getProduct();
      $ids = $product->getVariationIds();
      $count = 0;
      foreach ($storage->loadByproperties(['product_id' => $product->id()]) as $variation) {
        if (!in_array($variation->id(), $ids)) {
          $variation->delete();
          $count++;
        }
      }
    }
    $count && \Drupal::messenger()->addWarning(new TranslatableMarkup('Deleted %count orphaned variations. See more: <a href=":href" target="_blank">https://www.drupal.org/project/commerce_bulk/issues/3027034</a>', ['%count' => $count, ':href' => 'https://www.drupal.org/project/commerce_bulk/issues/3027034']));

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#id'] != 'edit-cancel') {
      if ($seleted_variations = $form_state->get('variations')) {
        $product = $form_state->get('product');
        $variations = $product->getVariations();
        foreach ($variations as $index => $variation) {
          if (in_array($variation, $seleted_variations)) {
            unset($variations[$index]);
            $variation->delete();
          }
        }
        $product->setVariations(array_values($variations))->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $variations) {
    if ($variations) {
      $ids = [];
      foreach ($variations as $variation) {
        $ids[] = $variation->id();
      }
      $url = $variation->toUrl();
      $query = [
        'destination' => \Drupal::request()->getRequestUri(),
        'ids' => implode('|', $ids),
      ];
      $path = $url::fromUserInput('/admin/config/system/actions/configure/' . $this->getPluginId(), ['query' => $query])->toString();
      $response = new RedirectResponse($path);
      $response->send();
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
    $result = $variation->access('delete', $account, TRUE);

    return $return_as_object ? $result : $result->isAllowed();
  }

}
