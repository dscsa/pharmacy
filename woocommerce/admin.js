jQuery(load)

function load() {

  upgradeMedication()
  upgradePharmacy()
  upgradeAllergies()
  upgradeBirthdate()

  //Toggle medication select and backup pharmacy text based on whether
  //Rx is being sent from doctor or transferred from a pharmacy.
  setSource()
}
