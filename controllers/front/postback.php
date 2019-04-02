<?php

/**
 * Created by IntelliJ IDEA.
 * User: michaelmartins
 * Date: 2019-03-14
 * Time: 10:56
 */

class CitypayPaylinkPostbackModuleFrontController extends ModuleFrontController


{

    public function __construct($response = array())
    {
        parent::__construct($response);
//        echo(Tools::getValue('module'));
    }

    public function initContent()
    {
        parent::initContent();
    }

    public function postProcess()
    {
        $rawData = file_get_contents("php://input");
//        $rawData = "{'sha1':'2opFLgbqZbYo0jPeCDxf/8udDsg=','cardScheme':'Visa Debit','expYear':2022,'authenticationResult':'Y','CSCResponse':' ','digest':'m8a76rqW0SJcRY/kSa9cPQ==','email':'jd@gmail.com','identifier':'SFMZCKLWP','firstname':'johny','ordertotal':'22.94','AVSResponse':' ','cavv':'jIrBSEdMHBzGABEAAAGE/aM/mc4=','postcode':'je123','result':1,'datetime':'2019-03-28T10:38:43.159Z','securekey':'36274a74fef3658a3ea80e23c6f57b8b','errormessage':'Test Transaction','country':'JE','amount':2294,'sha256':'5pv64sCHaywav8bY0OmjgldtfwgTEXtysEBK04hy1ak=','maskedPan':'465901******0005','lastname':'cash','expMonth':7,'displayname':'CityPay Paylink','cac':0,'status':'O','orderid':'65','errorid':'001','currency':'GBP','address':'street','errorcode':'001','mode':'test','authcode':'A12345','cardSchemeId':'VD','eci':'5','title':'','cartid':'68','authorised':'true','cac_id':'','transno':104,'merchantid':64215680}";
        $data = json_decode($rawData, true);
//        var_dump($data);

        $digestValid = false;

        if (isset($data["sha256"])) {
            $digestData = $data["authcode"] . $data["amount"] . $data["errorcode"] . $data["merchantid"] . $data["transno"] . $data["identifier"] . Configuration::get('LICENCE_KEY');

            $digestCompare = base64_encode(hash("sha256", $digestData, true));
            if ($digestCompare == $data["sha256"]) {
                $digestValid = true;
            } else {
                http_response_code(403);
                echo "Digest Mismatch";
                exit();
            }
        }

        if (isset($data["authorised"]) && isset($data["mode"]) && isset($data["errorid"]) && isset($data["orderid"]) && $digestValid) {

            $order = new Order ((int)$data['orderid']);

            if ($data["mode"] === "test") {
                $order->setCurrentState(Configuration::get('PS_OS_CITYPAY_TEST'));
                $order->save();
            } else {
                if ($data["authorised"] === "true") {
                    $order->setCurrentState(Configuration::get('PS_OS_CITYPAY_COMPLETED'));
                    $order->save();
                } else if ($data["errorid"] !== "080") {
                    $order->setCurrentState(Configuration::get('PS_OS_CITYPAY_FAILED'));
                    $order->save();
                } else {
                    $order->setCurrentState(Configuration::get('PS_OS_CITYPAY_CANCELED'));
                    $order->save();
                }
            }
        }

        // exiting because prestashop is expecting a template parameter
        exit();
    }
}
