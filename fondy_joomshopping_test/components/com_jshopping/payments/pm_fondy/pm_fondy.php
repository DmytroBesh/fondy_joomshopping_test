<?php
/*
 * @version      1.0.0
 * @author       DM
 * @package      Jshopping
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die('Restricted access');

class pm_fondy extends PaymentRoot{
    const VERSION = '1.0';
    const ORDER_APPROVED = 'approved';
    const ORDER_DECLINED = 'declined';
    const SIGNATURE_SEPARATOR = '|';
    const ORDER_SEPARATOR = ":";
	const URL = 'https://api.fondy.eu/api/checkout/redirect/';
	
    /**
     * Подключение необходимого языкового файла для модуля
     */
    function loadLanguageFile(){
        $lang = JFactory::getLanguage();
        // определяем текущий язык
        $lang_tag = $lang->getTag();
        // папка с языковыми файлами модуля
        $lang_dir = JPATH_ROOT . '/components/com_jshopping/payments/pm_fondy/lang/';
        // переменная с полным именем языкового файла (с путём)
        $lang_file = $lang_dir . $lang_tag . '.php';
        // пытаемся подключить языковой файл, если такого нет - подключается по-умолчанию (en-GB.php)
        if(file_exists($lang_file))
            require_once $lang_file;
        else
            require_once $lang_dir . 'en-GB.php';
    }

    function showPaymentForm($params, $pmconfigs){
        include(dirname(__FILE__) . "/paymentform.php");
    }

    /**
     * Данный метод отвечает за настройки плагина в админ. части
     * @param $params Параметры настроек плагина
     */
    function showAdminFormParams($params){
        $module_params_array = array(
			'fondy_redirect',
            'fondy_merchant_id',
            'fondy_secret_key',
			'fondy_cur',
            'transaction_end_status',
            'transaction_failed_status'
        );
        foreach($module_params_array as $module_param){
            if(!isset($params[$module_param]))
                $params[$module_param] = '';
        }

        $orders = JModelLegacy::getInstance('orders', 'JshoppingModel');
        $this->loadLanguageFile();
        include dirname(__FILE__) . '/adminparamsform.php';
    }



	function showEndForm($pmconfigs, $order){
        // подгружаем языковой файл для описания возможных ошибок
        $this->loadLanguageFile();
        
        /* далее получаем необходимые поля для инициализации платежа */

        $lang = JFactory::getLanguage()->getTag();
        switch($lang){
            case 'en_EN':
                $lang = 'en';
                break;
            case 'ru_RU':
                $lang = 'ru';
                break;
            default:
                $lang = 'en';
                break;
        }
        $order_id = $order->order_id;
        $description = 'Order :' . $order_id;

        $base_url = JURI::root() . 'index.php?option=com_jshopping&controller=checkout&task=step7&js_paymentclass=' . __CLASS__ . '&order_id=' . $order_id;
        $success_url = $base_url . '&act=finish';
        $fail_url = $base_url . '&act=cancel';
		if($pmconfigs['fondy_cur']!= '')
		{$cur = $pmconfigs['fondy_cur'];}else{$cur = $order->currency_code_iso;}
        $result_url = $base_url . '&act=notify&nolang=1';
		
        ?>
		<?php if ($pmconfigs['fondy_redirect'] == 1) {
			 $fondy_args = array('order_id' => $order_id . self::ORDER_SEPARATOR . time(),
		    'merchant_id' =>  $pmconfigs['fondy_merchant_id'],
            'order_desc' => $description,
            'amount' =>  round($this->fixOrderTotal($order)*100),
            'currency' => $cur,
            'server_callback_url' => $result_url,
            'response_url' => $success_url,
            'lang' => $lang,
            'sender_email' => $order->email);
        $fondy_args['signature'] = $this->getSignature($fondy_args, $pmconfigs['fondy_secret_key']);
		$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, 'https://api.fondy.eu/api/checkout/url/');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('request'=>$fondy_args)));
			$result = json_decode(curl_exec($ch));
			if ($result->response->response_status == 'failure'){
				echo $result->response->error_message;
				exit;
			}
						?>
        <html>
        <head>
            <script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.0/jquery.min.js"></script>
            <script src="https://api.fondy.eu/static_common/v1/checkout/ipsp.js"></script>
            <script src="https://rawgit.com/dimsemenov/Magnific-Popup/master/dist/jquery.magnific-popup.js"></script>
            <link href="https://rawgit.com/dimsemenov/Magnific-Popup/master/dist/magnific-popup.css" type="text/css" rel="stylesheet" media="screen">
            <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css">
        </head>
        <body>
        <style>
            #checkout_wrapper a{
                font-size: 20px;
                top: 30px;
                padding: 20px;
                position: relative;
            }
            #checkout_wrapper {
                text-align: left;
                position: relative;
                background: #FFF;
                /* padding: 30px; */
                padding-left: 15px;
                padding-right: 15px;
                padding-bottom: 30px;
                width: auto;
                max-width: 2000px;
                margin: 9px auto;
				
            }
			#checkout{
			overflow:hidden;	
			}

        </style>
        <div id="checkout">
            <div id="checkout_wrapper"></div>
        </div>
		<?php if ($pmconfigs['fondy_popup'] == 1) { ?>
			<script>
				$(document).ready(function() {
					$.magnificPopup.open({
						showCloseBtn:false,
						items: {
							src: $("#checkout_wrapper"),
							type: "inline"
						},
						callbacks: {
							close: function() { location.href = '<?php echo $fail_url ?>'}
						}
					});
				})
			</script>
		<?php } ?>
        <script>
		var checkoutStyles = {
		<?php echo  $pmconfigs['fondy_style'] ?>
		}
		function checkoutInit(url, val) {
                $ipsp("checkout").scope(function() {
                    this.setCheckoutWrapper("#checkout_wrapper");
                    this.addCallback(__DEFAULTCALLBACK__);
					this.setCssStyle(checkoutStyles);
                    this.width('100%');
                    this.action("show", function(data) {
                        $("#checkout_loader").remove();
                        $("#checkout").show();
                    });
                    this.action("hide", function(data) {
                        $("#checkout").hide();
                    });
                    if(val){
                        this.width(val);
                        this.action("resize", function(data) {
                            $("#checkout_wrapper").width(val).height(data.height);
                        });
                    }else{
                        this.action("resize", function(data) {
                            $("#checkout_wrapper").width(<?php echo ($pmconfigs['fondy_popup'] == 1) ? "480" : '"100%"'; ?>).height(data.height);
                        });
                    }
                    this.loadUrl(url);
                });
            }
            checkoutInit("<?php echo $result->response->checkout_url ?>");
        </script>
        </body>
        </html>
		<?php }else { 
			 $fondy_args = array('order_id' => $order_id . self::ORDER_SEPARATOR . time(),
		    'merchant_id' =>  $pmconfigs['fondy_merchant_id'],
            'order_desc' => $description,
            'amount' =>  round($this->fixOrderTotal($order)*100),
            'currency' => $cur,
            'server_callback_url' => $result_url,
            'response_url' => $success_url,
            'lang' => $lang,
            'sender_email' => $order->email);
        $fondy_args['signature'] = $this->getSignature($fondy_args, $pmconfigs['fondy_secret_key']);
		?>
		  <html>
        <head>
            <meta http-equiv="content-type" content="text/html; charset=utf-8" />            
        </head>
        <body>
        <form id="paymentform" action="<?php print pm_fondy::URL; ?>" name = "paymentform" method = "post">
        <?php
            foreach ($fondy_args as $key => $value) :
        ?>
                <input type="hidden" name="<?php echo $key; ?>" value="<?php echo $value; ?>">
        <?php
            endforeach;
			//die();
        ?>
        </form>        
        <?php print _JSHOP_REDIRECT_TO_PAYMENT_PAGE ?>
        <br>
        <script type="text/javascript">document.getElementById('paymentform').submit();</script>
        </body>
        </html>
        <?php
        die(); } ?>
        <?php

	}
    function checkTransaction($pmconfig, $order, $rescode)
    {
        // подгружаем языковой файл для описания возможных ошибок
        $this->loadLanguageFile();
        // получаем объект, содержащий входные данные (GET и POST), исп. вместо deprecated JRequest::getInt('var')
		$callback = JFactory::$application->input->post->getArray();
       	If (empty($callback)){
            $fap = json_decode(file_get_contents("php://input"));
            foreach($fap as $key=>$val)
            {
                $callback[$key] =  $val ;
            }
		}
        $paymentInfo = $this->isPaymentValid($callback, $pmconfig, $order);
        return $paymentInfo;
    }

    function getSignature($data, $password, $encoded = true)
    {
        $data = array_filter($data, function($var) {
            return $var !== '' && $var !== null;
        });
        ksort($data);

        $str = $password;
        foreach ($data as $k => $v) {
            $str .= self::SIGNATURE_SEPARATOR . $v;
        }

        if ($encoded) {
            return sha1($str);
        } else {
            return $str;
        }
    }

    function isPaymentValid($response, $pmconfig, $order)
    {
        list($orderId,) = explode(self::ORDER_SEPARATOR, $response['order_id']);
        if ($orderId != $order->order_id) {
            return array(0, FONDY_UNKNOWN_ERROR);
        }
		
        if ($pmconfig['fondy_merchant_id'] != $response['merchant_id']) {

            return array(0, FONDY_MERCHANT_DATA_ERROR);
        }

        $responseSignature = $response['signature'];
		if (isset($response['response_signature_string'])){
			unset($response['response_signature_string']);
		}
		if (isset($response['signature'])){
			unset($response['signature']);
		}
		
		
        if ($this->getSignature($response, $pmconfig['fondy_secret_key']) != $responseSignature) {
            return array(0, FONDY_SIGNATURE_ERROR);
        }

        if ($response['order_status'] != self::ORDER_APPROVED) {
            return array(0, FONDY_ORDER_DECLINED);
        }

        if ($response['order_status'] == self::ORDER_APPROVED) {
            JFactory::getApplication()->enqueueMessage( FONDY_ORDER_APPROVED . $_REQUEST['payment_id']);
            return array(1, FONDY_ORDER_APPROVED . $_REQUEST['payment_id']);

        }

    }

    function getUrlParams($fondy_config){
        $params = array();
        $input = JFactory::$application->input;
        $params['order_id'] = $input->getInt('order_id', null);
        $params['hash'] = "";
        $params['checkHash'] = 0;
        $params['checkReturnParams'] = 1;
        return $params;
    }
    
	function fixOrderTotal($order){
        $total = $order->order_total;
        if ($order->currency_code_iso=='HUF'){
            $total = round($total);
        }else{
            $total = number_format($total, 2, '.', '');
        }
    return $total;
    }
}
?>