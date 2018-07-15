//Helps providers signout easier. Also prevents setting the ?register when signed in
function signup2signout() {
  jQuery('li#menu-item-10 a, li#menu-item-103 a').html('<span class="english">Sign Out</span><span class="spanish">Cierre de sesión</span>').prop('href', '/account/logout/')
  jQuery('li#menu-item-9 a, li#menu-item-102 a').html('<span class="english">My Account</span><span class="spanish">Mi Cuenta</span>')
  //jQuery('li#menu-item-102').addClass('current-menu-item').css('pointer-events', 'none')
}

function setSource() {
  jQuery("#rx_source_pharmacy").change(hideErx)
  jQuery("#rx_source_erx").change(hidePharmacy)
  jQuery("<style id='rx_source' type='text/css'></style>").appendTo('head')
  jQuery("input[name=rx_source]:checked").triggerHandler('change')
}

function hidePharmacy() {
  jQuery('#rx_source').html(".pharmacy{display:none}")
}

function hideErx() {
  jQuery('#rx_source').html(".erx{display:none}")
}

function translate() {
  jQuery("#language_EN").change(hideSpanish)
  jQuery("#language_ES").change(hideEnglish)
  jQuery("<style id='language' type='text/css'></style>").appendTo('head')
  jQuery("input[name=language]:checked").first().triggerHandler('change') //registration page has two language radios
}

function hideEnglish() {
  jQuery('#language').html(".english{display:none}")
}

function hideSpanish() {
  jQuery('#language').html(".spanish{display:none}")
}

function upgradeAllergies() {
  jQuery("input[name=allergies_none]").on('change', function(){
    var children = jQuery(".allergies")
    this.value ? children.hide() : children.show()
  })
  jQuery("input[name=allergies_none]:checked").triggerHandler('change')

  var allergies_other = jQuery('#allergies_other').prop('disabled', true)
  jQuery('#allergies_other_input').on('input', function() {
    allergies_other.prop('checked', this.value)
  })
  jQuery('#allergies_other_input').triggerHandler('input')
}

function upgradePharmacy(pharmacies) {
  console.log('upgradePharmacy')

  var select = jQuery('#backup_pharmacy')
  var pharmacyGsheet = "https://spreadsheets.google.com/feeds/list/1ivCEaGhSix2K2DvgWQGvd9D7HmHEKA3VkQISbhQpK8g/1/public/values?alt=json"
  //ovrg94l is the worksheet id.  To get this you have to use https://spreadsheets.google.com/feeds/worksheets/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/private/full

  jQuery.ajax({
    url:pharmacyGsheet,
    type: 'GET',
    cache:true,
    success:function($data) {
      console.log('pharmacy gsheet')
      var data = []
      for (var i in $data.feed.entry) {
        data.push(pharmacy2select($data.feed.entry[i]))
      }
      select.select2({data:data, matcher:matcher, minimumInputLength:3})
    }
  })
}

function upgradeBirthdate() { //now 2 on same page (loing & register) so jquery id, #, selection doesn't work since assumes ids are unique
  jQuery('[name=birth_date]:not([readonly])').each(function() {
    var elem = jQuery(this)
    elem.datepicker({changeMonth:true, changeYear:true, yearRange:"c-100:c", defaultDate:elem.val() || "-50y", dateFormat:"yy-mm-dd", constrainInput:false})
  })
}

function clearEmail() {
  var email = jQuery('#email').val() || jQuery('#account_email').val()
  if(email && /\d{10}@goodpill.org/.test(email)) {
    jQuery('#email').val('')
    jQuery('#account_email').val('')
  }
}

//Disabled fields not submitted causing validation error.
function disableFixedFields() {
  jQuery('#account_first_name').prop('readonly', true)
  jQuery('#account_last_name').prop('readonly', true)
  //Readonly doesn't work for radios https://stackoverflow.com/questions/1953017/why-cant-radio-buttons-be-readonly
  jQuery('input[name=language]:not(:checked)').attr('disabled', true)

  // var other_allergy = jQuery('#allergies_other_input')
  // if (other_allergy.val()) //we cannot properly edit in guardian right now
  //   other_allergy.prop('disabled', true)
}

function pharmacy2select(entry, i) {

  var store = {
    fax:entry.gsx$fax.$t,
    phone:entry.gsx$phone.$t,
    npi:entry.gsx$npi.$t,
    street:entry.gsx$street.$t,
    city:entry.gsx$city.$t,
    state:'GA',
    zip:entry.gsx$zip.$t,
    name:entry.gsx$name.$t
  }
  var text = store.name+', '+store.street+', '+store.city+', GA '+store.zip+' - Phone: '+store.phone
  return {id:JSON.stringify(store), text:text}
}

//http://stackoverflow.com/questions/36591473/how-to-use-matcher-in-select2-js-v-4-0-0
function matcher(param, data) {
   if ( ! param.term ||  ! data.text) return null
   var has = true
   var words = param.term.toUpperCase().split(/,? /)
   var text  = data.text.toUpperCase()
   for (var i =0; i < words.length; i++)
     if ( ! ~ text.indexOf(words[i])) return null

   return data
}

function upgradeMedication(openOnSelect, callback) {
  console.log('upgradeMedication')

  var select = jQuery('#medication\\[\\]')
  select.empty()

  var medicationGsheet = "https://spreadsheets.google.com/feeds/list/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/od6/public/values?alt=json"
  //ovrg94l is the worksheet id.  To get this you have to use https://spreadsheets.google.com/feeds/worksheets/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/private/full

  jQuery.ajax({
    url:medicationGsheet,
    type: 'GET',
    cache:true,
    success:function($data) {
      console.log('upgradeMedication medications gsheet')
      var data = []
      for (var i in $data.feed.entry) {
        var entry = $data.feed.entry[i]
        if (entry.gsx$totalqty.$t > 0 && ( ! entry.gsx$stock.$t || ! openOnSelect)) //don't fill up dropdown with stuff we have never gotten
          data.push(entry2select(entry))
      }

      select.select2({multiple:true, closeOnSelect: ! openOnSelect, data:data})
      callback && callback(select)
    }
  })
}

function entry2select(entry, rx) {

  var notes = []

  if (entry.gsx$stock.$t == 'Out of Stock')
    notes.push(entry.gsx$stock.$t)

  if (entry.gsx$stock.$t == 'Refills Only' && ( ! rx || ! rx.is_refill))
    notes.push(entry.gsx$stock.$t)

  if (rx && ! rx.refills_total)
    notes.push("No Refills")

  notes = notes.join(', ')

  var price = entry.gsx$day_2.$t || entry.gsx$day.$t,
       days = entry.gsx$day_2.$t ? '90 days' : '45 days',
       drug = ' '+entry.gsx$_cokwr.$t+', $'+price+' for '+days

  return {
    id:entry.gsx$_cokwr.$t,
    text: drug + (notes ? ' ('+notes+')' : ''),
    disabled:!!notes,
    price:price
  }
}

function upgradeRxs(callback) {

  console.log('upgradeRxs')

  var select = jQuery('#rxs\\[\\]')

  var rxs = select.data('rxs')
  console.log('data-rxs', typeof rxs, rxs.length, rxs)
  //if (rxs.length) medication.val(rxs).change()

  //select.empty()

  var medicationGsheet = "https://spreadsheets.google.com/feeds/list/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/od6/public/values?alt=json"
  //od6 is the worksheet id.  To get this you have to use https://spreadsheets.google.com/feeds/worksheets/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/private/full
  jQuery.ajax({
    url:medicationGsheet,
    type: 'GET',
    cache:true,
    success:function($data) {
      console.log('getOrderRxs medications gsheet')
      var data = []
      for (var j in rxs) {
        var rx = rxs[j]
        for (var i in $data.feed.entry) {
          var entry = $data.feed.entry[i]
          rx.regex = rx.regex || new RegExp('\\b'+rx.gcn_seqno+'\\b')
          if (entry.gsx$gcns.$t.match(rx.regex)) {
            data.push(entry2select(entry, rx))
            break
          } else if (i+1 == $data.feed.entry.length) {
            data.push({ //No match found
              id:rx.drug_name.slice(1, -1),
              text: rx.drug_name.slice(1, -1) + ' (GCN Error)',
              disabled:true,
              price:0
            })
          }
        }
      }
      console.log('getOrderRxs medications gsheet', data)
      select.select2({multiple:true, closeOnSelect:true, data:data})
      select.val(data.map(function(drug) { return ! drug.disabled && drug.id })).change()

      callback && callback(select)
    }
  })
}


/*
(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
})(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

ga('create', 'UA-102235287-1', 'auto');
ga('send', 'pageview');
*/
