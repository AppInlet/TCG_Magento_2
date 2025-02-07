# TCG_Magento_2

This is The Courier Guy plugin v1.2.1 for Magento v2.4.7.

# Installation

- Copy the "AppInlet" Folder into **magento-root**/app/code
- Cd into **magento/root** and enable the plugin by running "sudo -u <Magento file system owner> php bin/magento module:
  enable AppInlet_TheCourierGuy"
- After plugin successfully installs, make sure to run these necessary Magento commands:
- sudo -u <Magento file system owner> php bin/magento setup:upgrade
- sudo -u <Magento file system owner> php bin/magento setup:di:compile
- sudo -u <Magento file system owner> php bin/magento setup:static-content:deploy
- sudo -u <Magento file system owner> php bin/magento indexer:reindex
- sudo -u <Magento file system owner> php bin/magento cache:clean
