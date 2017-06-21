jQuery(load)

function load() {

  //hide saved cards on everything but the account details page which has a password field
  //for some reason there is a space in the id so need the \\20
  if (window.location.pathname == '/account/address/')
    jQuery('#tc-saved-cards\\20').next().show()

  if ( ! ~ ['/account/details/', '/account/'].indexOf(window.location.pathname))
    return

  upgradePharmacy()

  jQuery("#language_english").change(function($event){
    jQuery('#language').remove()
    jQuery("<style id='language' type='text/css'>.spanish{display:none}</style>").appendTo("head")
  })

  jQuery("#language_spanish").change(function(){
    jQuery('#language').remove()
    jQuery("<style id='language' type='text/css'>.english{display:none}</style>").appendTo("head")
  })

  jQuery("input[name=language]:checked").triggerHandler('change')

  jQuery("input[name=allergies_none]").on('change', function(){
    var children = jQuery(".allergies")
    this.value ? children.hide() : children.show()
  })
  jQuery("input[name=allergies_none]:checked").triggerHandler('change')

  var allergies_other = jQuery('#allergies_other').prop('disabled', true)
  jQuery('#allergies_other_input').on('input', function() {
    allergies_other.prop('checked', this.value)
  })

  jQuery('#birth_date').prop('type', 'date') //can't easily set date type in woocommerce

  if (window.location.search == '?register') {
    jQuery('#customer_login > div').toggle()
    return jQuery("form.register").submit(function($event){
      this.username.value = this.first_name.value+' '+this.last_name.value+' '+this.birth_date.value
    })
  }
}

function upgradePharmacy(pharmacies) {
  console.log('upgradePharmacy')

  var select = jQuery('#backup_pharmacy')
  var pharmacyGsheet  = "https://spreadsheets.google.com/feeds/list/11Ew_naOBwFihUrkaQnqVTn_3rEx6eAwMvGzksVTv_10/1/public/values?alt=json"
  //ovrg94l is the worksheet id.  To get this you have to use https://spreadsheets.google.com/feeds/worksheets/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/private/full

  jQuery.ajax({
    url:pharmacyGsheet,
    type: 'GET',
    cache:true,
    success:function($data) {
      var pharmacies = $data.feed.entry.map(pharmacy2select)
      select.select2({data:pharmacies, matcher:matcher, minimumInputLength:3})
    }
  })
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
