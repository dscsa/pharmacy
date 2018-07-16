jQuery(load)

function load() {

  upgradeOrdered(function(ordered) {
    var rxs = ordered.data('rxs')
    console.log('ordered data-rxs', typeof rxs, rxs.length, rxs)
    if (rxs.length) ordered.val(rxs).change()
  })

  upgradePharmacy()
  upgradeAllergies()
  upgradeBirthdate()

  //Toggle medication select and backup pharmacy text based on whether
  //Rx is being sent from doctor or transferred from a pharmacy.
  setSource()
}
