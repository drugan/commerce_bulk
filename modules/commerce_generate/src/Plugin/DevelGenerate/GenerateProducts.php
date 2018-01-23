<?php

namespace Drupal\commerce_generate\Plugin\DevelGenerate;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\devel_generate\DevelGenerateBase;
use Drush\Utils\StringUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_bulk\BulkVariationsCreatorInterface;

/**
 * Provides a GenerateProducts plugin.
 *
 * @DevelGenerate(
 *   id = "products",
 *   label = @Translation("products"),
 *   description = @Translation("Generate a given number of products. Optionally delete current products."),
 *   url = "products",
 *   permission = "administer devel_generate",
 *   settings = {
 *     "kill" = FALSE,
 *     "num" = 1,
 *     "batch" = 10,
 *     "title_prefix" = @Translation("Product"),
 *     "title_length" = 4,
 *     "price_min" = "0.01",
 *     "price_max" = "9.99",
 *     "price_per_variation" = FALSE,
 *   }
 * )
 */
class GenerateProducts extends DevelGenerateBase implements ContainerFactoryPluginInterface {

  /**
   * The store storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storeStorage;
  /**
   * The product storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $productStorage;

  /**
   * The product type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $productTypeStorage;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The url generator service.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The variations creator service.
   *
   * @var \Drupal\commerce_bulk\BulkVariationsCreatorInterface
   */
  protected $creator;

  /**
   * Constructs a new GenerateProducts object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $store_storage
   *   The store storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $product_storage
   *   The product storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $product_type_storage
   *   The product type storage.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The url generator service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\commerce_bulk\BulkVariationsCreatorInterface $variations_creator
   *   The variations creator service.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityStorageInterface $store_storage, EntityStorageInterface $product_storage, EntityStorageInterface $product_type_storage, LanguageManagerInterface $language_manager, UrlGeneratorInterface $url_generator, DateFormatterInterface $date_formatter, BulkVariationsCreatorInterface $variations_creator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->storeStorage = $store_storage;
    $this->productStorage = $product_storage;
    $this->productTypeStorage = $product_type_storage;
    $this->languageManager = $language_manager;
    $this->urlGenerator = $url_generator;
    $this->dateFormatter = $date_formatter;
    $this->creator = $variations_creator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $entity_manager = $container->get('entity.manager');
    return new static(
      $configuration, $plugin_id, $plugin_definition,
      $entity_manager->getStorage('commerce_store'),
      $entity_manager->getStorage('commerce_product'),
      $entity_manager->getStorage('commerce_product_type'),
      $container->get('language_manager'),
      $container->get('url_generator'),
      $container->get('date.formatter'),
      $container->get('commerce_bulk.variations_creator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $stores = $this->storeStorage->loadMultiple();
    if (empty($stores)) {
      $create_url = $this->urlGenerator->generateFromRoute('entity.commerce_store.add_page');
      $this->setMessage($this->t('You do not have any stores to which generated products could be assigned. <a href=":create-type">Go create a new store</a>', [':create-type' => $create_url]), 'error', FALSE);
      return;
    }

    $types = $this->productTypeStorage->loadMultiple();
    if (empty($types)) {
      $create_url = $this->urlGenerator->generateFromRoute('entity.commerce_product_type.add_form');
      $this->setMessage($this->t('You do not have any product types that can be generated. <a href=":create-type">Go create a new product type</a>', [':create-type' => $create_url]), 'error', FALSE);
      return;
    }

    $options = [];
    foreach ($stores as $store) {
      $options[$store->id()] = [
        'type' => ['#markup' => $store->label()],
        'store_type' => ['#markup' => $store->bundle()],
      ];
    }
    $header = [
      'type' => $this->t('Assign products to stores'),
      'store_type' => [
        'data' => $this->t('Machine name'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
    ];
    $form['stores'] = [
      '#prefix' => $this->t('<h6>Select at least one of the stores below:</h6>'),
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
    ];

    $options = [];
    foreach ($types as $type) {
      $options[$type->id()] = [
        'type' => ['#markup' => $type->label()],
        'variation_type' => ['#markup' => $type->getVariationTypeId()],
      ];
    }
    $header = [
      'type' => $this->t('Product type'),
      'variation_type' => [
        'data' => $this->t('Machine name'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
    ];

    $form['product_types'] = [
      '#prefix' => $this->t('<h6>Select at least one of the product types below:</h6>'),
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
    ];

    $form['kill'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<strong>Delete ALL (<mark>sic!</mark>) products</strong> in the selected stores or of the selected product types before generating new ones. As an example you may delete all products belonging to a particular store by selecting this store (without product types) and setting the number of products to generate to 0 and then pressing the <strong>Generate</strong> button. By the way, the internal name of this checkbox is <strong>"kill"</strong> so, be careful when considering which products to delete. <mark>You are warned.</mark>'),
      '#default_value' => $this->getSetting('kill'),
    ];

    $form['num'] = [
      '#type' => 'number',
      '#title' => $this->t('The total number of products to generate.'),
      '#default_value' => $this->getSetting('num'),
      '#required' => TRUE,
      '#step' => 1,
      '#min' => 0,
    ];

    $form['batch'] = [
      '#type' => 'number',
      '#title' => $this->t('The treshold for batch.'),
      '#description' => $this->t("The number of products at which to start a batch products' generating process instead of doing this in one go. Environments where the module works may differ, the structure of product types may differ even more. So, adjust the treshold for your needs, depending on capacity of the server and products you are going to generate."),
      '#default_value' => $this->getSetting('batch'),
      '#required' => TRUE,
      '#step' => 1,
      '#min' => 2,
    ];

    $form['title_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The title prefix'),
      '#description' => $this->t('The word to prepend to a randomly generated product title.'),
      '#default_value' => $this->getSetting('title_prefix'),
    ];

    $form['title_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum number of words in titles'),
      '#default_value' => $this->getSetting('title_length'),
      '#required' => TRUE,
      '#step' => 1,
      '#min' => 1,
      '#max' => 255,
    ];

    $form['price_min'] = [
      '#type' => 'number',
      '#title' => $this->t('The minimum of the randomly generated price.'),
      '#default_value' => $this->getSetting('price_min'),
      '#min' => '0.01',
      '#step' => '0.01',
    ];

    $form['price_max'] = [
      '#type' => 'number',
      '#title' => $this->t('The maximum of the randomly generated price.'),
      '#default_value' => $this->getSetting('price_max'),
      '#step' => '0.01',
      '#min' => '0.01',
    ];

    $form['price_per_variation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set random price per variation instead of per product basis.'),
      '#default_value' => $this->getSetting('price_per_variation'),
    ];

    $form['price_number'] = [
      '#type' => 'value',
      '#value' => '1.00',
    ];

    $form['owner'] = [
      '#type' => 'commerce_entity_select',
      '#title' => t('Product owner'),
      '#target_type' => 'user',
      '#description' => $this->t('The user to assign as owner for generated products. Leave empty for randomly selected users.'),
    ];

    $options = [1 => $this->t('Now')];
    foreach ([3600, 86400, 604800, 2592000, 31536000] as $interval) {
      $options[$interval] = $this->dateFormatter->formatInterval($interval, 1) . ' ' . $this->t('ago');
    }
    $form['time_range'] = [
      '#type' => 'select',
      '#title' => $this->t('How far back in time should the products be dated?'),
      '#description' => $this->t('Product creation dates will be distributed randomly from the current time, back to the selected time.'),
      '#options' => $options,
      '#default_value' => 604800,
    ];

    $options = [];
    // We always need a language.
    $languages = $this->languageManager->getLanguages(LanguageInterface::STATE_ALL);
    foreach ($languages as $langcode => $language) {
      $options[$langcode] = $language->getName();
    }

    $form['add_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Set language on products'),
      '#multiple' => TRUE,
      '#description' => $this->t('Requires locale.module'),
      '#options' => $options,
      '#default_value' => [
        $this->languageManager->getDefaultLanguage()->getId(),
      ],
    ];

    $form['#redirect'] = FALSE;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsFormValidate(array $form, FormStateInterface $form_state) {
    if ($form_state->getValue('kill') && !$form_state->getValue('num')) {
      return;
    }
    if (!array_filter($form_state->getValue('stores'))) {
      $form_state->setErrorByName('stores', $this->t('Please select at least one store!'));
    }
    if (!array_filter($form_state->getValue('product_types'))) {
      $form_state->setErrorByName('product_types', $this->t('Please select at least one product type!'));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function generateElements(array $values) {
    if ($values['batch'] > $values['num']) {
      $this->generateProducts($values);
    }
    else {
      $this->batchGenerateProducts($values);
    }
  }

  /**
   * {@inheritdoc}
   */
  private function generateProducts($values) {
    $values['product_types'] = array_filter($values['product_types']);
    $values['stores'] = array_filter($values['stores']);
    if (!empty($values['kill']) && ($values['product_types'] || $values['stores'])) {
      $this->productKill($values);
    }

    if ($values['product_types'] && $values['num']) {
      // Generate products.
      $this->prepareGenerateProduct($values);
      $start = time();
      for ($i = 1; $i <= $values['num']; $i++) {
        $this->generateSaveProduct($values);
        if ($this->isDrush8() && function_exists('drush_log') && $i % drush_get_option('feedback', 1000) == 0) {
          $now = time();
          drush_log(dt('Completed @feedback products (@rate products/min)', [
            '@feedback' => drush_get_option('feedback', 1000),
            '@rate' => (drush_get_option('feedback', 1000) * 60) / ($now - $start),
          ]), 'ok');
          $start = $now;
        }
      }
      $this->setMessage($this->formatPlural($values['num'], '1 product created.', 'Finished creating @count products'));
    }
  }

  /**
   * {@inheritdoc}
   */
  private function getRandomPrice(&$values) {
    $min = $values['price_min'];
    $max = $values['price_max'];
    $min = bccomp($min, $max) === -1 ? $min : $max;
    $max = bccomp($max, $min) === 1 ? $max : $min;
    if (bccomp($min, $max) === -1) {
      // http://php.net/manual/function.mt-getrandmax.php
      $min_calc = bcadd($min, mt_rand());
      $min_calc = bcdiv($min_calc, mt_getrandmax());
      $number = $values['price_number'] = bcmul($min_calc, bcsub($max, $min));
    }
    else {
      $number = $values['price_number'] = bcadd($max, 0);
    }

    // If some crazy numbers are submited then it may return scientific notation
    // number which is not supported by this module. So, to prevent crashes
    // return something sensible instead.
    return is_numeric($number) && $number > 0 ? $number : '1.11';
  }

  /**
   * {@inheritdoc}
   */
  protected function productKill($values) {
    $products = $in_stores = $in_product_types = [];
    if ($values['stores']) {
      $products = $in_stores = (array) $this->productStorage->loadByProperties(['stores' => $values['stores']]);
    }
    if ($values['product_types']) {
      $products = $in_product_types = (array) $this->productStorage->loadByProperties(['type' => $values['product_types']]);
    }
    if ($in_stores && $in_product_types) {
      $products = array_intersect_key($in_stores, $in_product_types);
    }

    if ($count = count($products)) {
      $this->productStorage->delete($products);
      $this->setMessage($this->t('Deleted %count products.', ['%count' => $count]));
    }
    else {
      $this->setMessage($this->t('Nothing to delete, skipped.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  private function batchGenerateProducts($values) {
    // Setup the batch operations and save the variables.
    $operations[] = [
      'devel_generate_operation', [$this, 'batchPrepareroduct', $values],
    ];

    // Add the kill operation.
    if ($values['kill']) {
      $operations[] = [
        'devel_generate_operation', [$this, 'batchProductKill', $values],
      ];
    }

    // Add the operations to create the products.
    for ($num = 0; $num < $values['num']; $num++) {
      $operations[] = [
        'devel_generate_operation', [
          $this,
          'batchGenerateSaveProduct',
          $values,
        ],
      ];
    }

    // Set the batch.
    $batch = [
      'title' => $this->t('Generating Products'),
      'operations' => $operations,
      'finished' => 'devel_generate_batch_finished',
      'file' => drupal_get_path('module', 'devel_generate') . '/devel_generate.batch.inc',
    ];
    batch_set($batch);
  }

  /**
   * {@inheritdoc}
   */
  public function batchPrepareroduct($vars, &$context) {
    $context['results'] = $vars;
    $context['results']['num'] = 0;
    $this->prepareGenerateProduct($context['results']);
  }

  /**
   * {@inheritdoc}
   */
  public function batchGenerateSaveProduct(&$vars, &$context) {
    $this->generateSaveProduct($context['results']);
    $context['results']['num']++;
  }

  /**
   * {@inheritdoc}
   */
  public function batchProductKill($vars, &$context) {
    $this->productKill($context['results']);
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareGenerateProduct(&$results) {
    if ($results['owner']) {
      $results['users'] = [$results['owner']];
    }
    else {
      $users = $this->getUsers();
      $results['users'] = $users;
    }
  }

  /**
   * Create one product. Used by both batch and non-batch code branches.
   */
  protected function generateSaveProduct(&$results) {
    if (!isset($results['time_range'])) {
      $results['time_range'] = 0;
    }
    $users = $results['users'];
    $title = $this->getRandom()->sentences(mt_rand(1, $results['title_length']), TRUE);
    $store = $this->storeStorage->load(array_rand(array_filter($results['stores'])));
    $code = $store->getDefaultCurrencyCode();
    $values = [
      'price' => new Price($this->getRandomPrice($results), $code),
    ];
    $product_type = array_rand(array_filter($results['product_types']));
    // Anonymous user cannot be assigned as product owner.
    $uid = $users[array_rand($users)] ?: $users[array_rand($users)];

    $product = $this->productStorage->create([
      'type' => $product_type,
      'title' => $results['title_prefix'] ? "{$results['title_prefix']} $title" : $title,
      'uid' => $uid,
      'created' => REQUEST_TIME - mt_rand(0, $results['time_range']),
      'langcode' => $this->getLangcode($results),
    ]);

    // See example usage of the commerce_generate property.
    // @see devel_generate_entity_insert()
    // @see commerce_generate_commerce_product_insert()
    $product->commerce_generate = $results;

    $variations = $this->creator->createAllProductVariations($product, $values);
    foreach ($variations as $variation) {
      // Generate custom field's sample value, such as variation image.
      $this->populateFields($variation);
      if ($results['price_per_variation']) {
        $variation->setPrice(new Price($this->getRandomPrice($results), $code));
      }
    }
    // Populate all the rest fields with sample values.
    $this->populateFields($product);
    // The populateFields() generates too much paragraphs, so do it here.
    $product->get('body')->setvalue($this->getRandom()->paragraphs(2));
    $product->setStores([$store]);
    $product->setVariations($variations);
    $product->save();
  }

  /**
   * Populate the fields on a given entity with sample values.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be enriched with sample field values.
   */
  public static function populateFields(EntityInterface $entity) {
    /** @var \Drupal\field\FieldConfigInterface[] $instances */
    $instances = entity_load_multiple_by_properties('field_config', ['entity_type' => $entity->getEntityType()->id(), 'bundle' => $entity->bundle()]);

    if ($skips = function_exists('drush_get_option') ? drush_get_option('skip-fields', '') : @$_REQUEST['skip-fields']) {
      foreach (explode(',', $skips) as $skip) {
        unset($instances[$skip]);
      }
    }

    foreach ($instances as $instance) {
      $field_storage = $instance->getFieldStorageDefinition();
      $field_name = $field_storage->getName();
      $type = $field_storage->getSetting('target_type');

      // These fields are populated in the ::generateSaveProduct() method.
      if ($type == 'commerce_product_attribute_value' || $field_name == 'stores' || $field_name == 'variations' || $field_name == 'body') {
        continue;
      }
      $max = $cardinality = $field_storage->getCardinality();
      if ($cardinality == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
        // Just an arbitrary number for 'unlimited'.
        $max = rand(1, 3);
      }
      $entity->$field_name->generateSampleItems($max);
    }
  }

  /**
   * Determine language based on $results.
   */
  protected function getLangcode($results) {
    if (isset($results['add_language'])) {
      $langcodes = $results['add_language'];
      $langcode = $langcodes[array_rand($langcodes)];
    }
    else {
      $langcode = $this->languageManager->getDefaultLanguage()->getId();
    }
    return $langcode;
  }

  /**
   * Retrieve 50 uids from the database.
   */
  protected function getUsers() {
    $users = [];
    $result = db_query_range("SELECT uid FROM {users}", 0, 50);
    foreach ($result as $record) {
      $users[] = $record->uid;
    }
    return $users;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Drush command a bit later.
   */
  public function validateDrushParams($args, $options = []) {
    $add_language = $this->isDrush8() ? drush_get_option('languages') : $options['languages'];
    if (!empty($add_language)) {
      $add_language = explode(',', str_replace(' ', '', $add_language));
      // Intersect with the enabled languages to make sure the language args
      // passed are actually enabled.
      $values['values']['add_language'] = array_intersect($add_language, array_keys($this->languageManager->getLanguages(LanguageInterface::STATE_ALL)));
    }

    $values['kill'] = $this->isDrush8() ? drush_get_option('kill') : $options['kill'];
    $values['title_length'] = 4;
    $values['num'] = array_shift($args);

    $all_stores = [];
    foreach ($this->storeTypeStorage->loadMultiple() as $store) {
      $all_stores[] = $store->id();
    }
    $default_stores = array_intersect(['online'], $all_stores);

    $all_types = [];
    foreach ($this->productTypeStorage->loadMultiple() as $type) {
      $all_types[] = $type->id();
    }

    $default_types = array_intersect(['default'], $all_types);
    if ($this->isDrush8()) {
      $selected_stores = _convert_csv_to_array(drush_get_option('stores', $default_stores));
      $selected_types = _convert_csv_to_array(drush_get_option('product-types', $default_types));
    }
    else {
      $selected_stores = StringUtils::csvToArray($options['stores'] ?: $default_stores);
      $selected_types = StringUtils::csvToArray($options['product-types'] ?: $default_types);
    }

    if (empty($selected_stores)) {
      throw new \Exception(dt('No stores available'));
    }

    if (empty($selected_types)) {
      throw new \Exception(dt('No product types available'));
    }

    $values['stores'] = array_combine($selected_stores, $selected_stores);
    $stores = array_filter($values['stores']);

    if (!empty($values['kill']) && ($values['num'] == 0) && empty($stores)) {
      throw new \Exception(dt('Please provide store ID (--stores) and / or product type (--product-types) in which you want to delete products.'));
    }

    // Checks for any missing store ID before generating products.
    if (array_diff($stores, $all_stores)) {
      throw new \Exception(dt('One or more stores have been entered that don\'t exist on this site'));
    }

    $values['product_types'] = array_combine($selected_types, $selected_types);
    $product_types = array_filter($values['product_types']);

    if (!empty($values['kill']) && ($values['num'] > 0) && empty($product_types)) {
      throw new \Exception(dt('Please provide product type (--product-types) in which you want to delete products.'));
    }

    // Checks for any missing content types before generating products.
    if (array_diff($product_types, $all_types)) {
      throw new \Exception(dt('One or more product types have been entered that don\'t exist on this site'));
    }

    return $values;
  }

}
