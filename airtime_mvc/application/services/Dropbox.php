<?php
/**
* Application_Service_Dropbox
* Interact with Dropbox.com, requires Dropbox SDK 
* @category Services
*/

require_once 'dropbox-sdk/Dropbox/autoload.php';

class Application_Service_Dropbox
{
    private $_enabled;
    private $_key;
    private $_secret;
    private $_authcode;
    private $_accesstoken;

    public function __construct()
    {
        $CC_CONFIG = Config::getConfig();
        $this->_key = $CC_CONFIG['dropbox_key'];
        $this->_secret = $CC_CONFIG['dropbox_secret'];
        $this->_enabled = Application_Model_Preference::GetEnableDropbox();
        $this->_authcode = Application_Model_Preference::GetDropboxAuthCode();
    }

}