
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

function makeCopy(oldFile, copyName, copyFolder) {
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
