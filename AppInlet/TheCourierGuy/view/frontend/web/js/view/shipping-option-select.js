;define([
  'jquery',
  'ko',
  'Magento_Ui/js/form/element/select',
  'Magento_Checkout/js/model/quote'
], function ($, ko, select, quote) {
  'use strict';

  let self;

  return select.extend({

    initialize: function () {
      self = this;
      this._super();

      quote.shippingMethod.subscribe(function () {

        const method = quote.shippingMethod();

        if (method && method['carrier_code'] !== undefined) {
          self.selectedShippingMethod = method;
          self.updateDropdownValues(method);
        }

      }, null, 'change');
    },

    /**
     * Called when shipping method is changed.
     * Also cadditionalShippingOptionFieldalled when initial selection is made.
     *
     * @param value
     * @returns {Object} Chainable
     */
    updateDropdownValues: function (method) {

      let placesCollection = [],
        places = [],
        shippingClasses = [],
        shippingClassesCollection = [];

      if (typeof method['extension_attributes'] != 'undefined') {
        const methodAttributes = method['extension_attributes'];
        if (typeof methodAttributes['places'] != 'undefined') {
          places = methodAttributes['places'];
        }
        if (typeof methodAttributes['shippingclasses'] != 'undefined' && methodAttributes['shippingclasses'] != null && typeof methodAttributes['shippingclasses'][1] != 'undefined') {
          shippingClasses = methodAttributes['shippingclasses'][1];
        }
      }

      if (places != null && places.length !== 0) { //08-06-2021

        places.forEach(function (item, index) {

          const placeObject = JSON.parse(item);

          const town = placeObject.town;

          const placeId = placeObject.place;

          placesCollection.push(
            {
              label: town,
              value: '' + placeId + ''
            });
        });
      }

      //add variable to window so update places displayed if address is changed after first load
      window.placesCollection = placesCollection;

      /*hiding dropdowns*/
      self.updatePlacesDropdown(placesCollection); //08-06-2021

      if (shippingClasses.length !== 0) {

        Object.keys(shippingClasses).forEach(function (index) {

          const classObject = JSON.parse(shippingClasses)[index];

          if (typeof classObject != 'undefined') {

            const classNameAndPrice = classObject.name + ' - R ' + classObject.total;

            shippingClassesCollection.push(
              {
                label: classNameAndPrice,
                value: '' + index + ''
              });

          }
        });

        //add variable to window so update pricing displayed if address is changed after first load
        window.shippingClassesCollection = shippingClassesCollection;

        /*hiding dropdowns*/

      }

    },

    updatePlacesDropdown: function (value) {

      let options = '';

      value.forEach(function (item, index) {

        options = options + '<option value=\'' + item.value + '\'>' + item.label + '</option>';

      });

      if (!$('#shipping_classes').length) {

        //load shipping classes
        if ($('#tcgPlaces').length != 1) {
          $('.onestep-shipping-method').append('<br><br><span id="tcgPlaces" data-bind=\'i18n: element.label\'>If selecting Courier, please select your preferred Suburb/Area for delivery</span><p><select id=\'places_classes\'>' + options + '</select></p>');
        }

      } else {

        //update shipping classes

        window.placesCustomTemplate.clearChoices();

        const selectValues = [];

        $.each(window.placesCollection, function (index, val) {

          if (index === 0) {

            selectValues.push({ value: val['value'], label: val['label'], selected: true });

          } else {

            selectValues.push({ value: val['value'], label: val['label'] });
          }
        });

        window.placesCustomTemplate.setChoices(
          selectValues,
          'value',
          'label',
          false,
        );

      }

    },

    updateShippingClassesDropdown: function (value) {

      let options = '';

      value.forEach(function (item, index) {

        options = options + '<option value=\'' + item.value + '\'>' + item.label + '</option>';

      });

      if (!$('#shipping_classes').length) {

        //load shipping classes
        if ($('#tcgShippingClasses').length != 1) {
        }

      } else {

        //update shipping classes

        window.shippingClassesCustomTemplate.clearChoices();

        const selectValues = [];

        $.each(window.shippingClassesCollection, function (index, val) {

          if (index === 0) {

            selectValues.push({ value: val['value'], label: val['label'], selected: true });

          } else {

            selectValues.push({ value: val['value'], label: val['label'] });
          }
        });

        window.shippingClassesCustomTemplate.setChoices(
          selectValues,
          'value',
          'label',
          false,
        );

      }
    }
  });
});
