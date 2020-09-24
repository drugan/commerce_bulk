<?php

namespace Drupal\commerce_bulk\Plugin\Action;

use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Delete terms.
 *
 * @Action(
 *   id = "commerce_bulk_order_zanonymize",
 *   label = @Translation("Anonymize Orders"),
 *   type = "commerce_order"
 * )
 */
class OrderAnonymize extends ConfigurableActionBase {

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
    $storage = \Drupal::service('entity_type.manager')->getStorage('commerce_order');
    if ($ids = explode('|', $request->query->get('ids'))) {
      $default_fields = [
        'ip_address',
        'billing_profile',
        'shipping_profile',
        'data',
        'mail',
        'field_pakiautomaadid',
      ];
      $bundles = $fields = [];
      $orders = $storage->loadMultiple($ids);
      $form_state->set('orders', $orders);
      $list = '<ol>';
      foreach ($orders as $order) {
        $list .= '<li><h5>' . $order->id() . '</h5></li>';
        $bundle = $order->bundle();
        if (!isset($bundles[$bundle])) {
          $bundles[$bundle] = $bundle;
          foreach ($order->getFieldDefinitions() as $id => $definition) {
            $fields[$id] = $definition->getLabel();
          }
        }
      }
      $list .= '</ol>';
      $form['warning'] = [
        '#markup' => new TranslatableMarkup('<h1>You are about to anonymize the following orders:</h1>' . $list),
      ];
      $form['strong_warning'] = [
        '#markup' => new TranslatableMarkup('<h2 style="color:red">After this operation your life will never be the same.</h2>'),
      ];

      $form['fields'] = [
        '#type' => 'select',
        '#title' => $this->t('Fields'),
        '#description' => $this->t('Select fields to anonymize. Use <mark>Ctrl</mark> or <mark>Shift</mark> keys to select multiple options.'),
        '#multiple' => TRUE,
        '#options' => $fields,
        '#size' => count($fields),
        '#default_value' => $default_fields,
        '#required' => TRUE,
      ];

      $form['order_age'] = [
        '#type' => 'number',
        '#title' => $this->t('Order Age'),
        '#description' => $this->t('Anonymize only orders older than a number of days specified. Leave empty to anonymize all selected orders.'),
        '#min' => '1',
        '#step' => '1',
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
      $orders = (array) $form_state->get('orders');
      $fields = (array) $form_state->getValue('fields');
      $age = (int) $form_state->getValue('order_age');
      $this->anonymizeEntities($orders, $fields, $age);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function anonymizeEntities(array $entities, array $fields, int $age = 0) {
    $age = $age ? $age * 24 * 60 * 60 : $age;
    $completed = \time() - $age;
    $time = current($entities) instanceof OrderInterface ? 'Completed' : 'Changed';
    foreach ($entities as $entity) {
      if ($age && $entity->{'get' . $time . 'Time'}() > $completed) {
        continue;
      }
      $save = FALSE;
      foreach ($fields as $name) {
        if (!$entity->hasField($name)) {
          continue;
        }
        $item = $entity->get($name);
        if ($value = $item->getValue()) {
          $save = TRUE;
          $this->anonymizeData($value);
          $entity->set($name, $value);
        }
      }
      $save && $entity->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function anonymizeData(&$data) {
    foreach ($data as $index => &$value) {
      if ($value === NULL || is_bool($value)) {
        continue;
      }
      if (is_array($value)) {
        $this->anonymizeData($value);
      }
      elseif ($index == 'target_id' || $index == 'target_revision_id') {
        $value = NULL;
      }
      elseif (is_numeric($value)) {
        $str = $value[0] != 1 ? '1' : '2';
        $value = str_pad($str, strlen($value), "0");
      }
      elseif (is_string($value)) {
        $vals = array_merge(range(65, 90), range(97, 122), range(48, 57));
        $max = count($vals) - 1;
        $str = chr(mt_rand(97, 122));
        for ($i = 1; $i < strlen($value); $i++) {
          $str .= chr($vals[mt_rand(0, $max)]);
        }
        $value = $str;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $orders) {
    if ($orders) {
      $ids = [];
      foreach ($orders as $order) {
        $ids[] = $order->id();
      }
      $url = $order->toUrl();
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
  public function access($order, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $order->access('update', $account, TRUE);

    return $return_as_object ? $result : $result->isAllowed();
  }

}
