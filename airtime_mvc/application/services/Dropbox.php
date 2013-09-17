<?php
/**
* Application_Service_Dropbox
* Interact with Dropbox.com, requires Dropbox SDK 
* @category Services
*/

require_once 'dropbox-sdk/Dropbox/autoload.php';
use \Dropbox as dbx;

class Application_Service_Dropbox
{
    private $_dbxClient;

    private $_enabled;
    private $_authcode;
    private $_accesstoken;
    private $_clientIdentifier = "airtime";
    private $_appInfo;

    public function __construct()
    {
        $CC_CONFIG = Config::getConfig();
        $this->_appInfo = new dbx\AppInfo($CC_CONFIG['dropbox']['key'], $CC_CONFIG['dropbox']['secret']);

        $this->_enabled = Application_Model_Preference::GetEnableDropbox();
        $this->_authcode = Application_Model_Preference::GetDropboxAuthCode();

        $this->_dbxClient = new dbx\Client($this->_accessToken, $this->_clientIdentifier); 


    }

    public function getAuthorizationURL()
    {
        //$webAuth = new dbx\WebAuthNoRedirect($this->_appInfo, $this->_clientIdentifier);
        //return $webAuth->start();
        return "this is url from the service";
    }

}