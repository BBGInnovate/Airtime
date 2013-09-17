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
    private $_appInfo;
    private $_webAuth;
    private $_dbxClient;

    private $_enabled;
    private $_authcode;
    private $_accesstoken;
    private $_userid;
    private $_clientIdentifier = "Airtime";

    public function __construct()
    {
        $CC_CONFIG = Config::getConfig();
        $this->_appInfo = new dbx\AppInfo($CC_CONFIG['dropbox']['key'], $CC_CONFIG['dropbox']['secret']);

        $this->_enabled = Application_Model_Preference::GetEnableDropbox();
        $this->_authcode = Application_Model_Preference::GetDropboxAuthCode();
        $this->_accesstoken = Application_Model_Preference::GetDropboxAccessToken();
        $this->_webAuth = new dbx\WebAuthNoRedirect($this->_appInfo, $this->_clientIdentifier);

        if($this->_accesstoken){
            $this->_dbxClient = new dbx\Client($this->_accessToken, $this->_clientIdentifier);         
        }
        
    }

    public function getAuthorizationURL()
    {
        return $this->_webAuth->start();
    }

    public function createAccessToken()
    {
        if($this->_authcode){
            list($this->_accesstoken, $this->_userid) = $this->_webAuth->finish($this->_authcode);
            return $this->_accesstoken;
        }
    }

    public function getAccessToken()
    {
        return $this->_accesstoken;
    }
    

}