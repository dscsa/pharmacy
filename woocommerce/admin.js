jQuery(load)

function load() {

  upgradeMedication(false, function(medication) {
    var rxs = medication.data('rxs')
    console.log('data-rxs', typeof rxs, rxs.length, rxs)
    if (rxs.length) medication.val(rxs).change()
  })

  upgradePharmacy()
  upgradeAllergies()
  upgradeBirthdate()

  //Toggle medication select and backup pharmacy text based on whether
  //Rx is being sent from doctor or transferred from a pharmacy.
  setSource()
}
