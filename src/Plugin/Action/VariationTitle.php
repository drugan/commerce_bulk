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
 *   id = "commerce_bulk_variation_title",
 *   label = @Translation("Change title"),
 *   type = "commerce_product_variation"
 * )
 */
class VariationTitle extends ConfigurableActionBase {

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
      $titles = '';
      foreach ($variations as $variation) {
        $titles .= $variation->getTitle() . PHP_EOL;
      }
      $form['warning'] = [
        '#markup' => new TranslatableMarkup('<h2>Note that for this action to work you should <span style="color:red">uncheck</span> the <a href=":href" target="_blank">Generate variation titles based on attribute values</a> checkbox.<h2>', [
          ':href' => $variation->toUrl()::fromUserInput("/admin/commerce/config/product-variation-types/{$variation->bundle()}/edit")->toString(),
        ]),
      ];
      $form['titles'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Titles'),
        '#default_value' => $titles,
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
      $titles = explode(PHP_EOL, trim($form_state->getValue('titles')));
      foreach ($form_state->get('variations') as $index => $variation) {
        if ($title = isset($titles[$index]) ? trim($titles[$index]) : FALSE) {
          $variation->setTitle($title)->save();
        }
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
