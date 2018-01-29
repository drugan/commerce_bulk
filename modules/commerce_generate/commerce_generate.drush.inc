<?php

/**
 * @file
 * Integration with Drush8.
 */

/**
 * Implements drush_hook_COMMAND_validate().
 */
function drush_devel_generate_generate_products_validate() {
  _drush_plugin_validate(func_get_args());
}

/**
 * Implements hook_drush_command().
 */
function commerce_generate_drush_command() {
  $items['generate-products'] = [
    'callback' => 'drush_commerce_generate',
    'callback arguments' => [
      'plugin_id' => 'products',
    ],
    'description' => "Create Drupal Commerce 2.x products. Example command explained: create 10 products, for stores with ID 1 or 2, either of my-type or their_type, delete (kill) previously created products of the types, do not create products in a batch process (11 > 10), append the word 'My Product' to a random title, restrict title to 3 words, assign random price in the range of 1.11 - 2.22, randomize price for each variation instead of each product, assign user with ID 1 as owner for created products, create products in languages specified (should be enabled). The very first (number of products) argument is required, any option(s) can be omitted but the exact sequence order should be kept as specified:

drush generate-products 10 --stores 1,2 --product-types my_type,their_type --kill --batch 11 --title-prefix 'My Product' --title-length 3 --price-min 1.11 --price-max 2.22 --price-per-variation --owner 1 --languages et,ru",
    'drupal dependencies' => ['devel_generate'],
    'arguments' => [
      'num' => 'Number of products to generate.',
    ],
    'options' => [
      'stores' => 'A comma delimited list of store IDs to assign generated products. Defaults to all stores.',
      'product-types' => 'A comma delimited list of product types to create. Defaults to all types.',
      'kill' => 'Delete all products before generating new ones.',
      'batch' => 'The threshold at which to start batch products creation process instead of doing this in one go.',
      'title-prefix' => 'The word to prepend to a randomly generated product title.',
      'title-length' => 'The maximum number of words in titles.',
      'price-min' => 'The minimum of the randomly generated price (without currency code)',
      'price-max' => 'The maximum of the randomly generated price (without currency code).',
      'price-per-variation' => 'Sets random price per variation instead of per product basis.',
      'owner' => 'User ID to assign as owner for generated products. Defaults to random user.',
      'languages' => 'Languages beyond the default to generate products in.',
    ],
    'aliases' => ['genpr', 'gprod'],
  ];

  return $items;
}

/**
 * Command callback. Generate a number of elements.
 */
function drush_commerce_generate() {
  $params = func_get_args();
  $plugin_id = array_shift($params);
  $instance_and_values = drupal_static('drush_devel_generate_generate_validate');

  /** @var DevelGenerateBaseInterface $instance */
  $instance = $instance_and_values[$plugin_id]['instance'];
  $values = $instance_and_values[$plugin_id]['values'];
  $instance->generate($values);
}
