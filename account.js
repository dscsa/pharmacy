jQuery(load)

function load() {

  var medicationGsheet = "https://spreadsheets.google.com/feeds/list/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/ovrg94l/public/values?alt=json"
  var pharmacyGsheet  = "https://spreadsheets.google.com/feeds/list/11Ew_naOBwFihUrkaQnqVTn_3rEx6eAwMvGzksVTv_10/1/public/values?alt=json"
  //ovrg94l is the worksheet id.  To get this you have to use https://spreadsheets.google.com/feeds/worksheets/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/private/full

  jQuery('form.login').submit(function(e) {
    this._wp_http_referer.value = "/account/orders"
  })

  if ( ! ~ jQuery('.woocommerce-MyAccount-content').text().indexOf('No order'))
    return

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
  checkoutForm.show()

  jQuery("input[name='source']").change(function($event){
    jQuery('label#eRX').toggle()
    jQuery('label#transfer').toggle()
  })

  //jQuery('#billing_state').prop('disabled', true)
  //jQuery('<input>').attr({type:'hidden', name:'billing_state', value:'GA'}).appendTo('form.checkout');

  jQuery("input[name='language']:eq(3)").on('click', function($event){
    jQuery("input[name='language']:eq(2)").prop('checked', true)
  })

  jQuery('#wc-stripe-new-payment-method').prop('checked', true)

  console.log('submit button 1', jQuery('input#place_order').length)
  setTimeout(function() {
    var button = jQuery('input#place_order')
    console.log('submit button 2', button)
    button.attr('type', 'button').click(saveWordpress)
  }, 2000)

  checkoutForm.submit(function(e) {
    console.log('submit event')
    // e.stopImmediatePropagation()
    // e.preventDefault()
  })

  checkoutForm.on('checkout_place_order', function(e) {
    console.log('checkout_place_order', e)
  })

  function saveWordpress(e) {
    console.log('saveWordpress')
    e.stopImmediatePropagation()
    e.preventDefault()
    //stripe_token not passed with data so data.result != success
    //however if everything passes except stripe token then we
    //get a Developers: make sure JS is enabled error, which we detect
    var data = checkoutForm.serialize()
    jQuery.post({url:'/account/orders/?wc-ajax=checkout', data:data, cache:true, success:isValid})
  }

  function isValid(data) {
    console.log('isValid', data)
    //stripe_token not passed with data so data.result != success
    //however if everything passes except stripe token then we
    //get a Developers: make sure JS is enabled error, which we detect
    if (data.result == 'success' || ~ data.messages.indexOf('Developers:'))
      saveGuardian()
    else
      checkoutForm.submit()
  }

  function saveGuardian() {
    console.log('saveGuardian')
    jQuery.post('https://requestb.in/1et2h7e1', {
      data:patientForm.serialize()+'&'+checkoutForm.serialize(),
      success:function() { checkoutForm.submit() }
    })
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
