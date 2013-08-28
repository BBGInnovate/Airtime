<?php
require_once 'customvalidators/ConditionalNotEmpty.php';

class Application_Form_UsimDirectPreferences extends Zend_Form_SubForm
{

    public function init()
    {
        $this->setDecorators(array(
            array('ViewScript', array('viewScript' => 'form/preferences_usimdirect.phtml'))
        ));

        //enable soundcloud uploads
        $this->addElement('checkbox', 'EnableUSIMDirect', array(
            'label'      => _('Enable USIM Direct Imports'),
            'required'   => false,
            'value' => Application_Model_Preference::GetEnableUSIMDirect(),
            'decorators' => array(
                'ViewHelper'
            )
        ));

        $select = new Zend_Form_Element_Select('USIMDirectLanguage');
        $select->setLabel(_('Content Language:'));
        $select->setAttrib('class', 'input_select');
        $select->setMultiOptions(array(
                "" => "",
                "en" => _("English"),
                "fr" => _("French"),
                "ht" => _("Creole"),
                "ha" => _("Hausa"),
                "id" => _("Indonesian"),
                "ku" => _("Kurdish"),
                "pt" => _("Portuguese"),
                "es" => _("Spanish"),
                "ru" => _("Russian"),
                "sw" => _("Swahili"),
                "tr" => _("Turkish"),
                "uk" => _("Ukrainian"),
                "uz" => _("Uzbek")
            ));
        $select->setRequired(false);
        $select->addValidator(new ConditionalNotEmpty(array('EnableUSIMDirect'=>'1')));
        $select->setValue(Application_Model_Preference::GetUSIMDirectLanguage());
        $select->setDecorators(array('ViewHelper'));
        $this->addElement($select);

    }

}
