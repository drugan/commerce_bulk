Commerce Bulk
=============

Provides a service for bulk creation of *Drupal Commerce* entities. For now just
product variations could be bulk created on a product add or edit form. Also,
dummy products could be bulk generated if
the [Commerce Generate ↗](https://github.com/drugan/commerce_bulk/tree/8.x-1.x/modules/commerce_generate)
submodule is enabled. Both modules were created as a result of the
following *Drupal Commerce* issue:

[Issue #2755529 by ndf: Product variant bulk creation ↗](https://www.drupal.org/node/2755529)

- [admin/help/commerce_bulk#info-for-developers](#info-for-developers "Info for developers")
- [admin/help/commerce_bulk#module-author](#module-author "Module author")
- [Commerce Bulk on drupal.org ↗](https://www.drupal.org/project/commerce_bulk)
- [Commerce Bulk on github.com ↗](https://github.com/drugan/commerce_bulk)

After installing the module go to a product variation
type *Manage form display* tab and check your settings for
the *Commerce Bulk SKU* widget. As an example I've taken
the [admin/commerce/config/product-variation-types/default/edit/form-display#](#0
"default product variation type") but actually all of them should have the
widget enabled by default.

![SKU widget](images/sku-widget.png "Commerce Bulk SKU widget")

The SKU widget settings' summary explained:

- **size:** The size of the SKU field on a variation add / edit form.
- **placeholder:** The text appearing inside the empty SKU field.
- **maximum:** The maximum of SKU values that might be generated in one go. Use
it to restrict the number of variations to create by pressing
the `Create N variations` button. So, to create the next portion of variations
the button should be pressed once more and more, etc..
- **custom_label:** Set a custom label for the field as the
default *"SKU"* text for the label might be seen as confusing by some people.
- **hide:** Whether to hide the SKU field on a variation add / edit form. Note
that despite the field is not visible the valid SKU value is generated on the
backend for the field.
- **prefix:** The text automatically prepended to the SKU value.
- **suffix:** The text automatically appended to the SKU value.
- **auto SKU sample:** The sample of the SKU as you set up it for the given
product variation type.

Note that finding which variation type is referenced by a product is quite easy
with the module. Just open the product add / edit form, then
open *Inline Entity Form* widget for adding / editing of variation and click on
the [admin/commerce/config/product-variation-types/default/edit/form-display#](#0
"Set up default SKU") link in the SKU field description. It leads you exactly to
the page shown on the above screenshot. For demo purposes was created the
variation type with three attributes each having 10 values (simple numbers).
So, the total number of possible variatons is 1000.

![IEF widget](images/ief-widget.png "Inline Entity Form widget")

As you see the SKU and attributes' fields are kindly pre-populated for you,
though obviously all of them might be changed for any value, the auto
generated [uniqid ↗](http://php.net/manual/en/function.uniqid.php) SKU
including. On a new product the button for bulk creation of variations is
disabled as the price (and currency) is unknown before you create at least on
variation.

![Let's go](images/create-499.png "Let's go")

OK, now the button is enabled and will display the statistics of the process.
Remember, the default setting for the **maximum** on
the *Commerce Bulk SKU* widget was 500, we (manually) created 1 variation, so
let's create the next 499 (500 - 1) variations by pressing the button.

![Create 500](images/create-500.png "Create 500")

Press the button once more.

![Done 1000](images/done-1000.png "Done 1000")

Done. Now, the `Create N variations` button is disappeared because there is
nothing to create any more. This is quite useful feature of the module as even
if you were created the variations manually by pressing the `Add new variation`
button again and again, without the module you'd never
know if you are not missed some variation. Also, with such a huge number of
variations it may happen that you'd add some variation twice.
In this case the module will display a warning for you like the following:

![Duplicated](images/duplicated.png "Duplicated")

________________________________________________________________________________


## Info for developers

All the above functionality is based on the `BulkVariationsCreator` service
which you can use in your custom module. For example, let's say you want
to automatically create products with all possible or just a subset of
variations. It's easy, just see how the *Commerce Generate* `GenerateProducts`
plugin does it.

@PHPFILE: modules/contrib/commerce_bulk/modules/commerce_generate/src/Plugin/DevelGenerate/GenerateProducts.php LINE:512 PADD:27 :PHPFILE@

Also, see how the service is called in the `commerce_bulk.module` file.

@PHPFILE: commerce_bulk.module LINE:48 PADD:4 :PHPFILE@

Ping me on https://www.drupal.org or https://drupal.slack.com `#commerce`
channel if you have any questions on the service.

###### Module author:
```
  Vladimir Proshin (drugan)
  [proshins@gmail.com](proshins@gmail.com)
  [https://drupal.org/u/drugan](https://drupal.org/u/drugan)
```
