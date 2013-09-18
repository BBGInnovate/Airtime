$(document).ready(function(){
    var loadingMsg = $('#import_status');

    //import button click handler
    $('#dropbox-import button#sb-trash').click(function(e){
        e.preventDefault();
        //disable the delete button
        /*
        var deleteButton = $(this);
        deleteButton.attr("disabled", "disabled");

        if (confirm('Are you sure you want to delete this file from your SoundCloud Account? \n This action cannot be undone.')) {
          loadingMsg.children(":first").html("Deleting Track from SoundCloud...");
          loadingMsg.show();
          hideImportMessage();

          //get the id
          sc_id = getSoundcloudIds();
          console.log('deleting ' + sc_id);

          //execute the delete script
          var request = $.ajax({
            url: "/soundcloud-import/delete",
            type: "post",
            data: {id : sc_id, format : 'json'},
            dataType: "json"
          });

          request.done(function(msg) {
            setImportMessage("File deleted from SoundCloud","alert-success");
            deleteButton.removeAttr("disabled");
            loadingMsg.hide();
            console.log(msg);
            removeTrackRow(sc_id);
          });

          request.fail(function(jqXHR, msg) {
            deleteButton.removeAttr("disabled");
            setImportMessage("Error unable to delte the file from SoundCloud","alert-error");
            loadingMsg.hide();
            console.log(msg);
          });
      } else {
        importButton.removeAttr("disabled");
      }
      */

    });

    //import button click handler
    $('#dropbox-import button#sclibrary-plus').click(function(e){
        e.preventDefault();
        //disable the import button
        var importButton = $(this);
        importButton.attr("disabled", "disabled");
        loadingMsg.children(":first").html("File import in progress...");
        loadingMsg.show();
        hideImportMessage();

        //get the id
        dropbox_paths = getDropboxPaths();
        console.log('importing ' + dropbox_paths);

        //execute the upload script
        var request = $.ajax({
          url: "/dropbox-import/do-import",
          type: "post",
          data: {path : dropbox_paths, format : 'json'}, //TODO allow import of multiple ids?
          dataType: "json"
        });

        request.done(function(data) {
          var messageInfo = "";
          if(data.response.revision){
            var sucessMessage = "Imported file: " + data.response.path;
            setImportMessage(sucessMessage, "alert-success");
            //TODO: update the file next to icon, or disable, or somehow show that file has been uploaded
          } else {
            errorMessage = "Import failed";
            setImportMessage(errorMessage, "alert-error");
          }
          importButton.removeAttr("disabled");
          loadingMsg.hide();
          console.log(data);
        });

        request.fail(function(jqXHR, data) {
          importButton.removeAttr("disabled");
          loadingMsg.hide();
          setImportMessage("Ajax Error: unable to import from Dropbox","alert-error");
          console.log(data);
        });

    });

  function setImportMessage(msgText, type){
    //given the text "message" to output, and the alert "type" class (alert-success|alert-error|alert-info|alert-block)
    var span = $('#alert-box span.plupload_upload_status');
    span.html(msgText);
    //show alert box
    span.parent().addClass(type).show();
  }

  function hideImportMessage(){
    $('#alert-box').hide().attr('class', 'alert');
    $('#alert-box span.plupload_upload_status').html();
  }

  function getDropboxPaths() {
    //return a comma seperated list of soundcloud track ids
     var paths = [];
     $('.directory-browser input[name="dropbox_files"]:checked').each(function() {
       paths.push($(this).val());
     });
     return paths.join(",");
  }

});

