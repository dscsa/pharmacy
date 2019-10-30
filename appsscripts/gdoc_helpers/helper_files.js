function removeFiles(title, folderId) {
  var folder = DriveApp.getFolderById(folderId)
  var iterator = folder.searchFiles('title contains "'+title+'"')

  while (iterator.hasNext()) {
    iterator.next().setTrashed(true) //Prevent printing an old list that Cindy pended and shipped on her own
    infoEmail('deleteShoppingLists', orderID, res && res.getContentText(), res && res.getResponseCode(), res && res.getHeaders())
  }
}
