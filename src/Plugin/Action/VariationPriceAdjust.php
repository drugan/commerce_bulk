<?php

namespace Drupal\commerce_bulk\Plugin\Action;

use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_price\Price;

/**
 * Adjust variation price.
 *
 * @Action(
 *   id = "commerce_bulk_variation_priceadjust",
 *   label = @Translation("Adjust price"),
 *   type = "commerce_product_variation"
 * )
 */
class VariationPriceAdjust extends ConfigurableActionBase {

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
      $form['warning'] = [
        '#markup' => new TranslatableMarkup('<h1>Adjust Unit Price or List Price for <span style="color:red">@count</span> variations</h1><mark>Note if the result of adjusting is a negative price it will be converted to a <span style="color:red">0</span> price.</mark>', ['@count' => count($variations)]),
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
      $form['price']['adjust_op'] = [
        '#type' => 'select',
        '#options' => [
          'add' => $this->t('Add'),
          'subtract'  => $this->t('Subtract'),
        ],
      ];
      $form['price']['adjust_value'] = [
        '#type' => 'number',
        '#min' => '0.000001',
        '#step' => '0.000001',
      ];
      $form['price']['adjust_type'] = [
        '#type' => 'select',
        '#options' => [
          'fixed_number'  => $this->t('Fixed Number'),
          'percentage' => $this->t('Percentage'),
        ],
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
      $values = $form_state->getValues();
      if (!is_numeric($value = $values['adjust_value'])) {
        \Drupal::messenger()->AddError($this->t('The inserted adjust value is not numeric.'));
        return;
      }
      $set = $values['price_type'] == 'list_price' ? 'setListPrice' : 'setPrice';
      $get = $values['price_type'] == 'list_price' ? 'getListPrice' : 'getPrice';
      $op = $values['adjust_op'] == 'add' ? 'add' : 'subtract';

      foreach ($form_state->get('variations') as $variation) {
        if (!$price = $variation->$get()) {
          continue;
        }
        if ($values['adjust_type'] == 'fixed_number') {
          $adjust_price = new Price($value, $price->getCurrencyCode());
        }
        else {
          $adjust_price = $price->divide('100')->multiply($value);
        }
        $price = $price->$op($adjust_price);
        if ($price->isNegative()) {
          $price = $price->multiply('0');
        }
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
    $result = $variation->access('update', $account, TRUE);

    return $return_as_object ? $result : $result->isAllowed();
  }

}
