jQuery(load)

function load() {
  jQuery('.erx').show()

  upgradeMedication()

  jQuery('.col-1').prepend(jQuery('.woocommerce-additional-fields'))

  jQuery('form.checkout').show()
  jQuery('.woocommerce-MyAccount-content').hide()

  jQuery('#wc-stripe-new-payment-method').prop('checked', true)

  jQuery("#source")
  .change(function(){
    jQuery('#medication\\[\\]_field').toggle()
    jQuery('.erx, .pharmacy').toggle()
  })

  jQuery("#billing_state").on('change', function($event){

    var shipAddress = jQuery("#ship-to-different-address-checkbox")

    if (this.value != 'GA')
      shipAddress.click().prop('disabled', true)
    else
      shipAddress.prop('disabled', false).click()
  })
}

function upgradeMedication(medications) {
  console.log('upgradeMedication')

  var select = jQuery('#medication\\[\\]')
  select.empty()

  var medicationGsheet = "https://spreadsheets.google.com/feeds/list/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/ovrg94l/public/values?alt=json"
  //ovrg94l is the worksheet id.  To get this you have to use https://spreadsheets.google.com/feeds/worksheets/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/private/full

  jQuery.ajax({
    url:medicationGsheet,
    type: 'GET',
    cache:true,
    success:function($data) {
      console.log('medications gsheet', $data.feed.entry)
      select.select2({multiple:true,data:$data.feed.entry.map(medication2select)})
    }
  })
}

function medication2select(entry, i) {
  var price     = entry.gsx$day_2.$t || entry.gsx$day.$t
  var message   = []

  if (entry.gsx$supplylevel.$t)
    message.push(entry.gsx$supplylevel.$t)

  message.push(entry.gsx$day.$t ? '30 day' : '90 day')

  var drug = ' '+entry.gsx$drugname.$t+', '+price+' ('+message.join(', ')+')'
  return {id:drug, text:drug, disabled:entry.gsx$supplylevel.$t == 'Out of Stock', price:price.replace('$', '')}
}
