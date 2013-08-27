$(document).ready(function(){
    var oTable = $('.datatable').dataTable();
    var loadingMsg = $('#import_status');

    //import button click handler
    $('#usimdirect-import button#sclibrary-plus').click(function(e){
        e.preventDefault();
        //disable the import button
        var importButton = $(this);
        importButton.attr("disabled", "disabled");
        loadingMsg.children(":first").html("File import in progress...");
        loadingMsg.show();
        hideImportMessage();

        //get the id
        item_id = getItemIds();
        console.log('importing ' + item_id);

        //execute the upload script
        var request = $.ajax({
          url: "/usim-direct-import/import",
          type: "post",
          data: {id : item_id, format : 'json'},
          dataType: "json"
        });

        request.done(function(data) {
          var messageInfo = "";
          if(data && typeof data.response.message != 'undefined'){
            messageInfo += data.response.message + "  ";
          }
          if(data && typeof data.response.info.message != 'undefined'){
            messageInfo += data.response.info.message + "  ";
          }
          if(data && data.response.success === true){
            var sucessMessage = "Imported file: " + messageInfo;
            setImportMessage(sucessMessage, "alert-success");
            removeTrackRow(item_id);
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
          setImportMessage("Ajax Error: unable to import from USIM Direct","alert-error");
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
    var row = $("#item_" + id);
    row.fadeOut('slow');
    //TODO: remove row with dataTable plugin
    //oTable.fnDeleteRow( oTable.fnGetPosition( row ) );

  }

  function getItemIds() {
    //return a comma seperated list of item ids
     var itemIds = [];
     $('tr.audio input:checked').each(function() {
       itemIds.push($(this).val());
     });
     return itemIds.join(",");
  }

});

