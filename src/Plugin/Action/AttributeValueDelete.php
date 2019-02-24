<?php

namespace Drupal\commerce_bulk\Plugin\Action;

use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Delete variations.
 *
 * @Action(
 *   id = "commerce_bulk_attribute_value_zdelete",
 *   label = @Translation("Delete attributes"),
 *   type = "commerce_product_attribute_value"
 * )
 */
class AttributeValueDelete extends ConfigurableActionBase {

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
    $storage = \Drupal::service('entity_type.manager')->getStorage('commerce_product_attribute_value');
    if ($ids = explode('|', $request->query->get('ids'))) {
      $attributes = $storage->loadMultiple($ids);
      $form_state->set('attributes', $attributes);
      $list = '<ol>';
      foreach ($attributes as $attribute) {
        $list .= '<li><h5>' . $attribute->getName() . '</h5></li>';
      }
      $list .= '</ol>';
      $form['warning'] = [
        '#markup' => new TranslatableMarkup('<h2>You are about to delete the following attributes:</h2>' . $list),
      ];
      $form['strong_warning'] = [
        '#markup' => new TranslatableMarkup('<h3>Ensure the attributes above are <span style="color:red">NOT</span> in use on some existing product variation.</h3><h3 style="color:red">After this operation your life will never be the same.</h3>'),
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
      if ($selected_attributes = $form_state->get('attributes')) {
        foreach ($selected_attributes as $attribute) {
          $attribute->delete();
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $attributes) {
    if ($attributes) {
      $ids = [];
      foreach ($attributes as $attribute) {
        $ids[] = $attribute->id();
      }
      $query = [
        'destination' => \Drupal::request()->getRequestUri(),
        'ids' => implode('|', $ids),
      ];
      $path = Url::fromUserInput('/admin/config/system/actions/configure/' . $this->getPluginId(), ['query' => $query])->toString();
      $response = new RedirectResponse($path);
      $response->send();
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
    $result = $attribute->access('delete', $account, TRUE);

    return $return_as_object ? $result : $result->isAllowed();
  }

}
