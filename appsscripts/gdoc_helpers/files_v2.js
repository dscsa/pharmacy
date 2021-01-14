/*
    This contains the v2 versions of file tasks.  These methods use specific
    parameters not generic objects
 */

/**
 * Remove a specific file by file ID
 *
 * @param  {string} fileId The fileId we are trying to delete.
 *
 * @return {object}     The results of the call that will be
 *      returned to the requestor
 */
function removeFile_v2(fileId) {
    var response = {
        "results": "success"
    };

    try {
        var file = DriveApp.getFileById(fileId);
    } catch (e) {
        response = {
            "results": "error",
            "message": "File not found.",
            "fileId": fileId,
            "error": e.message
        };
    }

    if (file) {
        try {
            file.setTrashed(true);
        } catch (e) {
            response = {
                "results": "error",
                "message": "Could not remove file",
                "name": file.getName(),
                "owner": file.getOwner(),
                "error": e.message
            };
        }
    }

    if (response.results == "success") {
        console.log("v2/removeFile: ", response);
    } else {
        console.error("v2/removeFile: ", response);
    }

    return response;

}

/**
 * Remove a specific file by file ID
 *
 * @param  {string} fileId The fileId we are trying to delete.
 *
 * @return {object}     The results of the call that will be
 *      returned to the requestor
 */
function moveFile_v2(fileId, toFolderId) {
    var response = {
        "results": "success"
    };

    try {
        var toFolder = DriveApp.getFolderById(toFolderId);
        var file = DriveApp.getFileById(fileId);
    } catch (e) {
        response = {
            "results": "error",
            "message": "File or Folder not found not found.",
            "fileId": fileId,
            "folderId": toFolderId,
            "error": e.message
        };
    }

    if (file && folder) {
        try {
            file.moveTo(toFolder)
        } catch (e) {
            response = {
                "results": "error",
                "message": "Could not move file",
                "fileName": file.getName(),
                "fileOwner": file.getOwner().getEmail(),
                "folderName": toFolder.getName(),
                "folderOwner": toFolder.getOwner().getEmail()
            };
        }
    }

    if (response.results == "success") {
        console.log("v2/moveFile: ", response);
    } else {
        console.error("v2/moveFile: ", response);
    }

    return response;
}

/**
 * mark a file as publised so it can't be edited again
 *
 * @param  {string} fileId The ID of the file to publish
 *
 * @return {object}     The results of the call that will be
 *      returned to the requestor
 */
function publishFile_v2(fileId) {
    var response = {
        "results": "success"
    };

    try {
        var file = DriveApp.getFileById(fileId);
    } catch (e) {
        response = {
            "results": "error",
            "message": "File not found.",
            "fileId": fileId "error": e.message,
        };
    }

    if (file) {
        try {
            var file = DriveApp.getFileById(fileId);
            // Side effect of this is that this account can no longer delete/trash/remove
            // this file since must be done by owner
            if (file.getOwner().getEmail() != 'webform@goodpill.org') {
                console.log({
                    "message": 'publishFile WRONG OWNER',
                    "file": file.getName(),
                    "Effective User": Session.getEffectiveUser().getEmail(),
                    "File Owner": file.getOwner().getEmail()
                });

                //support@goodpill.org can only publish files that require sirum sign in
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
        } catch (e) {
            response = {
                "results": "error",
                "message": "Could Not publish file",
                "fileId": fileId,
                "fileName": file.getName(),
                "fileOwner": file.getOwner().getEmail(),
                "error": e.message
            };
            console.error(response);
            return response;

        }
    }

    if (response.results == "success") {
        console.log("v2/publishFile: ", response);
    } else {
        console.error("v2/publishFile: ", response);
    }

    return response;
}

/**
 * Get the details about a file to confirm it's set properly
 * @param  {string} fileId the doc id for a file
 * @return {object}        the details for the file
 */
function fileDetails(fileId) {
    try {
        var file = DriveApp.getFileById(fileId);
        var parent = file.getParents().next();
        return {
            name: file.getName(),
            id: file.getId(),
            trashed: file.isTrashed(),
            parent: {
                name: parent.getName(),
                id: parent.getId()
            }
        };
    } catch (e) {
        response = {
            "results": "error",
            "message": "File not found.",
            "fileId": fileId "error": e.message,
        };

        console.log(response);
        return response;
    }
}
