# TCG_Magento_2
This is The Courier Guy plugin for Magento 2.

Working:
Rates are being returned from the TCG portal, and when address is updated, rates update accordingly.
When an invalid address is entered, no TCG options show.
TCG waybill is sent to portal when the “ship” flow is selected and carried out on an invoice.
System automatically selects cheapest option.
The weights are sent through, but sometimes the weight is rounded down instead of up (was 16.5kg, but showed as 16kg).
Fixed price for TCG works, with the ship functionality.

Not Working:
Does not allow customer to choose shipping option (eco, ovn, lof etc). Only shows “The courier guy”. 
Only 5 shipping methods available on backend, vs the 20-30 on woocommerce backend.
An error shows when the checkout page is loaded (please see screenshot attached).
Print button does not print waybill. Only prints packing slip for order.