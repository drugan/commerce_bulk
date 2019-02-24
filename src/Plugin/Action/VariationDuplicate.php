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
 *   id = "commerce_bulk_variation_duplicate",
 *   label = @Translation("Duplicate variation"),
 *   type = "commerce_product_variation"
 * )
 */
class VariationDuplicate extends ConfigurableActionBase {

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
    if ($id = $request->query->get('id')) {
      $creator = \Drupal::service('commerce_bulk.variations_creator');
      $variation = $storage->load($id);
      $product = $variation->getProduct();
      $variations = $product->getVariations();
      $all = $creator->getAttributesCombinations($variations);
      $all['last_variation'] = $variation;
      $form['warning'] = [
        '#markup' => new TranslatableMarkup('<h1>You are about to create <span style="color:red">@not_used</span> variations:</h1><h3>The number of created variations can be narrowed down by unselecting some attribute options below. Use <mark>Ctrl</mark> or <mark>Shift</mark> keys to select multiple options. Note that default maximum possible number of variations to create in one go is <span style="color:red">500</span>. If you experience problems when creating large amount of variations try to change this number on <a href=":href" target="_blank">the variation type SKU widget settings</a>. Also, the server <span style="color:red">php.ini</span> configuration file settings can be increased in order to be able to perform this operation.</h3>',
        [
          '@not_used' => $all['not_used'],
          ':href' => '/admin/commerce/config/product-variation-types/' . $variation->bundle() . '/edit/form-display',
        ]),
      ];
      $form['max_execution_time'] = [
        '#type' => 'number',
        '#title' => new TranslatableMarkup('Temporarily increase php.ini <span style="color:red">max_execution_time</span> setting. Leave empty to apply default <span style="color:red">%max_execution_time</span> seconds.', ['%max_execution_time' => ini_get('max_execution_time')]),
        '#min' => '0',
        '#step' => '1',
      ];
      $options = $creator->getAttributeFieldOptionIds($variation);
      // First, move selected variation to the bottom.
      foreach ($variations as $index => $variation) {
        if ($variation == $all['last_variation']) {
          unset($variations[$index]);
          array_push($variations, $all['last_variation']);
          break;
        }
      }
      $form_state->set('kit', [
        'creator' => $creator,
        'product' => $product,
        'variations' => $variations,
        'all' => $all,
        'options' => $options,
      ]);
      $form['attributes'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['container-inline']],
      ];
      $values = [];
      foreach ($all['not_used_combinations'] as $index => $combination) {
        foreach ($combination as $key => $value) {
          $values[$key][$value] = $value;
        }
      }
      foreach ($options['options'] as $field_name => $value) {
        $definition = $variation->get($field_name)->getFieldDefinition();
        if (!$required = $definition->isRequired() && !isset($value['_none'])) {
          $value = ['_none' => ''] + $value;
        }
        foreach ($value as $key => $name) {
          if (!isset($values[$field_name][$key])) {
            // All combinations for this option are already used.
            unset($value[$key]);
          }
        }
        if ($size = count($value)) {
          $form['attributes'][$field_name] = [
            '#type' => 'select',
            '#title' => $definition->getLabel(),
            '#field_prefix' => "<mark>{$size}</mark>",
            '#multiple' => TRUE,
            '#options' => $value,
            '#size' => $size,
            '#default_value' => array_keys($value),
            '#required' => $required,
          ];
        }
      }
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
      if (($kit = $form_state->get('kit')) && $kit['all']['not_used_combinations']) {
        $values = $form_state->getValues();
        if ($values['max_execution_time']) {
          ini_set('max_execution_time', $values['max_execution_time']);
        };
        $attributes = [];
        foreach (array_keys($kit['options']['options']) as $key) {
          $attributes[$key] = $values[$key];
        }
        foreach ($kit['all']['not_used_combinations'] as $index => $combination) {
          foreach ($combination as $key => $value) {
            if (!isset($attributes[$key][$value])) {
              unset($kit['all']['not_used_combinations'][$index]);
              continue 2;
            }
          }
        }
        $count = count($kit['all']['not_used_combinations']);
        if ($count > 100) {
          $count = 0;
          $all = [];
          $all['all']['last_variation'] = $kit['all']['last_variation'];
          foreach ($kit['all']['not_used_combinations'] as $index => $combination) {
            $count++;
            $all['all']['not_used_combinations'][] = $combination;
            if ($count == 100) {
              if (isset($kit['variations'])) {
                $all['variations'] = $kit['variations'];
                unset($kit['variations']);
              }
              else {
                $all['variations'] = $kit['product']->getVariations();
              }
              $kit['product']->setVariations($kit['creator']->createAllProductVariations($kit['product'], [], $all))->save();
              $count = 0;
              $all['all']['not_used_combinations'] = [];
            }
          }
          if (!empty($all['all']['not_used_combinations'])) {
            $all['variations'] = $kit['product']->getVariations();
            $kit['product']->setVariations($kit['creator']->createAllProductVariations($kit['product'], [], $all))->save();
          }
        }
        else {
          $kit['product']->setVariations($kit['creator']->createAllProductVariations($kit['product'], [], $kit))->save();
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $variations) {
    if ($variations) {
      $variation = end($variations);
      $url = $variation->toUrl();
      $query = ['destination' => \Drupal::request()->getRequestUri(), 'id' => $variation->id()];
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
    $result = $variation->access('create', $account, TRUE);

    return $return_as_object ? $result : $result->isAllowed();
  }

}
