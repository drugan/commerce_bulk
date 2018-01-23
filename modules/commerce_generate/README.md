Commerce Generate
=================

Generates dummy commerce products. If you ever used *Generate content* plugin
then you might be familiar with the functionality because
the *Generate Products* is also plugin implementation of
the *Devel Generate* module.

- [admin/help/commerce_generate#todo](#todo "TODO")
- [admin/help/commerce_generate#module-author](#module-author "Module author")


![Generate Products](images/generate-products.png "Generate Products")

![Generate Products Form](images/generate-products-form.png "Generate Products Form")

The products to generate might be configured.

![Generate Products Config](images/generate-products-config.png "Generate Products Config")

![Generate Products List](images/generate-products-list.png "Generate Products List")

The same as the parent module does the *Generate Products* also generates dummy
images both for the product itself as well as for each product variation if the
image field is added to a variation.

![Generate Products Image](images/generate-products-image.png "Generate Products Image")


## TODO

- *Generate Products* Drush command.
- *Generate Stores* plugin based on existing store types with dummy store
owner data.
- *Generate Product Types* plugin with randomly assigned product variation type.
- *Generate Product Variation Types* plugin with randomly constructed sets of
attributes.
- *Generate Attributes* plugin to generate dummy attributes with some predefined
sets of values such as colors, sizes, human names, animals, numbers
like *One*, *Two*, *Three* or *1*, *2*, *3*, etc..
- `Add sample store` button on the [admin/commerce/config/stores#](#0
"admin/commerce/config/stores") page.
- `Add sample product` button on the [admin/commerce/config/product-types#](#0
"admin/commerce/config/product-types") page.
- `Add sample product variation type` button on
the [admin/commerce/config/product-variation-types#](#0
"admin/commerce/config/product-variation-types") page.
- `Add sample product attribute` button on the [admin/commerce/product-attributes#](#0
"admin/commerce/product-attributes") page.

###### Module author:
```
  Vladimir Proshin (drugan)
  [proshins@gmail.com](proshins@gmail.com)
  [https://drupal.org/u/drugan](https://drupal.org/u/drugan)
```
