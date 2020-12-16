function test_copy_invoice() {
  Log("Getting a copy of 'Invoice Template v1'")
  var template = fileByName("Invoice Template v1")
  var newDoc   = makeCopy(template, "Ben Test Doc", "Pending")
  Log(newDoc.getId())
  var template = fileByName("Invoice Template v1")
  var newDoc   = makeCopy(template, "Ben Test Doc 2", "Pending")
  Log(newDoc.getId())
  return newDoc.getId();
  
}
