;define([
    'jquery',
    'mage/utils/wrapper',
    'underscore'
], function ($, wrapper, _) {
    'use strict';

    return function (payloadExtender) {

        return wrapper.wrap(payloadExtender, function (originalFunction, payload) {
            const shippingClassId = $(".servicechoices__item.choices__item--selectable") ? $(".servicechoices__item.choices__item--selectable").attr("data-value") : '';
            const placeId = $(".placechoices__item.choices__item--selectable") ? $(".placechoices__item.choices__item--selectable").attr("data-value") : '';
            const emailAddress = $("#customer-email") ? $("#customer-email").val() : '';

            payload = originalFunction(payload);

            _.extend(payload.addressInformation, {
                extension_attributes: {
                    'email_address': emailAddress,
                    'place_id': placeId,
                    "shipping_class": shippingClassId
                }
            });

            return payload;
        });
    };
})