<?php
require_once 'customvalidators/ConditionalNotEmpty.php';

class Application_Form_DropboxPreferences extends Zend_Form_SubForm
{

    public $authurl;

      public function __construct()
      {
        //if the DropBox access token isn't set, output the authorization link
        //$storedToken = Application_Model_Preference::GetDropboxAccessToken();
        //if($storedToken == "" || is_null($storedToken)){
            $dbxService = new Application_Service_Dropbox();
            $this->authurl = $dbxService->getAuthorizationURL();
        //}
        
        $this->init();
      }

    public function init()
    {
        $this->setDecorators(array(
            array('ViewScript', array('viewScript' => 'form/preferences_dropbox.phtml'))
        ));

        //enable Dropbox import
        $this->addElement('checkbox', 'EnableDropbox', array(
            'label'      => _('Enable Dropbox Imports'),
            'required'   => false,
            'value' => Application_Model_Preference::GetEnableDropbox(),
            'decorators' => array(
                'ViewHelper'
            )
        ));

        //Dropbox Authorization Code
        $this->addElement('text', 'DropboxAuthCode', array(
            'class'      => 'input_text',
            'label'      => _('Dropbox Authorization Code'),
            'filters'    => array('StringTrim'),
            'autocomplete' => 'off',
            'value' => Application_Model_Preference::GetDropboxAuthCode(),
            'decorators' => array(
                'ViewHelper'
            ),
        ));

        //Dropbox Access Token
        $this->addElement('hidden', 'DropboxAccessToken', array(
            'filters'    => array('StringTrim'),
            'autocomplete' => 'off',
            'value' => Application_Model_Preference::GetDropboxAccessToken(),
        ));

    }

}
