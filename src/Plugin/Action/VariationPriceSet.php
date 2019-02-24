<?php

namespace Drupal\commerce_bulk\Plugin\Action;

use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_price\Price;

/**
 * Set variation price.
 *
 * @Action(
 *   id = "commerce_bulk_variation_priceset",
 *   label = @Translation("Set price"),
 *   type = "commerce_product_variation"
 * )
 */
class VariationPriceSet extends ConfigurableActionBase {

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
      $form_state->set('variations', array_values($variations));
      $variation = end($variations);
      $price = $variation->getPrice()->toArray();
      $form['warning'] = [
        '#markup' => new TranslatableMarkup('<h1>Set New Unit Price or List Price for <span style="color:red">@count</span> variations</h1>', ['@count' => count($variations)]),
      ];
      $form['price'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['container-inline']],
      ];
      $form['price']['price_type'] = [
        '#type' => 'radios',
        '#options' => [
          'unit_price' => $this->t('Unit Price'),
          'list_price'  => $this->t('List Price'),
        ],
        '#default_value' => 'unit_price',
      ];
      $form['price']['price_value'] = [
        '#type' => 'commerce_price',
        '#title' => 'New Value',
        '#default_value' => $price,
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
      $set = $form_state->getValue('price_type') == 'list_price' ? 'setListPrice' : 'setPrice';
      $price = $form_state->getValue('price_value');
      $price = new Price($price['number'], $price['currency_code']);
      foreach ($form_state->get('variations') as $variation) {
        $variation->$set($price)->save();
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
