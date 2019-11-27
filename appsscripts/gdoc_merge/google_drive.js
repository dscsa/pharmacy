
var fileCache = {}
function fileByName(name) {
  if (fileCache[name]) return fileCache[name]
  var file = DriveApp.getFilesByName(name).next()
  fileCache[name] = DocumentApp.openById(file.getId());
  return fileCache[name]
}

var filesCache = {}
function filesByName(name) {

  if (filesCache[name]) return filesCache[name]

  var matches = DriveApp.searchFiles('title contains "'+name+'"')

  var files = []
  while (matches.hasNext()) {
    var doc = DocumentApp.openById(matches.next().getId());
    files.push(doc)
  }
  return filesCache[name] = files
}

var folderCache = {}
function folderByName(name) {
  if (folderCache[name]) return folderCache[name]
  return folderCache[name] = DriveApp.getFoldersByName(name).next()
}

function parentByFile(file) {

  try {
    return file.getParents().next()
  } catch(e) {
    return DriveApp.getRootFolder()
  }
}

//TODO Right now this makes a new file with a new id.  Google Apps Script also does not currently allow the creation of a new revision
//to an existing doc, or restore a revision (the initial template) so that we can re-mail merge.  BUT we could create the new doc, append
//content to old doc, erase old doc content and then delete the new doc: https://ctrlq.org/code/19892-merge-multiple-google-documents
//This seems a lot of work to just not have multiple invoices, so skipping for right now but may be worth revisiting in the future
function makeCopy(oldFile, copyName, copyFolder) {
   oldFile = DriveApp.getFileById(oldFile.getId()) //Class Document doesn't have makeCopy need Class File
   var newFile = oldFile.makeCopy(copyName)
   parentByFile(newFile).removeFile(newFile)
   folderByName(copyFolder).addFile(newFile)
   publishToWeb(newFile)
   return DocumentApp.openById(newFile.getId())
}

//Drive (not DriveApp) must be turned on under Resources -> Advanced Google Services -> Drive
//https://stackoverflow.com/questions/40476324/how-to-publish-to-the-web-a-spreadsheet-using-drive-api-and-gas
function publishToWeb(file){
  file.setOwner('admin@sirum.org') //support@goodpill.org can only publish files that require sirum sign in
  var fileId = file.getId()
  var revisions = Drive.Revisions.list(fileId);
  var items = revisions.items;
  var revisionId = items[items.length-1].id;
  var resource = Drive.Revisions.get(fileId, revisionId);
  resource.published = true;
  resource.publishAuto = true;
  resource.publishedOutsideDomain = true;
  resource = Drive.Revisions.update(resource, fileId, revisionId);
}
