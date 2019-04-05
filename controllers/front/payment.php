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
                'redirect_failure' => Context::getContext()->shop->getBaseURL(true).'index.php?controller=order-detail&id_order='.$order_id,
                'postback' => Context::getContext()->shop->getBaseURL(true).'?fc=module&module=citypaypaylink&controller=postback',
                'postback_policy' => 'sync',
                'return_params' => true,
                'passThroughData' => array(
                    'securekey' => $secure_key,
                    'cartid' => $cart_id,
                    'ordertotal' => $this->context->cart->getOrderTotal(true),
                    'displayname' => $this->module->displayName,
                    'orderid' => (int)Order::getIdByCartId((int)$cart_id)
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

//        print_r($data);

        // check token request
        if ($data['result']==1){
            $this->context->smarty->assign([
                'params' => $_REQUEST,
                'tokenUrl' => $data['url'],
            ]);
            //redirect user to paylink payment form
            Tools::redirect($data['url']);
        } else {
            $this->context->smarty->assign([
                'errors' => $data['errors'],
            ]);
            $this->setTemplate('module:citypaypaylink/views/templates/front/payment_req_fail.tpl');
            $order->setCurrentState(Configuration::get('PS_OS_CITYPAY_FAILED'));
            $order->save();
        }


    }
}


