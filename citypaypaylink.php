<?php
/*
*  @author CityPay <support@citypay.com>
*  @copyright  2019 CityPay Limited
*/

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CitypayPaylink extends PaymentModule
{
    protected $_html = '';
    protected $_postErrors = array();

    public $merchantId;
    public $licenceKey;
    public $testMode;
    public $merchantEmail;

    public function __construct()
    {
        $this->name = 'citypaypaylink';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'CityPay';
        $this->controllers = array('payment');
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $config = Configuration::getMultiple(array('MERCHANT_ID', 'LICENCE_KEY', 'TEST_MODE', 'MERCHANT_EMAIL'));
        if (!empty($config['MERCHANT_ID']))
            $this->merchantId = $config['MERCHANT_ID'];
        if (!empty($config['LICENCE_KEY']))
            $this->licenceKey = $config['LICENCE_KEY'];
        if (!empty($config['TEST_MODE']))
            $this->testMode = $config['TEST_MODE'];
        if (!empty($config['MERCHANT_EMAIL']))
            $this->merchantEmail = $config['MERCHANT_EMAIL'];

        parent::__construct();
        $this->displayName = $this->l('CityPay Paylink');
        $this->description = $this->l('Pay with Paylink, secured by CityPay');

        if (!isset($this->merchantId) || !isset($this->licenceKey) || !isset($this->testMode))
            $this->warning = $this->l('Account details must be configured before using this module.');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    public function install()
    {
        if (!parent::install() || !$this->registerHook('paymentOptions') || !$this->registerHook('paymentReturn')) {
            return false;
        }

        if (!$this->installCitypayOpenOrderState()) {
            return false;
        }
        if (!$this->installCitypayCompletedOrderState()) {
            return false;
        }
        if (!$this->installCitypayCanceledOrderState()) {
            return false;
        }
        if (!$this->installCitypayFailedOrderState()) {
            return false;
        }
        if (!$this->installCitypayTestOrderState()) {
            return false;
        }

        return true;
    }

    public function uninstall()
    {
        if (!Configuration::deleteByName('MERCHANT_ID')
            || !Configuration::deleteByName('LICENCE_KEY')
            || !Configuration::deleteByName('TEST_MODE')
            || !Configuration::deleteByName('MERCHANT_EMAIL')
            || !parent::uninstall())
            return false;
        return true;
    }

    protected function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue('MERCHANT_ID'))
                $this->_postErrors[] = $this->l('Merchant ID is required.');
            elseif (!Tools::getValue('LICENCE_KEY'))
                $this->_postErrors[] = $this->l('Licence Key is required.');
        }
    }

    protected function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('MERCHANT_ID', Tools::getValue('MERCHANT_ID'));
            Configuration::updateValue('LICENCE_KEY', Tools::getValue('LICENCE_KEY'));
            Configuration::updateValue('TEST_MODE', Tools::getValue('TEST_MODE'));
            Configuration::updateValue('MERCHANT_EMAIL', Tools::getValue('MERCHANT_EMAIL'));
        }
        $this->_html .= $this->displayConfirmation($this->l('Settings updated'));
    }

    public function getContent()
    {

        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        }
        $this->_html .= '<br />';
        return $this->_html.$this->displayForm();

    }

    public function displayForm()
    {
        $options = array(
            array(
                'id_option' => 1,
                'name' => 'Test'
            ),
            array(
                'id_option' => 2,
                'name' => 'Live'
            )
        );

        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('CityPay Account Details'),
                    'icon' => 'icon-envelope'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Merchant Id'),
                        'name' => 'MERCHANT_ID',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Licence Key'),
                        'name' => 'LICENCE_KEY',
                        'required' => true
                    ),
                    array(
                        'type' => 'select',
                        'options' => array(
                            'query' => $options,
                            'id' => 'id_option',
                            'name' => 'name'

                        ),
                        'label' => $this->l('Mode'),
                        'name' => 'TEST_MODE',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Merchant Email'),
                        'name' => 'MERCHANT_EMAIL',
                        'required' => false,
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                )
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $this->fields_form = array();
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'MERCHANT_ID' => Tools::getValue('MERCHANT_ID', Configuration::get('MERCHANT_ID')),
            'LICENCE_KEY' => Tools::getValue('LICENCE_KEY', Configuration::get('LICENCE_KEY')),
            'TEST_MODE' => Tools::getValue('TEST_MODE', Configuration::get('TEST_MODE')),
            'MERCHANT_EMAIL' => Tools::getValue('MERCHANT_EMAIL', Configuration::get('MERCHANT_EMAIL')),
        );
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $activeShopID = (int)Context::getContext()->shop->id;
        $cart = $this->context->cart;
        $currency = Currency::getCurrency($cart->id_currency);

        $payment_options = [
            $this->getPaylinkPaymentOption(),
        ];

        return $payment_options;
    }

    public function hookPaymentReturn($params)
    {
        echo $params;
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getPaylinkPaymentOption()
    {

        $paylinkOption = new PaymentOption();
        $paylinkOption
            ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
            ->setAdditionalInformation($this->context->smarty->fetch('module:citypaypaylink/views/templates/front/payment.tpl'))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/payment.png'));

        return $paylinkOption;
    }

    private function installCitypayOpenOrderState()
    {
        if (Configuration::get('PS_OS_CITYPAY_INIT') < 1) {
            $order_state = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages(false) as $language) {
                $order_state->name[(int)$language['id_lang']] = 'Payment Requested';
            }
            $order_state->invoice = false;
            $order_state->send_email = false;
            $order_state->module_name = $this->name;
            $order_state->color = "RoyalBlue";
            $order_state->unremovable = true;
            $order_state->hidden = false;
            $order_state->logable = false;
            $order_state->delivery = false;
            $order_state->shipped = false;
            $order_state->paid = false;
            $order_state->deleted = false;
            if ($order_state->add()) {
                // We save the order State ID in Configuration database
                Configuration::updateValue("PS_OS_CITYPAY_INIT", $order_state->id);
            } else {
                return false;
            }
        }
        return true;
    }

    private function installCitypayCompletedOrderState()
    {
        if (Configuration::get('PS_OS_CITYPAY_COMPLETED') < 1) {
            $order_state = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages(false) as $language) {
                $order_state->name[(int)$language['id_lang']] = 'Payment Completed';
            }
            $order_state->invoice = true;
            $order_state->send_email = true;
            $order_state->module_name = $this->name;
            $order_state->color = "LimeGreen";
            $order_state->unremovable = true;
            $order_state->hidden = false;
            $order_state->logable = false;
            $order_state->delivery = false;
            $order_state->shipped = false;
            $order_state->paid = true;
            $order_state->deleted = false;
            if ($order_state->add()) {
                // We save the order State ID in Configuration database
                Configuration::updateValue("PS_OS_CITYPAY_COMPLETED", $order_state->id);
            } else {
                return false;
            }
        }
        return true;
    }

    private function installCitypayCanceledOrderState()
    {
        if (Configuration::get('PS_OS_CITYPAY_CANCELED') < 1) {
            $order_state = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages(false) as $language) {
                $order_state->name[(int)$language['id_lang']] = 'Payment Canceled';
            }
            $order_state->invoice = false;
            $order_state->send_email = false;
            $order_state->module_name = $this->name;
            $order_state->color = "OrangeRed";
            $order_state->unremovable = true;
            $order_state->hidden = false;
            $order_state->logable = false;
            $order_state->delivery = false;
            $order_state->shipped = false;
            $order_state->paid = false;
            $order_state->deleted = false;
            if ($order_state->add()) {
                // We save the order State ID in Configuration database
                Configuration::updateValue("PS_OS_CITYPAY_CANCELED", $order_state->id);
            } else {
                return false;
            }
        }
        return true;
    }

    private function installCitypayFailedOrderState()
    {
        if (Configuration::get('PS_OS_CITYPAY_FAILED') < 1) {
            $order_state = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages(false) as $language) {
                $order_state->name[(int)$language['id_lang']] = 'Payment Failed';
            }
            $order_state->invoice = false;
            $order_state->send_email = false;
            $order_state->module_name = $this->name;
            $order_state->color = "Red";
            $order_state->unremovable = true;
            $order_state->hidden = false;
            $order_state->logable = false;
            $order_state->delivery = false;
            $order_state->shipped = false;
            $order_state->paid = false;
            $order_state->deleted = false;
            if ($order_state->add()) {
                // We save the order State ID in Configuration database
                Configuration::updateValue("PS_OS_CITYPAY_FAILED", $order_state->id);
            } else {
                return false;
            }
        }
        return true;
    }

    private function installCitypayTestOrderState()
    {
        if (Configuration::get('PS_OS_CITYPAY_TEST') < 1) {
            $order_state = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages(false) as $language) {
                $order_state->name[(int)$language['id_lang']] = 'CityPay TEST Transaction';
            }
            $order_state->invoice = false;
            $order_state->send_email = false;
            $order_state->module_name = $this->name;
            $order_state->color = "Gold";
            $order_state->unremovable = true;
            $order_state->hidden = false;
            $order_state->logable = false;
            $order_state->delivery = false;
            $order_state->shipped = false;
            $order_state->paid = false;
            $order_state->deleted = false;
            if ($order_state->add()) {
                // We save the order State ID in Configuration database
                Configuration::updateValue("PS_OS_CITYPAY_TEST", $order_state->id);
            } else {
                return false;
            }
        }
        return true;
    }

}
