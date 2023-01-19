;define([
	'jquery',
    'uiComponent',
    'ko',
    'Magento_Checkout/js/model/quote'

], function ($,Component, ko, quote) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'AppInlet_TheCourierGuy/additional-shipping-option'
        },

        initObservable: function () {
            const self = this._super();

            this.showAdditionalOption = ko.computed(function() {
                const method = quote.shippingMethod();

                if(method && method['carrier_code'] !== undefined) {
                	
                        if(method['carrier_code'] === 'appinlet_the_courier_guy') {
							const placeId = $(".placechoices__item.choices__item--selectable") ? $(".placechoices__item.choices__item--selectable").attr("data-value") : '';
							
							if(placeId !== undefined){
								
								$.ajax({
									showLoader: true,
									url: "/thecourierguy/index/updatequote",
									data: {place_id:placeId},
									type: "POST"
								}).done(function (data) {
									return true;
								});
							}
							 return true;
					    }
                }

                return false;

            }, this);

            return this;
        }
    });
});