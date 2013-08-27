<?php

class UsimDirectImportController extends Zend_Controller_Action
{
    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('import', 'json')
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
         * Output list of USIM Direct files 
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
        $this->view->headScript()->appendFile($baseUrl.'/js/airtime/usimdirect/usimdirect.js?'.$CC_CONFIG['airtime_version'], 'text/javascript');

        $this->view->headLink()->appendStylesheet($baseUrl.'/css/jquery.contextMenu.css?'.$CC_CONFIG['airtime_version']);
        $this->view->headLink()->appendStylesheet($baseUrl.'/css/datatables/css/ColVis.css?'.$CC_CONFIG['airtime_version']);
        $this->view->headLink()->appendStylesheet($baseUrl.'/css/datatables/css/ColReorder.css?'.$CC_CONFIG['airtime_version']);

        $this->view->headScript()->appendFile($baseUrl.'/js/airtime/playlist/smart_blockbuilder.js?'.$CC_CONFIG['airtime_version'], 'text/javascript');
        $this->view->headLink()->appendStylesheet($baseUrl.'/css/playlist_builder.css?'.$CC_CONFIG['airtime_version']);

        $usimdirect = new Application_Service_UsimDirect();
        $tracks = $usimdirect->getTracks();

        //add to view
        $this->view->tracks = $tracks;

    }


    public function importAction()
    {
        /* **
         * Get SoundCloud ID from a form and import the remote file 
         * **/
        $request = $this->getRequest();
        if($request->isXmlHttpRequest()) {
            $this->_helper->layout()->disableLayout();

            $id = $this->_request->getPost("id");
            if(is_numeric($id)){
                $usimdirect = new Application_Service_UsimDirect();
                $response = $usimdirect->importTrack($id);

                $this->view->response = $response;
                
            } else {
                throw new Exception("Error. Invalid ID.");
            }

        }else{
          throw new Exception("Error. This is not an Ajax request.");
        }
    }




}