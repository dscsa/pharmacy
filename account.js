jQuery(load)

function load() {

  jQuery('form.login').submit(function(e) {
    this._wp_http_referer.value = "/account/orders"
  })

  var medication, pharmacies
  var medicationGsheet = "https://spreadsheets.google.com/feeds/list/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/ovrg94l/public/values?alt=json"
  var pharmacyGsheet  = "https://spreadsheets.google.com/feeds/list/11Ew_naOBwFihUrkaQnqVTn_3rEx6eAwMvGzksVTv_10/1/public/values?alt=json"
  //ovrg94l is the worksheet id.  To get this you have to use https://spreadsheets.google.com/feeds/worksheets/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/private/full

  jQuery.ajax({
    url:medicationGsheet,
    type: 'GET',
    cache:true,
    success:function($data) {
      console.log('medications gsheet', $data.feed.entry)
      medications = $data.feed.entry.map(medication2select)
    }
  })

  jQuery.ajax({
    url:pharmacyGsheet,
    type: 'GET',
    cache:true,
    success:function($data) {
      pharmacies = $data.feed.entry.map(pharmacy2select)
    }
  })

  // setTimeout(function() {
  //   window.scrollTo(0, 0)
  // },1)

  if ( ~ jQuery('.woocommerce-MyAccount-content').text().indexOf('No order')) {
    jQuery('.new-patient').show()
    jQuery('.woocommerce-billing-fields').show()

    upgradePharmacy(pharmacies)
    upgradeMedication(medications)

    jQuery('#billing_state').prop('disabled', true)

    jQuery("input[name='source']").change(function($event){
      jQuery('label#eRX').toggle()
      jQuery('label#transfer').toggle()
    })

    jQuery("#languageOther").on('input', function($event){
      jQuery('#languageRadio').prop('checked', true)
    })
  }
}

function upgradeMedication(medications) {
  console.log('upgradeMedication')
  var medicationSelect = jQuery('#medicationSelect')
  medicationSelect.select2({multiple:true,data:medications})
}

function upgradePharmacy(pharmacies) {
  console.log('upgradePharmacy')
  var backupPharmacySelect = jQuery('#backupPharmacy')
  var options = {data:pharmacies, matcher:matcher, minimumInputLength:3}
  backupPharmacySelect.select2(options)
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
