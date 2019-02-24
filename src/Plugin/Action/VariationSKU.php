<?php

namespace Drupal\commerce_bulk\Plugin\Action;

use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Duplicate variation.
 *
 * @Action(
 *   id = "commerce_bulk_variation_sku",
 *   label = @Translation("Change SKUs"),
 *   type = "commerce_product_variation"
 * )
 */
class VariationSKU extends ConfigurableActionBase {

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
      $readmehelp = readmehelp_converter_service();
      $path = $readmehelp->moduleHandler->getModuleDirectories()['commerce_bulk'] . '/commerce_bulk.module';
      $variations = $storage->loadMultiple($ids);
      $form_state->set('variations', array_values($variations));
      $skus = '';
      foreach ($variations as $variation) {
        $skus .= $variation->getSku() . PHP_EOL;
      }
      $form['warning'] = [
        '#markup' => new TranslatableMarkup('<h1>Note that each SKU must be <span style="color:red">unique</span> accross all SKUs existing on the current Drupal Commerce site and not exceed <span style="color:red">60</span> characters length.</h1><h3><mark>Tip:</mark> SKU for each variation can also be programmatically set in the <mark>hook_bulk_creator_sku_alter()</mark> for your needs while executing <mark>Duplicate variation</mark> action. See example hook implementation in the commerce_bulk.module file:</h3><div style="border:1px solid grey">' . $readmehelp->highlightPhp($path, 41, 6) . '</div>'),
      ];
      $form['skus'] = [
        '#type' => 'textarea',
        '#title' => $this->t('SKUs'),
        '#default_value' => $skus,
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
      $storage = \Drupal::service('entity_type.manager')->getStorage('commerce_product_variation');
      $skus = explode(PHP_EOL, trim($form_state->getValue('skus')));
      $list = '';
      foreach ($form_state->get('variations') as $index => $variation) {
        $sku = isset($skus[$index]) ? trim($skus[$index]) : FALSE;
        if ($sku && (strlen($sku) < 61) && !$storage->loadBySku($sku)) {
          $variation->setSku($sku)->save();
        }
        else {
          $list .= "<li>{$sku}</li>";
        }
      }
      if ($list) {
        \Drupal::messenger()->addWarning(new TranslatableMarkup("<h3>The following SKUs were rejected as they already exist or more than 60 characters:</h3><ul>{$list}</ul>"));
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
