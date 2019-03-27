jQuery(load)

function load() {

  upgradeTransfer()
  upgradeRxs()

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
function upgradeRxs() {
  console.log('upgradeRxs')
  var select = jQuery('#rxs\\[\\]')

  getInventory(function(data) {

    console.log('upgradeRxs data', data.length, data)

    //Remove low stock (disabled) items
    var rxMap = getRxMap()
    console.log('rxMap 1', rxMap)
    rxMap = disableRxs(data, rxMap)
    console.log('rxMap 2', rxMap)
    rxMap = objValues(rxMap)
    console.log('rxMap 3', rxMap)

    select.select2({multiple:true, data:rxMap})
    select.val(rxMap.map(function(rx) { return ! rx.disabled && rx.id })).change()
  })

}

function upgradeTransfer() {
  console.log('upgradeTransfer')
  var select = jQuery('#transfer\\[\\]')

  getInventory(function(data) {
    console.log('upgradeTransfer data', data.length, data)

    var rxMap = getRxMap()
    console.log('rxMap 1', rxMap)

    //Remove low stock (disabled) items
    data = disableInventory(data, rxMap)

    select.empty() //get rid of default option (Select Rxs) before loading in our actual inventory
    select.select2({multiple:true, data:data})
  })

}
