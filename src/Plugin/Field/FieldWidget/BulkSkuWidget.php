<?php

namespace Drupal\commerce_bulk\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextfieldWidget;
use Drupal\commerce_bulk\Entity\BulkProductVariation;
use Drupal\commerce_product\Entity\Product;

/**
 * Plugin implementation of the 'commerce_bulk_sku' widget.
 *
 * @FieldWidget(
 *   id = "commerce_bulk_sku",
 *   label = @Translation("Commerce Bulk SKU"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class BulkSkuWidget extends StringTextfieldWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'custom_label' => '',
      'uniqid_enabled' => TRUE,
      'more_entropy' => FALSE,
      'hide' => FALSE,
      'prefix' => 'sku-',
      'suffix' => '',
      'size' => 60,
      'placeholder' => '',
      'maximum' => 500,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $none = $this->t('None');
    $settings = $this->getSettings();
    $element['custom_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom label'),
      '#description' => $this->t('The label for the SKU field displayed on a variation edit form.'),
      '#default_value' => empty($settings['custom_label']) ? '' : $settings['custom_label'],
      '#placeholder' => $none,
    ];
    $element['uniqid_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable unique auto SKU values generation'),
      '#default_value' => $settings['uniqid_enabled'],
    ];
    $element['more_entropy'] = [
      '#type' => 'checkbox',
      '#title_display' => 'before',
      '#title' => $this->t('More unique'),
      '#description' => $this->t('If unchecked the SKU (without prefix and suffix) will look like this: <strong>@short</strong>. If checked, like this: <strong>@long</strong>. <a href=":uniqid_href" target="_blank">Read more</a>', [
        ':uniqid_href' => 'http://php.net/manual/en/function.uniqid.php',
        '@short' => uniqid(),
        '@long' => uniqid('', TRUE),
      ]),
      '#default_value' => $settings['more_entropy'],
      '#states' => [
        'visible' => [':input[name*="uniqid_enabled"]' => ['checked' => TRUE]],
      ],
    ];
    $element['hide'] = [
      '#type' => 'checkbox',
      '#title_display' => 'before',
      '#title' => $this->t('Hide SKU'),
      '#description' => $this->t('Hide the SKU field on a product add/edit forms adding SKU values silently at the background.'),
      '#default_value' => $settings['hide'],
      '#states' => [
        'visible' => [':input[name*="uniqid_enabled"]' => ['checked' => TRUE]],
      ],
    ];
    $element['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SKU prefix'),
      '#default_value' => $settings['prefix'],
      '#placeholder' => $none,
    ];
    $element['suffix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SKU suffix'),
      '#default_value' => $settings['suffix'],
      '#placeholder' => $none,
      '#description' => $this->t('Note if you leave all the above settings empty some services will become unavailable. For example, bulk creation of variations will be disabled on a product add or edit form.'),
    ];
    $element['size'] = [
      '#type' => 'number',
      '#title' => $this->t('Size of SKU field'),
      '#default_value' => $settings['size'],
      '#required' => TRUE,
      '#min' => 1,
    ];
    $element['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder'),
      '#default_value' => $settings['placeholder'],
      '#description' => $this->t('Text that will be shown inside the field until a value is entered. This hint is usually a sample value or a brief description of the expected format.'),
      '#placeholder' => $none,
    ];
    $element['maximum'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum'),
      '#default_value' => $settings['maximum'],
      '#description' => $this->t('The maximum of SKU values that might be generated in one go. Use it if you have troubles with bulk creation of variations on a product add or edit form. Helps to create a great number of variations by pressing <strong>Create N variations</strong> button several times. Note that <strong>the actual maximum of created values may differ</strong> as it depends on the number of attributes. Start from the minimum 3 SKU values to calculate the desired maximum.'),
      '#required' => TRUE,
      '#step' => 1,
      '#min' => 3,
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $none = $this->t('None');
    $settings = $this->getSettings();
    $sku = uniqid($settings['prefix'], $settings['more_entropy']) . $settings['suffix'];
    $settings['auto SKU sample'] = $settings['uniqid_enabled'] ? $sku : $none;
    $settings['hide'] = $settings['hide'] ? $this->t('Yes') : $this->t('No');
    unset($settings['uniqid_enabled'], $settings['more_entropy']);
    foreach ($settings as $name => $value) {
      $value = empty($settings[$name]) ? $none : $value;
      $summary[] = "{$name}: {$value}";
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $value = isset($items[$delta]->value) ? $items[$delta]->value : NULL;
    $settings = $this->getSettings();
    $custom_label = $this->getSetting('custom_label');
    $element['#title'] = !empty($custom_label) ? $custom_label : $element['#title'];
    $entity = $form_state->getFormObject()->getEntity();
    $variations = $variation = NULL;
    if ($entity instanceof BulkProductVariation) {
      $variation = $entity;
      $product = $variation->getProduct();
      $variations = $product->getVariations();
    }
    elseif ($entity instanceof Product) {
      $product = $entity;
      $variations = $product->getVariations();
      $variation = end($variations);
    }
    if ($variation && !$variation->id()) {
      $creator = \Drupal::service('commerce_bulk.variations_creator');
      $all = $creator->getNotUsedAttributesCombination($variations ?: [$variation]);
      if ($price = $all['last_variation']->getPrice()) {
        $form['price']['widget'][0]['#default_value'] = $price->toArray();
      }
      if ($price = $all['last_variation']->getListPrice()) {
        $form['list_price']['widget'][0]['has_value']['#default_value'] = TRUE;
        $form['list_price']['widget'][0]['value']['#default_value'] = $price->toArray();
      }
      foreach ($all['attributes']['options'] as $attribute_name => $options) {
        if (isset($form[$attribute_name]['widget']['#options'])) {
          $form[$attribute_name]['widget']['#options'] = array_filter($form[$attribute_name]['widget']['#options'],
            function ($k) use ($options) {
              return $k == '_none' || isset($options[$k]);
            }, ARRAY_FILTER_USE_KEY);
        }
      }
      if ($all['not_used_combination']) {
        foreach ($all['not_used_combination'] as $attribute_name => $id) {
          if (isset($form[$attribute_name]['widget']['#default_value'])) {
            $form[$attribute_name]['widget']['#default_value'] = [$id];
          }
        }
      }
      $setup_link = $this->t('<a href=":href" target="_blank">Set up default SKU.</a>', [':href' => '/admin/commerce/config/product-variation-types/' . $variation->bundle() . '/edit/form-display']);
      $element['#description'] = implode(' ', [$element['#description'], $setup_link]);
    }

    if (!empty($settings['uniqid_enabled']) && $settings['hide']) {
      $element['value']['#type'] = 'value';
      $element['value']['#value'] = $value;
    }
    else {
      $element['value'] = $element + [
        '#type' => 'textfield',
        '#default_value' => $value,
        '#size' => $this->getSetting('size'),
        '#placeholder' => $this->getSetting('placeholder'),
        '#maxlength' => $this->getFieldSetting('max_length'),
        '#attributes' => ['class' => ['js-text-full', 'text-full']],
      ];
    }

    return $element;
  }

}
