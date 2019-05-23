jQuery(load)

function load() {

  getInventory(function(data) {
    var rxMap = getRxMap() //Remove low stock (disabled) items
    upgradeTransfer(data, rxMap)
    upgradeRxs(data, rxMap)
  })

  //Show our form fields at the beginning rather than the end
  jQuery('form.checkout .col-1').prepend(jQuery('.woocommerce-additional-fields'))

  //Move coupon from top of page to be next to payment instead and set width back to 100%
  jQuery('div.woocommerce-form-coupon-toggle').appendTo(jQuery('#customer_details')).addClass('reset-to-full-width')
  jQuery('form.checkout_coupon').appendTo(jQuery('#customer_details')).addClass('reset-to-full-width')

  //This class causes update_checkout_totals, which causes a spinner and delay that we don't need
  //https://github.com/woocommerce/woocommerce/blob/master/assets/js/frontend/checkout.js
  setTimeout(function() {
    jQuery('form.checkout').off('keydown', '.address-field input.input-text, .update_totals_on_change input.input-text')
  }, 1000)

  //Toggle medication select and backup pharmacy text based on whether
  //Rx is being sent from doctor or transferred from a pharmacy.
  setSource()
}

//Initial eRx and Refill Requests
function upgradeRxs(data, rxMap) {
  console.log('upgradeRxs',  data.length)
  var select = jQuery('#rxs\\[\\]')

  console.log('rxMap 1', rxMap)
  rxMap = disableRxs(data, rxMap)
  console.log('rxMap 2', rxMap)
  rxMap = objValues(rxMap)
  console.log('rxMap 3', rxMap)

  select.select2({multiple:true, data:rxMap})
  select.val(rxMap.map(function(rx) { return ! rx.disabled && rx.id })).change()
}

function upgradeTransfer(data, rxMap) {

  console.log('upgradeTransfer data', data.length)

  var select = jQuery('#transfer\\[\\]')

  console.log('rxMap 1', rxMap)
  data = disableInventory(data, rxMap) //Remove low stock (disabled) items

  select.empty() //get rid of default option (Select Rxs) before loading in our actual inventory
  select.select2({multiple:true, data:data})
}
