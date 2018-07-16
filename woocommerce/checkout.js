jQuery(load)

function load() {

  upgradeTransfer()

  upgradeRxs(function(select, drugs) {
    select.on("select2:unselecting", preventDefault)
    select.val(drugs.map(function(drug) { return ! drug.disable && drug.id })).change()
  })

  //Show our form fields at the beginning rather than the end
  jQuery('form.checkout .col-1').prepend(jQuery('.woocommerce-additional-fields'))

  //Move coupon from top of page to be next to payment instead
  jQuery( "a.showcoupon" ).parent().appendTo(jQuery('#customer_details'))
  jQuery('form.checkout_coupon').appendTo(jQuery('#customer_details'))

  //Save card info to our account automatically
  jQuery('#wc-stripe-new-payment-method').prop('checked', true)

  //This class causes update_checkout_totals, which causes a spinner and delay that we don't need
  //https://github.com/woocommerce/woocommerce/blob/master/assets/js/frontend/checkout.js
  setTimeout(function() {
    jQuery('form.checkout').off('keydown', '.address-field input.input-text, .update_totals_on_change input.input-text')
  }, 1000)

  //Toggle medication select and backup pharmacy text based on whether
  //Rx is being sent from doctor or transferred from a pharmacy.
  setSource()

  function preventDefault(e) {
    console.log('select2 preventDefault')
    e.preventDefault()
  }
}
