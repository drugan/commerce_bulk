<?php

namespace Drupal\commerce_bulk\Plugin\Action;

use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Delete terms.
 *
 * @Action(
 *   id = "commerce_bulk_term_zdelete",
 *   label = @Translation("Delete terms"),
 *   type = "taxonomy_term"
 * )
 */
class TermDelete extends ConfigurableActionBase {

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
    $storage = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term');
    if ($ids = explode('|', $request->query->get('ids'))) {
      $terms = $storage->loadMultiple($ids);
      $form_state->set('terms', $terms);
      $list = '<ol>';
      foreach ($terms as $term) {
        $list .= '<li><h5>' . $term->getName() . '</h5></li>';
      }
      $list .= '</ol>';

      $form['warning'] = [
        '#markup' => new TranslatableMarkup('<h1>You are about to delete the following terms:</h1>' . $list),
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
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#id'] != 'edit-cancel') {
      if ($terms = $form_state->get('terms')) {
        foreach ($terms as $index => $term) {
          $term->delete();
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $terms) {
    if ($terms) {
      $ids = [];
      foreach ($terms as $term) {
        $ids[] = $term->id();
      }
      $url = $term->toUrl();
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
  public function execute($term = NULL) {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function access($term, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $term->access('delete', $account, TRUE);

    return $return_as_object ? $result : $result->isAllowed();
  }

}
