function preventDefault(e) {
  console.log('select2 preventDefault')
  e.preventDefault()
}

//Helps providers signout easier. Also prevents setting the ?register when signed in
function signup2signout() {
  jQuery('li#menu-item-10 a, li#menu-item-103 a').html('<span class="english">Sign Out</span><span class="spanish">Cierre de sesión</span>').prop('href', jQuery('.woocommerce-MyAccount-navigation-link--customer-logout a').prop('href'))
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


//Used at checkout and on account details
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
  var email = jQuery('#email').val() || jQuery('#account_email').val() || jQuery('#reg_email').val()
  if(email && /.+?_.+?_\d{4}-\d{2}-\d{2}@goodpill.org/.test(email)) {
    jQuery('#email').val('')
    jQuery('#account_email').val('')
    jQuery('#reg_email').val('')
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

//Used in 2 places: Admin / Order Confirmation (inserted inline via amazon_ami.php).
function upgradeOrdered(callback) {
  console.log('upgradeOrdered')

  var select = jQuery('#ordered\\[\\]')

  var rxs = select.data('rxs') || []
  console.log('data-rxs', typeof rxs, rxs.length, rxs)

  var data = rxs.map(function(rx) { return { id:rx, text:rx }})

  if ( ! select.select2) return console.log('No select.select2 function', select)

  select.select2({multiple:true, data:data})
  select.val(rxs).change()
  select.on("select2:unselecting", preventDefault)
}

//Returns object {[gcn]:rx}
function getRxMap() {
  console.log('getRxMap')
  var select = jQuery('#rxs\\[\\]')
  var rxs  = select.data('rxs')
  var rxMap = {} //Put Rxs into an object {gcn:rx} for easy lookups in filterInventory and filterRxs
  console.log('data-rxs', typeof rxs, rxs && rxs.length, rxs)

  for (var i in rxs || []) {
     var rx = {
      name:rxs[i].drug_name.slice(1, -1), //remove quotes
      script_no:rxs[i].script_no,
      refills_total:rxs[i].refills_total,
      is_refill:rxs[i].is_refill,
      gcn:rxs[i].gcn_seqno,
      status:rxs[i].script_status
    }

    rx.text = rx.name+', Rx:'+rx.script_no //this is what select 2 displays to the user
    rx.id = JSON.stringify(rx) //this is what select2 passes back in $_POST e.g. $_POST == ['rxs[]' =>  [id1, id2, id3]]
    rxMap[rx.gcn] = rx
  }

  return rxMap
}

function getInventory(callback) {
  var medicationGsheet = "https://spreadsheets.google.com/feeds/list/1gF7EUirJe4eTTJ59EQcAs1pWdmTm2dNHUAcrLjWQIpY/o8csoy3/public/values?alt=json"
  //o8csoy3 is the worksheet id.  To get this you have to use https://spreadsheets.google.com/feeds/worksheets/1gF7EUirJe4eTTJ59EQcAs1pWdmTm2dNHUAcrLjWQIpY/private/full
  jQuery.ajax({
    url:medicationGsheet,
    type: 'GET',
    cache:false,
    success:function($data) {
      console.log('medications gsheet retrieved')
      callback(mapGoogleSheetInv($data.feed.entry))
    }
  })
}

//Google Sheets have a weird schema based on header row names. Let's convert it to something more friendly
function mapGoogleSheetInv(inventory) {
  console.log('mapGoogleSheetInv', inventory)
  return inventory.map(function(row) {
    var drug = {
      name:row.gsx$_cn6ca.$t,
      price:{
        amount:row['gsx$pricemonth'].$t * (row['gsx$inventory.qty'].$t < 1000 ? 1.5 : 3) || '',
        days:row['gsx$inventory.qty'].$t < 1000 ? '45 days' : '90 days'
      },
      gcns:row['gsx$key.3'].$t.split(','),
      stock:row.gsx$stock.$t.replace('- Hidden', 'Stock') //Say "Low Stock" instead of "Low - Hidden"
    }

    drug.text = drug.name+', $'+drug.price.amount+' for '+drug.price.days //this is what select 2 displays to the user
    drug.id = JSON.stringify(drug) //this is what select2 passes back in $_POST e.g. $_POST == ['rxs[]' =>  [id1, id2, id3]]

    return drug
  })
}

//Enable/Disable Inventory (Transfers) Based on RxMap
function disableInventory(inventory, rxMap) {

  for (var i in inventory) {
    var drug = inventory[i]

    if ( ! drug.stock) continue  //High supply inventory is available no matter the RXs

    drug.text += ' ('+drug.stock+')'
    drug.disabled = true //By default if its a low stock item

    if (drug.stock != 'Out of Stock' && drug.stock != 'Not Offered') //Out of stock items should be shown but disabled as to not allow transfers (I think???))
      for (var i in drug.gcns) {
        var drugGcn = drug.gcns[i]
        if (rxMap[drugGcn] && rxMap[drugGcn].is_refill) {
          console.log('Despite low stock, allowing transfer of ', drug, rxMap[drugGcn])
          drug.disabled = false //Enable low supply items that are not Out of Stock only if they are refills
        }
      }
  }

  return inventory
}

//Enables/Disables RXs (eRx and Refill Requests) Based on Inventory
function disableRxs(inventory, rxMap) {

  mainloop: for (var gcn in rxMap) {
    var rx = rxMap[gcn]

    console.log('disableRxs', rx)

    if (rx.status == 'Transferred Out') {
      rx.text += ' (Transferred Out)'
      rx.disabled = true //No Refill Rxs should be disabled
      continue
    }
    else if ( ! rx.refills_total) { //this could be caused from the rx being transferred out
      rx.text += ' (No Refills)'
      rx.disabled = true //No Refill Rxs should be disabled
      continue
    }

    for (var i in inventory) {
      var drug = inventory[i]

      for (var j in drug.gcns) {
        var drugGcn = drug.gcns[j]

        if (gcn != drugGcn) continue

        if (drug.stock == 'Out of Stock' || drug.stock == 'Not Offered') {
          rx.text += ' ('+drug.stock+')'
          rx.disabled = true //disable low stock non-refills
        }
        else if (drug.stock && ! rx.is_refill) {
          rx.text += ' ('+drug.stock+')'
          rx.disabled = true //disable low stock non-refills
        }

        continue mainloop //skip unneccessary processing once an inventory match is found
      }
    }

    //No match in live inventory
    rx.text += ' (No Match Found)'
    rx.disabled = true //disable low stock non-refills
  }

  return rxMap
}

function objValues(obj) {
  return Object.keys(obj).map(function(key) { return obj[key] })
}

function savePaymentCard() {
  //Save card info to our account automatically
  console.log('savePaymentCard checkbox')
  jQuery('#wc-stripe-new-payment-method').prop('checked', true)
}
