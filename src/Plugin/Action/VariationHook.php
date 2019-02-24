<?php

namespace Drupal\commerce_bulk\Plugin\Action;

use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Pass variation to a hook.
 *
 * @Action(
 *   id = "commerce_bulk_variation_hook",
 *   label = @Translation("Pass to hook"),
 *   type = "commerce_product_variation"
 * )
 */
class VariationHook extends ConfigurableActionBase {

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
    if ($ids = explode('|', $request->query->get('ids'))) {
      $storage = \Drupal::service('entity_type.manager')->getStorage('commerce_product_variation');
      $variations = $storage->loadMultiple($ids);
      $variation = reset($variations);
      $product = $variation->getProduct();
      $readmehelp = readmehelp_converter_service();
      $path = $readmehelp->moduleHandler->getModuleDirectories()['commerce_bulk'] . '/commerce_bulk.module';

      $form_state->set('kit', [
        'variations' => $variations,
        'product' => $product,
        'product_type' => $product->bundle(),
      ]);
      $form['warning'] = [
        '#markup' => new TranslatableMarkup('<h1>Add custom data</h1><h3>You are about to invoke the <mark>hook_commerce_bulk_variation_alter()</mark> on <span style="color:red;font-weight:bolder;">@count</span> variations.</h3><h3>See example hook implementation in the commerce_bulk.module file:</h3><div style="border:1px solid grey">' . $readmehelp->highlightPhp($path, 18, 12) . '</div>', ['@count' => count($variations)]),
      ];
      $form['data'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Optional JSON or XML data'),
        '#rows' => 20,
      ];
      $form['cancel'] = [
        '#type' => 'submit',
        '#value' => 'CANCEL AND BACK',
        '#weight' => 1000,
      ];
      // Remove the "Action was applied to N items" message.
      \Drupal::messenger()->deleteByType('status');
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#id'] != 'edit-cancel') {
      $kit = $form_state->get('kit');
      \Drupal::moduleHandler()->alter('commerce_bulk_variation', $kit['variations'], $form_state->getValue('data'), $kit['product_type']);
      $kit['variations'] = $kit['product']->getVariations();
      $kit['product']->setVariations($kit['variations']);
      $kit['product']->save();
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
