<?php

namespace Drupal\commerce_bulk\Plugin\Action;

use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Duplicate attribute value.
 *
 * @Action(
 *   id = "commerce_bulk_attribute_value_name",
 *   label = @Translation("Change or add names"),
 *   type = "commerce_product_attribute_value"
 * )
 */
class AttributeValueName extends ConfigurableActionBase {

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
      $names = '';
      foreach ($attributes as $attribute) {
        $names .= $attribute->getName() . PHP_EOL;
      }
      $values = $attribute->getAttribute()->getValues();
      $form_state->set('attribute', end($values));
      $form_state->set('attributes', $attributes);
      $readmehelp = readmehelp_converter_service();
      $path = $readmehelp->moduleHandler->getModuleDirectories()['commerce_bulk'] . '/commerce_bulk.module';
      $form['warning'] = [
        '#markup' => new TranslatableMarkup('<h2>Change and / or add attributes <span style="color:red">Name</span>s. New <em>Names</em> should be inserted after the last existing: one <em>Name</em> on each line.'),
      ];
      $form['names'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Names'),
        '#default_value' => $names,
        '#rows' => 20,
      ];
      $form['data_warning'] = [
        '#markup' => new TranslatableMarkup('<h3><mark>Tip:</mark> Optionally, you can pass <em>XML</em> or <em>JSON</em> data and alter each attribute value in the <mark>YOUR_MODULE_commerce_bulk_attribute_value_alter()</mark>. See example hook implementation in the commerce_bulk.module file:</h3><div style="border:1px solid grey">' . $readmehelp->highlightPhp($path, 59, 8) . '</div>'),
      ];
      $form['data'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Optional JSON or XML data'),
        '#rows' => 1,
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
      if ($names = explode(PHP_EOL, trim($form_state->getValue('names')))) {
        $module_handler = \Drupal::moduleHandler();
        $data = $form_state->get('data');
        $attributes = array_values($form_state->get('attributes'));
        $form_state->set('attributes', NULL);
        $attribute = end($attributes);
        $weight = $form_state->get('attribute')->getWeight();
        foreach ($names as $index => $name) {
          if ($name = trim($name)) {
            if (isset($attributes[$index])) {
              $value = $attributes[$index];
            }
            else {
              $value = $attribute->createDuplicate();
              $weight++;
              $value->setWeight($weight);
            }
            $module_handler->alter('commerce_bulk_attribute_value', $value, $name, $data);
            $value->setName($name)->save();
          }
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
    $result = $attribute->access('update', $account, TRUE);

    return $return_as_object ? $result : $result->isAllowed();
  }

}
