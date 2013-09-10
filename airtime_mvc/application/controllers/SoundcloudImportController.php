<?php

class SoundcloudImportController extends Zend_Controller_Action
{
    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('do-import', 'json')
                    ->addActionContext('delete', 'json')
                    ->addActionContext('update-tags', 'json')
                    ->initContext();

    }

    public function indexAction()
    {
        /* **
         * Forward the default view
         * **/
        $this->_forward('list');
    }

    public function listAction()
    {

        /* **
         * Output list of soundcloud files 
         * **/
        global $CC_CONFIG;

        $request = $this->getRequest();
        $baseUrl = $request->getBaseUrl();

        $this->view->headScript()->appendFile($baseUrl.'/js/blockui/jquery.blockUI.js?'.$CC_CONFIG['airtime_version'], 'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'/js/contextmenu/jquery.contextMenu.js?'.$CC_CONFIG['airtime_version'], 'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'/js/datatables/js/jquery.dataTables.js?'.$CC_CONFIG['airtime_version'], 'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'/js/datatables/plugin/dataTables.pluginAPI.js?'.$CC_CONFIG['airtime_version'], 'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'/js/datatables/plugin/dataTables.fnSetFilteringDelay.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'/js/datatables/plugin/dataTables.ColVis.js?'.$CC_CONFIG['airtime_version'], 'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'/js/datatables/plugin/dataTables.ColReorder.js?'.$CC_CONFIG['airtime_version'], 'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'/js/datatables/plugin/dataTables.FixedColumns.js?'.$CC_CONFIG['airtime_version'], 'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'/js/datatables/plugin/dataTables.columnFilter.js?'.$CC_CONFIG['airtime_version'], 'text/javascript');

        $this->view->headScript()->appendFile($baseUrl.'/js/airtime/buttons/buttons.js?'.$CC_CONFIG['airtime_version'], 'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'/js/airtime/utilities/utilities.js?'.$CC_CONFIG['airtime_version'], 'text/javascript');
        //$this->view->headScript()->appendFile($baseUrl.'/js/airtime/library/library.js?'.$CC_CONFIG['airtime_version'], 'text/javascript');
        //$this->view->headScript()->appendFile($baseUrl.'/js/airtime/library/events/library_playlistbuilder.js?'.$CC_CONFIG['airtime_version'], 'text/javascript');
        //TODO can the custom JS in soundcloud.js be moved into Library.js???
        $this->view->headScript()->appendFile($baseUrl.'/js/airtime/soundcloud/soundcloud.js?'.$CC_CONFIG['airtime_version'], 'text/javascript');

        //$this->view->headLink()->appendStylesheet($baseUrl.'/css/media_library.css?'.$CC_CONFIG['airtime_version']);
        $this->view->headLink()->appendStylesheet($baseUrl.'/css/jquery.contextMenu.css?'.$CC_CONFIG['airtime_version']);
        $this->view->headLink()->appendStylesheet($baseUrl.'/css/datatables/css/ColVis.css?'.$CC_CONFIG['airtime_version']);
        $this->view->headLink()->appendStylesheet($baseUrl.'/css/datatables/css/ColReorder.css?'.$CC_CONFIG['airtime_version']);

        //$this->view->headScript()->appendFile($baseUrl.'/js/airtime/library/spl.js?'.$CC_CONFIG['airtime_version'], 'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'/js/airtime/playlist/smart_blockbuilder.js?'.$CC_CONFIG['airtime_version'], 'text/javascript');
        $this->view->headLink()->appendStylesheet($baseUrl.'/css/playlist_builder.css?'.$CC_CONFIG['airtime_version']);

        $soundcloud = new Application_Service_Soundcloud();
        $user = $soundcloud->getUser();
        $groups = $soundcloud->getGroups();
        $tracks = $soundcloud->getTracks();
        $playlists = $soundcloud->getSets();
        $existing_tracks = $soundcloud->getImportedFileIds();

        //remove previously imported tracks
        foreach ($tracks as $index => $track) {
            if(in_array($track['id'], $existing_tracks)){
                unset($tracks[$index]);
            }
        }
        foreach ($groups as $i => $group) {
            if(isset($group['tracks'])){
                foreach($group['tracks'] as $index => $track){
                    if(in_array($track['id'], $existing_tracks)){
                        unset($groups[$i]['tracks'][$index]);
                    }
                }
            }
        }

        //add to view
        $this->view->client_id = $CC_CONFIG['soundcloud-client-id'];
        $this->view->user = $user;
        $this->view->groups = $groups;
        $this->view->playlists = $playlists;
        $this->view->tracks = $tracks;

    }


    public function doImportAction()
    {
        /* **
         * Get SoundCloud ID from a form and import the remote file 
         * **/
        $request = $this->getRequest();
        if($request->isXmlHttpRequest()) {
            $this->_helper->layout()->disableLayout();

            $id = $this->_request->getPost("id");
            if(is_numeric($id)){
                $soundcloud = new Application_Service_Soundcloud();
                $response = $soundcloud->importTrack($id);

                $this->view->response = $response;
                
            } else {
                throw new Exception("Error. Invalid SoundCloud ID.");
            }

        }else{
          throw new Exception("Error. This is not an Ajax request.");
        }
    }

    public function deleteAction()
    {
        /* **
         * Get SoundCloud ID from a form and delete the remote file 
         * **/
        $request = $this->getRequest();
        if($request->isXmlHttpRequest()) {
            $this->_helper->layout()->disableLayout();

            $id = $this->_request->getPost("id");
            if(is_numeric($id)){
                $soundcloud = new Application_Service_Soundcloud();
                $response = $soundcloud->deleteTrack($id);

                $this->view->response = $response;
                
            } else {
                throw new Exception("Error. Invalid SoundCloud ID.");
            }

        }else{
          throw new Exception("Error. This is not an Ajax request.");
        }
    }

    public function updateTagsAction()
    {
        /* **
         * Update the tags list on Soundcloud with current Airtime tags
         * 
         * **/
        $request = $this->getRequest();
        if($request->isXmlHttpRequest()) {
            $this->_helper->layout()->disableLayout();

            $sc_id = $this->_getParam('scid');
            if(is_numeric($sc_id)){
                $soundcloud = new Application_Service_Soundcloud();
                $response = $soundcloud->updateSCTags($sc_id);

                $this->view->response = $response;
                
            } else {
                throw new Exception("Error. Invalid SoundCloud ID.");
            }

        }else{
          throw new Exception("Error. This is not an Ajax request.");
        }
    }



}