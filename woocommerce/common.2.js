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
function upgradePharmacy(retry) {

  var select = jQuery('#backup_pharmacy')

  if (window.sessionStorage) {
    var pharmacyCache = sessionStorage.getItem('pharmacyCache')
    console.log('upgradePharmacy, cached:', pharmacyCache || 'No Cache')
    if (false) return select.select2({data:pharmacyCache, matcher:matcher, minimumInputLength:3})
  }

  var start = new Date()
  var pharmacyGsheet = "https://spreadsheets.google.com/feeds/list/1ivCEaGhSix2K2DvgWQGvd9D7HmHEKA3VkQISbhQpK8g/1/public/values?alt=json"
  retry = retry || 1000
  //ovrg94l is the worksheet id.  To get this you have to use https://spreadsheets.google.com/feeds/worksheets/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/private/full

  if ( ! select.select2) {
    console.error('No select.select2 function in upgradePharmacy', 'retry', retry, select)
    setTimeout(function() { upgradePharmacy(retry*2) }, retry)
  }

  jQuery.ajax({
    url:pharmacyGsheet,
    method:'GET', //dataType: 'jsonp', //USED to be method:'GET' until this bug https://issuetracker.google.com/issues/131613284#comment98
    success:function($data) {
      console.log('pharmacy gsheet. load time in secs:', (new Date()-start)/1000)
      var pharmacyCache = []
      for (var i in $data.feed.entry) {
        pharmacyCache.push(pharmacy2select($data.feed.entry[i]))
      }

      if (window.sessionStorage)
        sessionStorage.setItem('pharmacyCache', pharmacyCache)

      select.select2({data:pharmacyCache, matcher:matcher, minimumInputLength:3})
      console.log('pharmacy gsheet. finish time in secs:', (new Date()-start)/1000)
    },
    error:function() {
      console.error('COULD NOT GET PHARMACY SPREADSHEET', 'retry', retry)
      setTimeout(function() { upgradePharmacy(retry*2) }, retry)
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

//1669909883	Good Pill Pharmacy - Do not transfer my Rx(s) if out of stock	1780 Coporate Dr. Suite 420	Norcross	GA	30093	888-987-5187	888-298-7726
function pharmacy2select(entry, i) {

  var store = entry
    ? {
        fax:entry.gsx$fax.$t,
        phone:entry.gsx$phone.$t,
        npi:entry.gsx$npi.$t,
        street:entry.gsx$street.$t,
        city:entry.gsx$city.$t,
        state:entry.gsx$state.$t,
        zip:entry.gsx$zip.$t,
        name:entry.gsx$name.$t
      }
    : {
        fax:'',
        phone:'888-987-5187',
        npi:'',
        street:'',
        city:'',
        state:'',
        zip:'',
        name:'Error Loading Pharmacies.  Please call us to report'
      }

  var text = store.name+', '+store.street+', '+store.city+', '+store.state+' '+store.zip+' - Phone: '+store.phone
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

  if ( ! select.select2) return console.log('No select.select2 function in upgradeOrdered', select)

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

function getInventory(callback, retry) {

  console.log('getInventory')

  var start = new Date()
  var medicationGsheet = "https://spreadsheets.google.com/feeds/list/1gF7EUirJe4eTTJ59EQcAs1pWdmTm2dNHUAcrLjWQIpY/o8csoy3/public/values?alt=json"
  retry = retry || 1000
  //o8csoy3 is the worksheet id.  To get this you have to use https://spreadsheets.google.com/feeds/worksheets/1gF7EUirJe4eTTJ59EQcAs1pWdmTm2dNHUAcrLjWQIpY/private/full
  jQuery.ajax({
    url:medicationGsheet,
    method:'GET', //dataType: 'jsonp', //USED to be method:'GET' until this bug https://issuetracker.google.com/issues/131613284#comment98
    success:function($data) {
      console.log('live inventory gsheet. load time in secs:', (new Date()-start)/1000)
      callback(mapGoogleSheetInv($data.feed.entry))
      console.log('live inventory gsheet. finish time in secs:', (new Date()-start)/1000)
    },
    error:function() {
      console.error('COULD NOT GET LIVE INVENTORY', 'retry', retry)
      setTimeout(function() { getInventory(callback, retry*2) }, retry)
    }
  })
}

//Google Sheets have a weird schema based on header row names. Let's convert it to something more friendly
function mapGoogleSheetInv(inventory) {
  console.log('mapGoogleSheetInv', inventory)
  return inventory.map(function(row) {
    var lowStock = row.gsx$stock.$t && row['gsx$inventory.qty'].$t < 1000
    var drug = {
      name:row.gsx$_cn6ca.$t,
      price:{
        amount:row['gsx$pricemonth'].$t * (lowStock ? 1.5 : 3) || '',
        days:lowStock ? '45 days' : '90 days'
      },
      gcns:row['gsx$key.3'].$t.split(','),
      stock:row.gsx$stock.$t.replace('- Hidden', 'Stock') //Say "Low Stock" instead of "Low - Hidden"
    }

    drug.text = drug.name+', $'+drug.price.amount+' for '+drug.price.days //this is what select 2 displays to the user
    drug.id = JSON.stringify(drug) //this is what select2 passes back in $_POST e.g. $_POST == ['rxs[]' =>  [id1, id2, id3]]

    return drug
  })
}

function getPriceComparison(callback, retry) {

  console.log('getPriceComparison')

  var start = new Date()
  var medicationGsheet = "https://spreadsheets.google.com/feeds/list/1TcuoHKR8vJ8j3AhVVJywqEvPz7-ecef5O05RywPQj_U/od6/public/values?alt=json"
  retry = retry || 1000
  //o8csoy3 is the worksheet id.  To get this you have to use https://spreadsheets.google.com/feeds/worksheets/1gF7EUirJe4eTTJ59EQcAs1pWdmTm2dNHUAcrLjWQIpY/private/full
  jQuery.ajax({
    url:medicationGsheet,
    method:'GET', //dataType: 'jsonp', //USED to be method:'GET' until this bug https://issuetracker.google.com/issues/131613284#comment98
    success:function($data) {
      console.log('price comparison gsheet. load time in secs:', (new Date()-start)/1000)
      callback(mapGoogleSheetPrices($data.feed.entry))
      console.log('price comparison gsheet. finish time in secs:', (new Date()-start)/1000)
    },
    error:function() {
      console.error('COULD NOT GET PRICE COMPARISON', 'retry', retry)
      setTimeout(function() { getPriceComparison(callback, retry*2) }, retry)
    }
  })
}

//Google Sheets have a weird schema based on header row names. Let's convert it to something more friendly
function mapGoogleSheetPrices(inventory) {
  console.log('mapGoogleSheetPrices', inventory)
  return inventory.map(function(row) {
    var drug = {
      name:row.gsx$_cn6ca.$t,
      price:{
        amount:row['gsx$pricemonth'].$t * 3 || '',
        days:'90 days',
        pharmacy1:row['gsx$pharmacyprice1'].$t,
        pharmacy2:row['gsx$pharmacyprice2'].$t,
        pharmacy3:row['gsx$pharmacyprice3'].$t
      },
      //gcns:row['gsx$key.3'].$t.split(','),
      stock:row.gsx$stock.$t.replace('- Hidden', 'Stock'), //Say "Low Stock" instead of "Low - Hidden"
    }

    drug.text = drug.name+', $'+drug.price.amount+' for '+drug.price.days //this is what select 2 displays to the user

    if (drug.price.pharmacy1)
      drug.text += '. '+drug.price.pharmacy1+' | '+drug.price.pharmacy2+' | '+drug.price.pharmacy3 //this is what select 2 displays to the user

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
        else if (drug.stock && drug.stock != 'Low - Hidden' && ! rx.is_refill) {
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



var storage = {
  get:function(key, maxAge) {
    if ( ! window.sessionStorage) return
    if (window.sessionStorage) {
      var pharmacyCache = sessionStorage.getItem('pharmacyCache')
      console.log('upgradePharmacy, cached:', !!pharmacyCache)
      if (pharmacyCache) return select.select2({data:pharmacyCache, matcher:matcher, minimumInputLength:3})
    }

  },
  set:function(key, val) {
    if ( ! window.sessionStorage) return

  }
}
