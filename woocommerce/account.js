jQuery(load)

function load() {

  // jQuery('form.login').submit(function(e) {
  //   this._wp_http_referer.value = "/account/orders"
  // })

  jQuery('.woocommerce-MyAccount-navigation-link--dashboard a').text('Place Order')

  //hide saved cards on everything but the account details page which has a password field
  //for some reason there is a space in the id so need the \\20
  if (jQuery('#password_current').length)
    jQuery('#tc-saved-cards\\20').show().next().show()

  if ( ! ~ jQuery('.woocommerce-MyAccount-content').text().indexOf('dashboard'))
    return

  var medicationGsheet = "https://spreadsheets.google.com/feeds/list/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/ovrg94l/public/values?alt=json"
  var pharmacyGsheet  = "https://spreadsheets.google.com/feeds/list/11Ew_naOBwFihUrkaQnqVTn_3rEx6eAwMvGzksVTv_10/1/public/values?alt=json"
  //ovrg94l is the worksheet id.  To get this you have to use https://spreadsheets.google.com/feeds/worksheets/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/private/full

  jQuery.ajax({
    url:medicationGsheet,
    type: 'GET',
    cache:true,
    success:function($data) {
      console.log('medications gsheet', $data.feed.entry)
      var medications = $data.feed.entry.map(medication2select)
      upgradeMedication(medications)
    }
  })

  jQuery.ajax({
    url:pharmacyGsheet,
    type: 'GET',
    cache:true,
    success:function($data) {
      var pharmacies = $data.feed.entry.map(pharmacy2select)
      upgradePharmacy(pharmacies)
    }
  })
  var checkoutForm = jQuery('form.checkout')
  var patientForm  = jQuery('form.new-patient')
  patientForm.show()

  setTimeout(showBillingForm, 500)
  
  jQuery("input[name='source']").change(function($event){
    jQuery('label#eRX').toggle()
    jQuery('label#transfer').toggle()
  })

  //Trust commerce gateway is not smart enough to do MM/YYYY to MM/YY for us
  jQuery('input#trustcommerce-card-expiry').on('blur', function() {
    var _this = jQuery(this)
    _this.val(_this.val().replace(/20(\d\d)/, '$1'))
  })

  //jQuery('#billing_state').prop('disabled', true)
  //jQuery('<input>').attr({type:'hidden', name:'billing_state', value:'GA'}).appendTo('form.checkout');

  jQuery("input[name='language']:eq(3)").on('click', function($event){
    jQuery("input[name='language']:eq(2)").prop('checked', true)
  })

  jQuery('#wc-stripe-new-payment-method').prop('checked', true)

  jQuery( document ).ajaxComplete(function( event, xhr, settings ) {
    if ( settings.url != wc_checkout_params.checkout_url)
      return

    var data = JSON.parse(xhr.responseText)
    console.log('ajaxComplete', data)

    if (data.redirect && data.result == 'success')
      saveGuardian(data.redirect.match(/order\/(\d+)/)[1])
  })

  function saveGuardian(order) {
    console.log('saveGuardian, order#', order)
    jQuery.post('https://webform.goodpill.org/patient',
      patientForm.serialize()+'&'+checkoutForm.serialize()+'&order='+order
    )
  }

  //This appears to need a delay
  function showBillingForm() {
    console.log('.av-active-counter', jQuery('.av-active-counter').text())
    if (jQuery('.av-active-counter').text())
      checkoutForm.show()
  }
}

function upgradeMedication(medications) {
  console.log('upgradeMedication')
  var select = jQuery('select[name="medication"]')
  select.select2({multiple:true,data:medications})
}

function upgradePharmacy(pharmacies) {
  console.log('upgradePharmacy')
  var select = jQuery('select[name="backupPharmacy"]')
  select.select2({data:pharmacies, matcher:matcher, minimumInputLength:3})
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

function pharmacy2select(entry, i) {
  var address  = entry.gsx$cleanaddress.$t.replace(/(\d{5})(\d{4})/, '$1')
  var pharmacy = entry.gsx$name.$t+', '+address+', Phone: '+entry.gsx$phone.$t
  return {id:pharmacy+', Fax:'+entry.gsx$fax.$t, text:pharmacy}
}

//http://stackoverflow.com/questions/36591473/how-to-use-matcher-in-select2-js-v-4-0-0
function matcher(param, data) {
   if ( ! param.term ||  ! data.text) return null
   var has = true
   var words = param.term.toUpperCase().split(" ")
   var text  = data.text.toUpperCase()
   for (var i =0; i < words.length; i++)
     if ( ! ~ text.indexOf(words[i])) return null

   return data
}
