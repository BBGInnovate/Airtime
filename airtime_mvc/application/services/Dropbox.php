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
    private $_clientid = "Airtime";

    public function __construct()
    {
        $CC_CONFIG = Config::getConfig();
        $this->_appInfo = new dbx\AppInfo($CC_CONFIG['dropbox']['key'], $CC_CONFIG['dropbox']['secret']);

        $this->_enabled = Application_Model_Preference::GetEnableDropbox();
        $this->_authcode = Application_Model_Preference::GetDropboxAuthCode();
        $this->_accesstoken = Application_Model_Preference::GetDropboxAccessToken();
        //Zend_Debug::dump($this->_accesstoken);
        //exit;
        
    }

    public function getAuthorizationURL()
    {
        $this->_webAuth = new dbx\WebAuthNoRedirect($this->_appInfo, $this->_clientid);
        return $this->_webAuth->start();
    }

    public function createAccessToken($authcode)
    {
        if($this->_authcode && $this->_appInfo){
            $this->_webAuth = new dbx\WebAuthNoRedirect($this->_appInfo, $this->_clientid);
            list($this->_accesstoken, $this->_userid) = $this->_webAuth->finish($this->_authcode);
            return $this->_accesstoken;
        }
    }

    public function validToken()
    {
        //TODO: make a simple call to dbx core api, see if successful or invalid
        return FALSE;
    }

    public function createClient()
    {
        if($this->_accesstoken != ""){
            $this->_dbxClient = new dbx\Client($this->_accesstoken, $this->_clientid);
            return $this->_dbxClient;         
        }
        return FALSE;
    }

    public function getMetadata($path)
    {
        $md = $this->_dbxClient->getMetadataWithChildren($path);
        return $md;
    }

    public function isEnabled()
    {
        return $this->_enabled ? $this->_enabled : 0;
    }

    public function getAccessToken()
    {
        return $this->_accesstoken;
    }

    public function getAuthCode()
    {
        return $this->_authcode;
    }

    public function getUserId()
    {
        return $this->_userid;
    }

    public function setAccessToken($token)
    {
        $this->_accesstoken = $token;
    }

    public function setAuthCode($code)
    {
        $this->_authcode = $code;
    }

    public function setUserId($id)
    {
        $this->_userid = $id;
    }

}