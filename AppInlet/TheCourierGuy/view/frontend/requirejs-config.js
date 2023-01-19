var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/model/shipping-save-processor/payload-extender': {
                'AppInlet_TheCourierGuy/js/model/shipping-save-processor/payload-extender-mixin': true
            }
        }
    },
    map: {
       '*': {
         'Magento_Checkout/template/additional-shipping-option.html':
             'AppInlet_TheCourierGuy/template/additional-shipping-option.html'
    }
  }

};