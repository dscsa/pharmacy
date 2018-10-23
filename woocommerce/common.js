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

function upgradeAutofill() {
  var rx_autofills = jQuery("input.rx_autofill")

  //When RX Autofill is unchecked, disable refill date input and have it display N/A
  //When RX Autofill is checked have it display placeholder of next-fill and allow user to select date
  rx_autofills.on('change', function(){
    var elem  = jQuery(this)
    var row   = elem.closest('tr.rx')
    var input = row.find('.next_fill')
    var disabled = row.hasClass('nextfill-disabled')
    var off = row.hasClass('autofill-off')
    console.log('toggle rx autofill', this.checked, disabled, input.val(), input.attr('next-fill'), input.attr('default'))
    if (off) {
      elem.prop('checked', false)
      elem.prop('disabled', true)
    }
    input.prop('disabled',  disabled)

    var placeholder = 'Upon Request' 

    if (disabled)
      placeholder = 'N/A'
    else if (this.checked)
      placeholder = input.attr('next-fill')

    input.prop('placeholder', placeholder)
  })

  //When Patient Autofill is unchecked, uncheck and disable all Rx Autofill checkboxes and uncheck the "New Rx" autofill
  //When Patient Autofill is checked, enable all Rx Autofills (but don't check them) and check the "New Rx" autofill
  jQuery(".pat_autofill input").on('change', function(){
    console.log('toggle patient autofill', this.checked)
    rx_autofills.prop('disabled', ! this.checked)
    if ( ! this.checked) rx_autofills.prop('checked', false)
    jQuery("input.new_rx_autofill").prop('checked', this.checked)
    rx_autofills.trigger('change') //triggerHandler only works on first matched element
  })

  //Put date picker UI on any enabled next-fill input
  jQuery(".next_fill").each(function() {
    var elem = jQuery(this)
    elem.datepicker({changeMonth:true, changeYear:true, yearRange:"c:c+1", dateFormat:"yy-mm-dd", constrainInput:true})
  })

  //Disable and set days based for new Rxs based on live inventory
  getInventory(function(inventory) {
    console.log('upgradeAutofill getInventory', 'inventory.length', inventory.length)

    jQuery("tr.rx").each(function(i, tableRow) {
      tableRow     = jQuery(tableRow)
      var gcn      = tableRow.attr('gcn')
      var regex    = new RegExp('\\b'+gcn+'\\b')
      console.log('upgradeAutofill', i, gcn)

      var nextFill = tableRow.find(".next_fill")
      //We could do this in PHP but doing here because of the parrallel with refills-only, out-of-stock, and gcn-error

      if (nextFill.val() == 'No Refills') {
        tableRow.addClass('nextfill-disabled')
        console.log('upgradeAutofill No Refills', i, gcn, nextFill.val(), nextFill)
        return
      }

      if (nextFill.val() == 'Transferred') {
        tableRow.addClass('nextfill-disabled autofill-off')
        console.log('upgradeAutofill Transferred', i, gcn, nextFill.val(), nextFill)
        return
      }

      for (var j in inventory) {

        var row = inventory[j]

        if ( ! row['gsx$key.3'].$t.match(regex)) continue

        if (row.gsx$stock.$t == 'Refills Only' && tableRow.hasClass('new')) {
          tableRow.addClass('nextfill-disabled autofill-off')
          nextFill.val('Refills Only')
          console.log('upgradeAutofill Refills Only', row.gsx$_cokwr.$t, i, gcn, nextFill.val(), nextFill)
          jQuery("tr.rx td.day_qty").val(45)
          break
        }

        if (row.gsx$stock.$t == 'Out of Stock') {
          console.log('upgradeAutofill Out of Stock', row.gsx$_cokwr.$t, i, gcn, nextFill.val(), nextFill)
          tableRow.addClass('nextfill-disabled autofill-off')
          nextFill.val('Out of Stock')
          jQuery("tr.rx td.day_qty").val(45)
          break
        }
      }

      if (j == inventory.length) {
        console.log('upgradeAutofill Gcn Error', row.gsx$_cokwr.$t, i, gcn, nextFill.val(), nextFill)
        tableRow.addClass('nextfill-disabled')
        nextFill.val('Gcn Error')
        jQuery("tr.rx td.day_qty").val('')
      }
    })

    jQuery(".pat_autofill input").triggerHandler('change')
  })
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

//Used in 2 places: Admin / Order Confirmation.
function upgradeOrdered(callback) {
  console.log('upgradeOrdered')

  var select = jQuery('#ordered\\[\\]')

  var rxs = select.data('rxs') || []
  console.log('data-rxs', typeof rxs, rxs.length, rxs)

  var data = rxs.map(function(rx) { return { id:rx, text:rx }})

  select.select2({multiple:true, data:data})
  select.val(rxs).change()

  callback && callback(select)
}

//On Admin and Checkout
function upgradeTransfer(callback) {
  console.log('upgradeTransfer')
  return _upgradeMedication('transfer', callback, function(inventory, select) {
    select.empty() //get rid of default option
    return inventory.filter(function(row) { return row.gsx$ordered.$t }) //not sure if its necessary here but for consisttency: weird JS quick '' && true -> ''
  })
}

//On Admin and Checkout
function upgradeStock(callback) {
  console.log('upgradeStock')
  return _upgradeMedication('stock', callback, function(inventory) {
    return inventory.filter(function(row) {
      return row.gsx$ordered.$t && ! row.gsx$stock.$t
    })
  })
}

function upgradeRxs(callback) {
  console.log('upgradeRxs')

  return _upgradeMedication('rxs', callback, function(inventory, select) {
    var data = []
    var rxs  = select.data('rxs')
    console.log('data-rxs', typeof rxs, rxs.length, rxs)
    for (var i in rxs) {
      var rx = rxs[i]
      console.log('upgradeRxs', rx.drug_name, i)
      var regex = new RegExp('\\b'+rx.gcn_seqno+'\\b')

      data.push({ //Default Value assuming no match found
        gsx$_cokwr: {$t: rx.drug_name.slice(1, -1)},
        gsx$stock : {$t:'GCN Error'},
        "gsx$order.price90": {$t:'??'}
      })

      for (var j in inventory) {
        var row = inventory[j]

        if (row['gsx$key.3'].$t.match(regex)) {
          if (row.gsx$stock.$t == 'Refills Only' && rx.is_refill)
            delete row.gsx$stock.$t

          if (row.gsx$stock.$t == 'Low - Hidden')
            delete row.gsx$stock.$t

          if ( ! rx.refills_total)
            row.gsx$stock.$t = 'No Refills'

          console.log('upgradeRxs gcn match', rx.drug_name, rx.gcn_seqno, row['gsx$key.3'].$t, i, j)
          data[data.length-1] = row //overwrite the default value

          break

        }
      }
    }
    return data
  })
}

//Used in 2 places: Check Our Stock, Transfers
function _upgradeMedication(selector, callback, transform) {
  var select = jQuery('#'+selector+'\\[\\]')

  getInventory(function(inventory) {
    console.log('_upgradeMedication', 'inventory.length', inventory.length)
    var data = transform(inventory, select).map(row2select)
    console.log('_upgradeMedication', 'transform.length', data.length)
    select.select2({
      multiple:true,
      closeOnSelect:selector != 'stock',
      data:data
    })
    callback && callback(select, data)
  })
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
      callback(Object.freeze($data.feed.entry))
    }
  })
}

function row2select(row) {

  if ( ! row.gsx$_cokwr || ! (row['gsx$price45'] && row['gsx$order.price90']))
    console.error('row2select error', row)

  var drug = row.gsx$_cokwr.$t,
      price = row['gsx$order.price90'].$t || row['gsx$price45'].$t || '',
      notes = []

  if (row.gsx$stock.$t)
    notes.push(row.gsx$stock.$t)

  notes = notes.join(', ')

  if (notes) {
    notes = ' ('+notes+')'
  }

  if (price) {
    var days = row['gsx$order.price90'].$t ? '90 days' : '45 days',
    price = ', $'+price+' for '+days
  }

  return {
    id:drug,
    text: ' '+drug + price + notes,
    disabled:!!notes,
    price:price
  }
}


/*
(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
})(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

ga('create', 'UA-102235287-1', 'auto');
ga('send', 'pageview');
*/
