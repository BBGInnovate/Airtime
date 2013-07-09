$(document).ready(function(){
    var oTable = $('.datatable').dataTable();
    var loadingMsg = $('#import_status');

    //import button click handler
    $('#soundcloud-import button#sb-trash').click(function(e){
        e.preventDefault();
        //disable the delete button
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

    });

    //import button click handler
    $('#soundcloud-import button#sclibrary-plus').click(function(e){
        e.preventDefault();
        //disable the import button
        var importButton = $(this);
        importButton.attr("disabled", "disabled");
        loadingMsg.children(":first").html("File import in progress...");
        loadingMsg.show();
        hideImportMessage();

        //get the id
        sc_id = getSoundcloudIds();
        console.log('importing ' + sc_id);

        //execute the upload script
        var request = $.ajax({
          url: "/soundcloud-import/do-import",
          type: "post",
          data: {id : sc_id, format : 'json'}, //TODO allow import of multiple ids?
          dataType: "json"
        });

        request.done(function(data) {
          var messageInfo = "";
          if(typeof data.response.message != 'undefined'){
            messageInfo += data.response.message + "  ";
          }
          if(typeof data.response.info.message != 'undefined'){
            messageInfo += data.response.info.message + "  ";
          }
          if(data.response.success === true){
            var sucessMessage = "Imported file: " + messageInfo;
            setImportMessage(sucessMessage, "alert-success");
            removeTrackRow(sc_id);
          } else {
            errorMessage = "Import failed: " + messageInfo;
            setImportMessage(errorMessage, "alert-error");
          }
          importButton.removeAttr("disabled");
          loadingMsg.hide();
          console.log(data);
        });

        request.fail(function(jqXHR, data) {
          importButton.removeAttr("disabled");
          loadingMsg.hide();
          setImportMessage("Ajax Error: unable to import from SoundCloud","alert-error");
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

  function removeTrackRow(id){
    //hide the imported row from the user
    var row = $("#sc_" + id);
    row.fadeOut('slow');
    //TODO: remove row with dataTable plugin
    //oTable.fnDeleteRow( oTable.fnGetPosition( row ) );

  }

  function getSoundcloudIds() {
    //return a comma seperated list of soundcloud track ids
     var souncloudIds = [];
     $('.tab-pane.active .sc-audio :checked').each(function() {
       souncloudIds.push($(this).val());
     });
     return souncloudIds.join(",");
  }

});

