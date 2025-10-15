# TCG_Magento_2

This is The Courier Guy module v1.4.0 for Magento v2.4.8.

## Installation

- Copy the **AppInlet** Folder into **magento-root/app/code**
- Navigate into **magento/root** and enable the plugin by running:

```
php bin/magento module:enable AppInlet_TheCourierGuy
```

- After plugin successfully installs, make sure to run these necessary Magento commands:

```
php bin/magento setup:upgrade 
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento indexer:reindex
php bin/magento cache:clean
```

**Important:** Due to Magento updates, the product dimension attributes (length, width, height) might not be
automatically available. This document explains how to add and configure these attributes correctly.

### Shipping costs are calculated by:

1. **Retrieving** product dimensions (length, width, height) and weight from the ordered items.
2. **Falling back** to default values configured in the module's config settings if any dimension or weight is missing.
3. **Generating** a shipping request with product details and destination information.
4. **Communicating** with The Courier Guy API to retrieve shipping rates.

### Adding Product Dimension Attributes

If you are experiencing issues with the module not recognizing product dimensions, follow these steps to ensure the
attributes are correctly configured:

**1. Create or Verify the Attributes:**

* Navigate to **Stores > Attributes > Product**.
* Search for attributes named:
    * `length`
    * `width`
    * `height`
* If these attributes do not exist, create them:
    * Click **Add New Attribute**.
    * **Default Label:** `Length`, `Width`, `Height` respectively.
    * **Catalog Input Type for Store Owner:** `Text Field`.
    * **Scope:** `Store View` or `Global` (depending on your needs).
    * **Unique Value:** `No`.
    * **Values Required:** `No` (or `Yes` if you want to force all products to have dimensions).
    * **Advanced Attribute Properties:**
        * **Attribute Code:** `length`, `width`, `height` (exactly as shown).
        * **Frontend Input Validation:** `Decimal Number` or `None` (if you want to allow non decimal numbers).
        * **Add to Column Options:** `Yes` (optional, for easy viewing in product grids).
        * **Use in Filter Options:** `No`.
    * **Storefront Properties:**
        * **Visible on Catalog Pages on Storefront:** `No` (unless you want to display these attributes).
        * **Used in Product Listing:** `No`.
    * Click **Save Attribute**.

**2. Assign Attributes to Attribute Sets:**

* Navigate to **Stores > Attributes > Attribute Set**.
* Select the attribute set(s) used for your physical products (e.g., Default).
* In the **Unassigned Attributes** list, find `length`, `width`, and `height`.
* Drag and drop these attributes into the appropriate group (e.g., Basic Settings, or create a new group called "
  Shipping Dimensions").
* Click **Save Attribute Set**.
* Repeat for all relevant attribute sets.

**3. Enter Product Dimensions:**

* Navigate to **Catalog > Products**.
* Open a product that requires shipping.
* Find the `length`, `width`, and `height` fields in the product's attribute set.
* Enter the dimensions for the product.
* Save the product.
* Repeat for all products.

**4. Clear Cache:**

* Navigate to **System > Cache Management**.
* Select all cache types and click **Flush Magento Cache**.

**5. Verify the Module's Configuration:**

* Navigate to the module's configuration in the Magento admin.
* Ensure the "Typical Length", "Typical Width", "Typical Height", and "Typical Weight" fields are correctly set. These
  values will be used as defaults if product dimensions are missing.
