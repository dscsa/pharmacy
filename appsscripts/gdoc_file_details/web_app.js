function doGet(e) {
    if (e.parameter.GD_KEY != 'Patients1st!') {
      return ContentService
        .createTextOutput("Access Denied")
        .setMimeType(ContentService.MimeType.JSON)
    }

    var fileId   = e.parameter.fileId;
    var file     = DriveApp.getFileById(fileId);
    var parent   = file.getParents().next();
    var response = {
      name:file.getName(),
      id:file.getId(),
      trashed:file.isTrashed(),
      parent:{ 
        name:parent.getName(), 
        id:parent.getId()
      }
    }
  
    return ContentService
      .createTextOutput(JSON.stringify(response))
      .setMimeType(ContentService.MimeType.JSON)

}