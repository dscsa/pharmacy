function test_v2_create() {
    createInvoice_v2('1SFW3_J1f3dVahFddqsz2YZY_G1I5dFDA8nVj6KKVfTg', '1ZSog-fJ7HhWJfXU_rmsJaMCiNb2VlFfG', 'Ben Test');
}

/**
 * Create make a copy of a template and return the doc_id
 * @param  {string} templateId Google Doc_Id of the templates
 * @param  {string} folderId   Google folder_id of the destination folder
 * @param  {string} fileName   Name to use for the file
 * @return {object}            A json object containt details of the results
 */
function createInvoice_v2(templateId, folderId, fileName) {
    try {
        var template    = DriveApp.getFileById(templateId);
        var destination = DriveApp.getFolderById(folderId);
        var newFile     = template.makeCopy(fileName, destination);
        var response = {
            "results": "success",
            "doc_id": newFile.getId()
        };
        console.log(fileName + ' created with id: ' + newFile.getId() + ' in folder: ' + folderId);
    } catch (e) {
        var response = {
            "results": "error",
            "message": "Error Creating the file",
            "templateId": templateId,
            "folderId": folderId,
            "fileName": fileName,
            "error": e.message
        };
    }

    return response;
}

/**
 * Merge the order data into a blank invoice.  We need to look at the data
 * on the document and throw an error if the document has already ben rendered
 * @param  {string} fileId    The Google Doc_Id of the file to render
 * @param  {object} orderData All of the data for the order that will be used to
 *      render the invoice
 * @return {object}           A JSON object that represents teh results of the command
 */
function completeInvoice_v2(fileId, orderData) {
    //try {
        var invoice = DriveApp.getFileById(fileId);
        var invoiceDoc = DocumentApp.openById(fileId)
        var descObj = JSON.parse(invoice.getDescription());

        if (descObj && descObj.rendered) {
            throw 'Already Rendered';
        }

        var order   = flattenOrder(orderData)
        var documentElement = invoiceDoc.getBody().getParent()
        var numChildren = documentElement.getNumChildren()

        for (var i = 0; i<numChildren; i++) {
          interpolate(documentElement.getChild(i), order)
        }

        invoiceDoc.saveAndClose()
        invoice.setDescription(JSON.stringify({'rendered':1}));

        var response = {
            "results": "success"
        };
    // } catch (e) {
    //     var response = {
    //         "results": "error",
    //         "message": "Error Completing File the file",
    //         "fileId": fileId,
    //         "error": e.message
    //     };
    // }

    return response;
}
