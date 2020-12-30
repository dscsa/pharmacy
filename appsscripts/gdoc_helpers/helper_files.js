/**
 * Mark a file  as published and set the appropriate revisions
 * @param  {object} opts The data passed into the web app
 * * * Publishing via search
 *          opts.folder  (Required) The folder to search.
 *          opts.file    (Required) The file name to search for.
 * * * Publishing specific file
 *          opts.fileId  (Required) The ID of the file we want to publish Not need if using the search
 * @return {object}
 */
function publishFile(opts) {

    if (opts.fileId) {
        var file = DriveApp.getFileById(opts.fileId);
    } else {
        var folder = DriveApp.getFoldersByName(opts.folder).next();
        var file = folder.searchFiles('title contains "' + opts.file + '"')
        file = file.next()
    }

    if (!file) {
        console.warn("publishFile: File Not Found", {
            "opts":opts
        });

        return {'error':"File Not Found"};
    }

    var fileId = file.getId()

    console.log('publishFile: Publishing fileName:' + file.getName() + " fileId:" + file.getId());

    /*
      Side effect of this is that this account can no longer delete/trash/remove
      this file since must be done by owner
     */

    if (file.getOwner().getEmail() != 'webform@goodpill.org') {
        console.warn("Files set to wrong owner", {
            "fileId":file.getId(),
            "fileName":file.getName(),
            "fileOwner":file.getOwner().getEmail(),
            "current_user":Session.getEffectiveUser().getEmail()
        });

        file.setOwner('webform@sirum.org')
    }

    var revisions = Drive.Revisions.list(fileId);
    var items = revisions.items;
    var revisionId = items[items.length - 1].id;
    var resource = Drive.Revisions.get(fileId, revisionId);

    resource.published = true;
    resource.publishAuto = true;
    resource.publishedOutsideDomain = true;
    resource = Drive.Revisions.update(resource, fileId, revisionId);

    return resource
}


/**
 * Move a file from one folder to another.  Will try to move the file twice
 * @param  {object} opts The data passed into the web ap
 * * * Move via search
 *          opts.fromFolder  (Required) The folder moving from.
 *          opts.toFolder    (Required) The folder moving to.
 *          opts.file        (Required) The file name to search for.
 * * * Move specific file
 *          opts.fileId      (Required) The ID of the file we want to move
 *          opts.toFolder    (Required) The folder moving to.
 * @param  {boolean} retry Is this our second attempt
 * @return {boolean}
 */
function moveFile(opts, retry) {

    if (opts.fileId) {
        var file = DriveApp.getFileById(opts.fileId);
    } else {
        var fromFolder = DriveApp.getFoldersByName(opts.fromFolder).next()
        var file = fromFolder.searchFiles('title contains "' + opts.file + '"')
        file = file.next()
    }

    if (file) {
        console.log('moveFile: Moving fileName:' + file.getName() + " fileId:" + file.getId());
        return moveToFolder(file, opts.toFolder)
    }

    if (!retry) {
        Utilities.sleep(30000)
        moveFile(opts, true)
    }

    console.warn("moveFile: File Not Found", {
        "opts":opts,
        "attempt":(retry ? 2 : 1)
    });

    return false;
}

/**
 * Remove files
 * @param  {object} opts The data passed into the web ap
 * * * Remove Via Search
 *          opts.folder    (Required) The folder to search and remove files from.
 *          opts.file      (Required) The file name to search for. All files with this
 *                              name will be removed from the folder
 * * * Remove Specific File
 *          opts.fileId    (Required) The ID of the file we want to remove.
 * @return {object}
 */
function removeFiles(opts) {

    var res = []

    if (opts.fileId) {
        var file = DriveApp.getFileById(opts.fileId);
        file.setTrashed(true);
        try {
            //Prevent printing an old list that Cindy pended and shipped on her own
            file.setTrashed(true)
            res.push(['success', file.getUrl(), file.getName(), file.getOwner()])
        } catch (e) {
            //e.g., Error: "Access denied: DriveApp."
            res.push(['error', file.getUrl(), file.getName(), file.getOwner()])
        }
    } else {
        var folder = DriveApp.getFoldersByName(opts.folder).next()
        var iterator = folder.searchFiles('title contains "' + opts.file + '"')

        while (iterator.hasNext()) {
            var file = iterator.next()
            try {
                //Prevent printing an old list that Cindy pended and shipped on her own
                file.setTrashed(true)
                res.push(['success', file.getUrl(), file.getName(), file.getOwner()])
            } catch (e) {
                //e.g., Error: "Access denied: DriveApp."
                res.push(['error', file.getUrl(), file.getName(), file.getOwner()])
            }
        }
    }

    return ['removeFiles', opts, res]
}


/**
 * Watch for changes to files.  If changes are found, send back
 * the details of what files have been changed
 *
 * @param  {object} opts The data passed into the web app
 *                  opts.minutes (Optional) How many minutes since last change
 *                                  defaults to 20
 *                  opts.folder  (Required) The name of the folder to look in
 * @return {object} The items that were found in the parent, printed and faxed
 */
function watchFiles(opts) {

    var today     = new Date();
    var minutes   = opts.minutes || 20;
    var startTime = new Date(today.getTime() - minutes * 60 * 1000);

    //Don't call if we are still making edits
    var tooRecent     = new Date(today.getTime() - 1 * 60 * 1000);
    var parentFolder  = DriveApp.getFoldersByName(opts.folder).next();
    var printedFolder = parentFolder.getFoldersByName('Printed');
    var faxedFolder   = parentFolder.getFoldersByName("Faxed");
    var query         = 'modifiedDate > "' + startTime.toJSON() + '" AND modifiedDate < "' + tooRecent.toJSON() + '"';
    var iterator      = parentFolder.searchFiles(query);

    console.log('Searching for files in %s with params %s', opts.folder, query);

    var parent = []
    while (iterator.hasNext()) {
        var file = isModified(iterator.next(), opts)
        if (file) parent.push(file)
    }

    var printed = []
    if (printedFolder.hasNext()) {
        var iterator = printedFolder.next().searchFiles(query)
        while (iterator.hasNext()) {
            var file = isModified(iterator.next(), opts)
            if (file) printed.push(file)
        }
    }

    var faxed = []
    if (faxedFolder.hasNext()) {
        var iterator = faxedFolder.next().searchFiles(query)
        while (iterator.hasNext()) {
            var file = isModified(iterator.next(), opts)
            if (file) faxed.push(file)
        }
    }

    return {
        'parent': parent,
        'printed': printed,
        'faxed': faxed
    }
}

/**
 * Create a new spreadsheet
 * @param  {object} opts The data from the webrequest
 * @return {[type]}      [description]
 */
function newSpreadsheet(opts) {

    var ss = SpreadsheetApp.create(opts.file)
    var file = DriveApp.getFileById(ss.getId())

    if (opts.vals) {
        ss.getActiveSheet()
            .getRange(1, 1, opts.vals.length, opts.vals[0].length)
            .setValues(opts.vals)
            .setHorizontalAlignment('left')
            .setFontFamily('Roboto Mono')
    }

    var widths = opts.widths || {}
    for (var col in widths) {
        ss.setColumnWidth(col, widths[col]); //show the full id when it print
    }

    moveToFolder(file, opts.folder)
}

/*
    Supporting Functions
 */

 /**
  * Move a file to a specific folder name
  * @param  {File}   file     A File object to move
  * @param  {string} folder   The name of the file to move
  * @return {File}            The file after it has been moved
  */
 function moveToFolder(file, folder) {
   if (!folder ) return

   var toFolder = DriveApp.getFoldersByName(folder).next();

   if (!toFolder) {
       console.warn('moveToFolder: folder not found', folder);
       return;
   }

   console.log('moveToFolder: Moving ' + file.getName() + ' to ' + toFolder.getName());

   file.moveTo(toFolder);

   return file;
 }


 /**
  * Looks at the data on the file to see if it has been modified.  Checks
  * the title of the file as well as the last modified timestamp.  Creates an
  * object containing various meta data about the file
  *
  * @param  {object}   next The file to check
  * @param  {object}   opts The objections passed in
  * @return {object}        The modification data about the file
  */
 function isModified(next, opts) {

   var file = {
     name:next.getName(),
     id:next.getId(),
     url:next.getUrl(),
     date_modified:next.getLastUpdated(),
     date_created:next.getDateCreated()
   }

   //If don't want watch to keep returning the same file with the same change multiple times
   var lastEdit = file.name.split(' Modified:')

   file.lastEdit = lastEdit[1]
   file.newFile  = (file.date_modified - file.date_created) < 10 * 60 * 1000 //1 minute
   file.newEdit  = file.newFile ? false : ( ! file.lastEdit || file.date_modified.toJSON().slice(0, 16) > file.lastEdit)

   file.skip  = ! file.newEdit && ! (file.newFile && opts.includeNew)

   console.log(JSON.stringify(['file', file], null, ' '))

   if (file.skip) return

   //This makes last_watched logic work
   next.setName(lastEdit[0]+' Modified:'+new Date().toJSON()) //This changes the modified date

   if (next.getMimeType() != MimeType.GOOGLE_DOCS) return

   //getBody does not have headers or footers
   try {
     var doc = DocumentApp.openById(next.getId())
   } catch (e) {
     //In Trash or Permission Issue
     console.warn('watchFiles: PERMISSION ERROR FileId: %s', next.getId())
     return;
   }

   var documentElement = doc.getBody().getParent()
   var numChildren = documentElement.getNumChildren()

   for (var i = 0; i<numChildren; i++) {
     var child = documentElement.getChild(i)
     file['part'+i] = child.getText()
   }

   return file
 }
