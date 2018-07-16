jQuery(load)

function load() {

  upgradeOrdered(function(select) {
    var rxs = select.data('rxs')
    console.log('ordered data-rxs', typeof rxs, rxs)
    select.val(rxs).change()
    select.on("select2:unselecting", preventDefault)
  })

  upgradePharmacy()
  upgradeAllergies()
  upgradeBirthdate()

  //Toggle medication select and backup pharmacy text based on whether
  //Rx is being sent from doctor or transferred from a pharmacy.
  setSource()
}
