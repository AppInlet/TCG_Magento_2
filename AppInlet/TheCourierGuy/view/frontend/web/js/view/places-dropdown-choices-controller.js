require(['jquery', 'Magento_Checkout/js/model/quote', 'Magento_Checkout/js/model/shipping-rate-registry'],

  function ($, quote, rateRegistry) {

    let placesTypingTimer; //timer identifier
    let cityTypingTimer;     //timer identifier
    const doneTypingInterval = 500;  //time in ms, 3 second

    //get address from quote observable
    const address = quote.shippingAddress();

    if (address != null) {

      //changes the object so observable sees it as changed
      address.trigger_reload = new Date().getTime();

      //create rate registry cache
      //the two calls are required
      //because Magento caches things
      //differently for new and existing
      //customers (a FFS moment)
      rateRegistry.set(address.getKey(), null);
      rateRegistry.set(address.getCacheKey(), null);

      //with rates cleared, the observable listeners should
      //update everything when the rates are updated

    }

    //on keyup, start the countdown
    $(document).on('keyup', 'input.choices__input.choices__input--cloned', function () {
      clearTimeout(placesTypingTimer);
      placesTypingTimer = setTimeout(lookPlaceUp.bind(null, $(this)), doneTypingInterval);
    });

    //on keydown, clear the countdown
    $(document).on('keydown', 'input.choices__input.choices__input--cloned', function () {
      clearTimeout(placesTypingTimer);
    });

    //update places and classes if address if update after first load
    $(document).on('change', '[name="city"]', function () {

      clearTimeout(cityTypingTimer);

      cityTypingTimer = setTimeout(updatePlacesAndShippingClasses, doneTypingInterval);

    });

    //update places and classes if address if update after first load

    $(document).on('keydown', '[name="city"]', function () {

      clearTimeout(cityTypingTimer);

    });

    //update quote when city in main address changes

    function updatePlacesAndShippingClasses () {

      const searchTerm = $('[name="city"]').val();

      const address = quote.shippingAddress();

      const apiUrl = window.location.origin + '/rest/V1/appinlet-thecourierguy/get-place-by-name';

      if (searchTerm !== '') {

        if (typeof window.placesCustomTemplate != 'undefined') {

          $.ajax({

            url: apiUrl,
            type: 'POST',
            beforeSend: function (xhr) {
              xhr.setRequestHeader('Content-Type', 'application/json');
            },
            dataType: 'json',
            data: JSON.stringify({
              place_name: searchTerm
            }),
            success: function (data) {

              window.placesCollection = data;

              window.placesCustomTemplate.clearChoices();

              var selectValues = [];

              $.each(data, function (index, val) {

                if (index === 0) {

                  selectValues.push({
                    value: val['place'],
                    label: val['town'],
                    customProperties: {
                      pcode: val['pcode']
                    },
                    selected: true
                  });

                  $('[name="postcode"]').val(val['pcode']);

                } else {

                  selectValues.push(
                    {
                      value: val['place'],
                      label: val['town'],
                      customProperties: {
                        pcode: val['pcode']
                      }
                    });
                }
              });

              window.placesCustomTemplate.setChoices(
                selectValues,
                'value',
                'label',
                false,
              );

            },
            error: function (xhr, resp, text) {
              console.log(xhr.responseJSON);

            }
          });

        }

      }

      //update quote

      if (address != null) {
        quote.shippingAddress(address);
      }
      $('[name="postcode"]').keyup();

    }

    function lookPlaceUp (element) {

      if (element.val() === $('input.choices__input.choices__input--cloned').first().val()) {

        var searchTerm = element.val();
        var apiUrl = window.location.origin + '/rest/V1/appinlet-thecourierguy/get-place-by-name';

        if (searchTerm !== '') {

          $.ajax({

            url: apiUrl,
            type: 'POST',
            beforeSend: function (xhr) {
              xhr.setRequestHeader('Content-Type', 'application/json');
            },
            dataType: 'json',
            data: JSON.stringify({
              place_name: searchTerm
            }),
            success: function (data) {

              window.placesCustomTemplate.clearChoices();

              var selectValues = [];

              $.each(data, function (index, val) {

                if (index === 0) {

                  selectValues.push({
                    value: val['place'],
                    label: val['town'],
                    customProperties: {
                      pcode: val['pcode']
                    },
                    selected: false
                  });

                } else {

                  selectValues.push(
                    {
                      value: val['place'],
                      label: val['town'],
                      customProperties: {
                        pcode: val['pcode']
                      }
                    });
                }
              });

              window.placesCustomTemplate.setChoices(
                selectValues,
                'value',
                'label',
                false,
              );

            },
            error: function (xhr, resp, text) {
              console.log(xhr.responseJSON);

            }
          });
        }

      }
    }

    /* initiate dropdowns */
    $(document).ready(function () {

      const placesDropdownExists = setInterval(function () {

        if ($('#places_classes').length) {

          clearInterval(placesDropdownExists);

          var placesDropDownElement = document.getElementById('places_classes');

          window.placesCustomTemplate = new Choices(placesDropDownElement, {
            callbackOnCreateTemplates: function (strToEl) {
              var classNames = this.config.classNames;
              var itemSelectText = this.config.itemSelectText;
              return {
                item: function (classNames, data) {
                  return strToEl('\
                            <div\
                              class="place' + String(classNames.item) + ' ' + String(data.highlighted ? classNames.highlightedState : classNames.itemSelectable) + '"\
                              data-item\
                              data-id="' + String(data.id) + '"\
                              data-value="' + String(data.value) + '"\
                              ' + String(data.active ? 'aria-selected="true"' : '') + '\
                              ' + String(data.disabled ? 'aria-disabled="true"' : '') + '\
                              >\
                              <span style="margin-right:10px;"></span> ' + String(data.label) + '\
                            </div>\
                          ');
                },
                choice: function (classNames, data) {
                  return strToEl('\
                      <div\
                        class="' + String(classNames.item) + ' ' + String(classNames.itemChoice) + ' ' + String(data.disabled ? classNames.itemDisabled : classNames.itemSelectable) + '"\
                        data-select-text="' + String(itemSelectText) + '"\
                        data-choice \
                        ' + String(data.disabled ? 'data-choice-disabled aria-disabled="true"' : 'data-choice-selectable') + '\
                        data-id="' + String(data.id) + '"\
                        data-value="' + String(data.value) + '"\
                        ' + String(data.groupId > 0 ? 'role="treeitem"' : 'role="option"') + '\
                        >\
                        <span style="margin-right:10px;"></span> ' + String(data.label) + '\
                      </div>\
                    ');
                },
              };
            }
          });

          placesDropDownElement.addEventListener(
            'addItem',
            function (event) {

              if (event.detail.customProperties == null) {

                if ($('[name="city"]').val() !== event.detail.label) {

                  $('[name="city"]').val(event.detail.label);

                  updatePlacesAndShippingClasses();
                }

              } else {

                if ($('[name="postcode"]').val() !== event.detail.customProperties.pcode) {

                  $('[name="postcode"]').val(event.detail.customProperties.pcode);

                }

                if ($('[name="city"]').val() !== event.detail.label) {

                  $('[name="city"]').val(event.detail.label);

                  //get address from quote observable
                  var address = quote.shippingAddress();

                  if (address != null) {

                    var address = quote.shippingAddress();
                    rateRegistry.set(address.getKey(), null);
                    rateRegistry.set(address.getCacheKey(), null);
                    quote.shippingAddress(address);
                  }
                }

              }

            },
            false,
          );

          //get places by city on load if city is set and list is empty
          if (typeof window.placesCollection != 'undefined' && window.placesCollection.length === 0) {

            updatePlacesAndShippingClasses();

          }

          //show shipping classes on load if not visible

          if (!$('#shipping_classes').length) {

            let options = '';

            if (typeof window.shippingClassesCollection != 'undefined') {

              window.shippingClassesCollection.forEach(function (item, index) {

                options = options + '<option value=\'' + item.value + '\'>' + item.label + '</option>';

              });

            }
            //load shipping classes
            if ($('#tcgShippingClasses').length != 1) {
            }

          }

        }
      }, 100); // check every 100ms

      const shippingClassesDropdownExists = setInterval(function () {

        if ($('#shipping_classes').length) {

          clearInterval(shippingClassesDropdownExists);

          window.shippingClassesCustomTemplate = new Choices(document.getElementById('shipping_classes'), {
            callbackOnCreateTemplates: function (strToEl) {
              var classNames = this.config.classNames;
              var itemSelectText = this.config.itemSelectText;
              return {
                item: function (classNames, data) {
                  return strToEl('\
                            <div\
                              class="service' + String(classNames.item) + ' ' + String(data.highlighted ? classNames.highlightedState : classNames.itemSelectable) + '"\
                              data-item\
                              data-id="' + String(data.id) + '"\
                              data-value="' + String(data.value) + '"\
                              ' + String(data.active ? 'aria-selected="true"' : '') + '\
                              ' + String(data.disabled ? 'aria-disabled="true"' : '') + '\
                              >\
                              <span style="margin-right:10px;"></span> ' + String(data.label) + '\
                            </div>\
                          ');
                },
                choice: function (classNames, data) {
                  return strToEl('\
                      <div\
                        class="' + String(classNames.item) + ' ' + String(classNames.itemChoice) + ' ' + String(data.disabled ? classNames.itemDisabled : classNames.itemSelectable) + '"\
                        data-select-text="' + String(itemSelectText) + '"\
                        data-choice \
                        ' + String(data.disabled ? 'data-choice-disabled aria-disabled="true"' : 'data-choice-selectable') + '\
                        data-id="' + String(data.id) + '"\
                        data-value="' + String(data.value) + '"\
                        ' + String(data.groupId > 0 ? 'role="treeitem"' : 'role="option"') + '\
                        >\
                        <span style="margin-right:10px;"></span> ' + String(data.label) + '\
                      </div>\
                    ');
                },
              };
            }
          });
        }
      }, 100); // check every 100ms

    });

  });
