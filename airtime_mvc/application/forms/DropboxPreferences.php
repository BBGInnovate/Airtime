<?php
require_once 'customvalidators/ConditionalNotEmpty.php';

class Application_Form_DropboxPreferences extends Zend_Form_SubForm
{

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

    }

}
