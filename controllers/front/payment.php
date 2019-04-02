<?php

/**
 * Created by IntelliJ IDEA.
 * User: michaelmartins
 * Date: 2019-03-14
 * Time: 10:56
 */
class CitypayPaylinkPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {
        // Call parent init content method
        parent::initContent();

//        $cart = $this->context->cart;
//
//        $this->context->smarty->assign(array(
//            'nbProducts' => $cart->nbProducts(),
//            'cust_currency' => $cart->id_currency,
//            'currencies' => $this->module->getCurrency((int)$cart->id_currency),
//            'total' => $cart->getOrderTotal(true, Cart::BOTH),
//            'this_path' => $this->module->getPathUri(),
//            'this_path_bw' => $this->module->getPathUri(),
//            'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/'
//        ));
    }

    public function postProcess()
    {

        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'citypaypaylink') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'payment'));
        }

        $ch = curl_init();

        $cart_id = $this->context->cart->id;
        $module_id = $this->module->id;
        $secure_key = Context::getContext()->customer->secure_key;

        $this->module->validateOrder($cart_id, Configuration::get('PS_OS_CITYPAY_INIT'), $this->context->cart->getOrderTotal(true), $this->module->displayName, NULL, NULL, 1, false, $secure_key);

        $order = new Order (Order::getOrderByCartId((int)$cart_id));

        $order_id = $this->module->currentOrder;

        $test = true;
        global $cookie;
        $firstName = $this->context->customer->firstname;
        $lastName = $this->context->customer->lastname;
        if(!$cookie->isLogged()){
            $firstName = $cookie->customer_firstname;
            $lastName = $cookie->customer_lastname;
        }

        if (Configuration::get('TEST_MODE')==2){
            $test = false;
        }

        $postData = array(
            'merchantId' => Configuration::get('MERCHANT_ID'),
            'licenceKey' => Configuration::get('LICENCE_KEY'),
            'email' => Configuration::get('MERCHANT_EMAIL'),
            'identifier' => $order->getUniqReference(),
            'amount' => number_format($this->context->cart->getOrderTotal(true), 2, '', ''),
            'currency' => $this->module->getCurrency($cart->id_currency),
            'test' => $test,
            'cardholder' => array(
                'firstName' => $firstName,
                'lastName' => $lastName,
            ),
            'config' => array(
                'redirect_success' => Context::getContext()->shop->getBaseURL(true).'index.php?controller=order-detail&id_order='.$order_id,
                'redirect_failure' => Context::getContext()->shop->getBaseURL(true),
                'postback' => Context::getContext()->shop->getBaseURL(true).'?fc=module&module=citypaypaylink&controller=postback',
                'postback_policy' => 'sync',
                'return_params' => true,
                'customParams' => array(
                    0 => array (
                            'name' => 'securekey',
                            'value' => $secure_key,
                            'locked' => true,
                        ),
                    1 => array (
                            'name' => 'cartid',
                            'value' => $cart_id,
                            'locked' => true,
                        ),
                    2 => array (
                        'name' => 'ordertotal',
                        'value' => $this->context->cart->getOrderTotal(true),
                        'locked' => true,
                    ),
                    3 => array (
                        'name' => 'displayname',
                        'value' => $this->module->displayName,
                        'locked' => true,
                    ),
                    4 => array (
                        'name' => 'orderid',
                        'value' => (int)Order::getIdByCartId((int)$cart_id),
                        'locked' => true,
                    ),
                ),
            )

        );

        curl_setopt($ch, CURLOPT_URL, "https://secure.citypay.com/paylink3/create");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);


        $result = curl_exec($ch);

        curl_close($ch);
        $data = json_decode($result, true);

        print_r($data);

        if ($data['result']==1){
            $this->context->smarty->assign([
                'params' => $_REQUEST,
                'tokenUrl' => $data['url'],
            ]);
        }


//        $this->setTemplate('payment_return.tpl');
//        $this->setTemplate('module:citypaypaylink/views/templates/front/return.tpl');
//        $this->module->validateOrder($cart->id, Configuration::get('PS_OS_BANKWIRE'), $data['amount'], $this->module->displayName, NULL, NULL, $data['currency'], false, $secure_key);
        Tools::redirect($data['url']);

//        if ($data['authorised'] == true ){
//            $this->module->validateOrder($cart->id, Configuration::get('PS_OS_BANKWIRE'), $data['amount'], $this->module->displayName, NULL, NULL, $data['currency'], false, $secure_key);
////            Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$secure_key);
//        }

//        $cart_id = $this->context->cart->id;
//        $module_id = $this->module->id;
//        $order_id = Order::getOrderByCartId((int)$cart_id);
//        $secure_key = Context::getContext()->customer->secure_key;

//        echo $cart_id;

//        Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart_id.'&id_module='.$module_id.'&id_order='.$order_id.'&key='.$secure_key);
    }
}

