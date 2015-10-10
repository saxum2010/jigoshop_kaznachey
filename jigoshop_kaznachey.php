<?php
/*
Plugin Name: Kaznachey Jigoshop Gateway
Plugin URI: https://www.kaznachey.ua/
Description: Кредитная карта Visa/MC, Webmoney, Liqpay, Qiwi... (www.kaznachey.ua)
Version: 2.3.1
Author: §aXuM
Author URI: http://www.kaznachey.ua/
*/

add_action( 'plugins_loaded', 'kaznachey_jigoshop_gateway', 159 );
function kaznachey_jigoshop_gateway() {

	if ( !class_exists( 'jigoshop_payment_gateway' ) ) return; // if the Jigoshop payment gateway class is not available, do nothing

	add_filter( 'jigoshop_payment_gateways', 'add_kaznachey');

	function add_kaznachey( $methods ) {
		$methods[] = 'jigoshop_kaznachey';
		return $methods;
	}

	class jigoshop_kaznachey extends jigoshop_payment_gateway {

		public function kaznachey_init() {
			$this->merchantGuid				= Jigoshop_Base::get_options()->get_option('jigoshop_merchantguid');
			$this->merchnatSecretKey		= Jigoshop_Base::get_options()->get_option('jigoshop_merchnatmerchnatsecretkey');
		}
		
		public function __construct() {
	
			parent::__construct();
	
			$this->paymentKaznacheyUrl = "http://payment.kaznachey.net/api/PaymentInterface/";

			$this->id						= 'kaznachey';
			$this->has_fields				= false;
			$this->kaznachey_init();
			$this->enabled					= Jigoshop_Base::get_options()->get_option('jigoshop_kaznachey_enabled');
			$this->title					= Jigoshop_Base::get_options()->get_option('jigoshop_kaznachey_title');

			$this->currency					= Jigoshop_Base::get_options()->get_option('jigoshop_kaznachey_currency');
			$this->language					= Jigoshop_Base::get_options()->get_option('jigoshop_kaznachey_language');

			$merchnatInfo = $this->GetMerchnatInfo();
			if(isset($merchnatInfo["PaySystems"])){
				$box = '<br><br><label for="cc_types">Выберите способ оплаты</label><select name="cc_types" id="cc_types">';
				foreach ($merchnatInfo["PaySystems"] as $paysystem){
					$box .= "<option value='$paysystem[Id]'>$paysystem[PaySystemName]</option>";
				}
				$box .= '</select><br><input type="checkbox" checked="checked" value="1" name="cc_agreed" id="cc_agreed"><label for="cc_agreed"><a href="'.$merchnatInfo['TermToUse'].'" target="_blank">Согласен с условиями использования</a></label>';
				
				$box .= "<script type=\"text/javascript\">
				(function(){ 
				var cc_a = jQuery('#cc_agreed');
					 cc_a.on('click', function(){
						if(cc_a.is(':checked')){	
							jQuery('.custom_gateway').find('.error').text('');
						}else{
							cc_a.next().after('<span class=\"error\">Примите условие!</span>');
						}
					 });
					jQuery('body').on('click', '#place_order', function() {
						 document.cookie='cc_types='+jQuery('#cc_types').val();
					});	
				})(); 
				</script> ";
			}
			$this->description = Jigoshop_Base::get_options()->get_option('jigoshop_kaznachey_description').$box;
			
			add_action('init', array($this, 'check_ipn_response'));
			add_action('receipt_kaznachey', array($this, 'receipt_kaznachey'));
			add_action('thankyou_kaznachey', array(&$this, 'thankyou_page'));

		}
			
		protected function get_default_options() {
			$defaults = array();
			$defaults[] = array( 'name' => __('kaznachey', 'jigoshop'), 'type' => 'title', 'desc' => __('This plugin extends the Jigoshop payment gateways by adding a kaznachey payment solution.', 'jigoshop') );
			$defaults[] = array(
					'name'		=> __('Активировать','jigoshop'),
					'desc' 		=> '',
					'tip' 		=> '',
					'id' 		=> 'jigoshop_kaznachey_enabled',
					'std' 		=> 'yes',
					'type' 		=> 'checkbox',
					'choices'	=> array(
							'no'			=> __('No', 'jigoshop'),
							'yes'			=> __('Yes', 'jigoshop')
					)
			);

			$defaults[] = array(
					'name'		=> __('Название','jigoshop'),
					'desc' 		=> '',
					'tip' 		=> __('Название будет отображено во время оформления заказа','jigoshop'),
					'id' 		=> 'jigoshop_kaznachey_title',
					'std' 		=> __('Кредитная карта Visa/MC, Webmoney, Liqpay, Qiwi... (www.kaznachey.ua)','jigoshop'),
					'type' 		=> 'text'
			);
			
			$defaults[] = array(
					'name'		=> __('Идентификатор мерчанта','jigoshop'),
					'desc' 		=> '',
					'tip' 		=> __('Идентификатор мерчанта. Вы можете найти его в <a href="http://kaznachey.ua" target="_blank">личном кабинете</a>.'),
					'id' 		=> 'jigoshop_merchantguid',
					'std' 		=> '',
					'type' 		=> 'text'
			);

			$defaults[] = array(
					'name'		=> __('Секретный ключ мерчанта','jigoshop'),
					'desc' 		=> '',
					'tip' 		=> __('Секретный ключ мерчанта. Вы можете найти его в <a href="http://kaznachey.ua" target="_blank">личном кабинете</a>.','jigoshop'),
					'id' 		=> 'jigoshop_merchnatmerchnatsecretkey',
					'std' 		=> '',
					'type' 		=> 'text'
			);

			$defaults[] = array(
					'name'		=> __('Описание','jigoshop'),
					'desc' 		=> '',
					'tip' 		=> __('Описание будет отображено во время оформления заказа','jigoshop'),
					'id' 		=> 'jigoshop_kaznachey_description',
					'std' 		=> __("", 'jigoshop'),
					'type' 		=> 'longtext'
			);

			$defaults[] = array(
					'name'		=> __('Валюта','jigoshop'),
					'desc' 		=> '',
					'tip' 		=> __('Валюта (UAH, USD, RUB, EUR)','jigoshop'),
					'id' 		=> 'jigoshop_kaznachey_currency',
					'std' 		=> __('UAH','jigoshop'),
					'type' 		=> 'text'
			);
			
			$defaults[] = array(
					'name'		=> __('Язык панели','jigoshop'),
					'desc' 		=> '',
					'tip' 		=> __('Язык страницы оплаты (RU, EN)'),
					'id' 		=> 'jigoshop_kaznachey_language',
					'std' 		=> __('RU','jigoshop'),
					'type' 		=> 'text'
			);

			return $defaults;
		}
			
		function receipt_kaznachey($order_id){
			$this->kaznachey_init();
			$order = new jigoshop_order($order_id);

			$sum=$qty=0;
			foreach ($order->items as $item) {
				$thumb = wp_get_attachment_image_src( get_post_thumbnail_id($item['id']), 'large' );
				$request['Products'][] = array(
					"ProductId" => $item['id'],
					"ProductName" => $item['name'],
					"ProductPrice" => $item['cost'],
					"ProductItemsNum" => $item['qty'],
					"ImageUrl" => (isset($thumb[0]))?$thumb[0]:'',
				);
				$sum += $item['cost'] * $item['qty'];
				$qty += $item['qty'];
			}
			
			// Доставка
			if ( jigoshop_shipping::is_enabled() ) {
				if ($order->order_shipping>0) {
					$request['Products'][] = array(
						"ProductId" => 1,
						"ProductName" => 'Доставка',
						"ProductPrice" => $order->order_shipping,
						"ProductItemsNum" => 1,
						"ImageUrl" => '',
					);
					$sum +=  $order->order_shipping;
					$qty += 1;
				}
			}
			
			$request["MerchantGuid"] = $this->merchantGuid;
			$request['SelectedPaySystemId'] = $_COOKIE['cc_types'] ? $_COOKIE['cc_types'] : $this->GetMerchnatInfo(false, true);
			$request['Currency'] = $this->currency;
			$request['Language'] = $this->language;
			
			$checkout_redirect = apply_filters( 'jigoshop_get_checkout_redirect_page_id', jigoshop_get_page_id('thanks') );
			
			$request['PaymentDetails'] = array(
				"EMail" => $order->billing_email,
				"PhoneNumber" => $order->billing_phone,
				"MerchantInternalPaymentId" => $order->id,
				"MerchantInternalUserId" => $order->user_id,
				"StatusUrl" => add_query_arg( 'key', $order->order_key, add_query_arg( 'order', $order->id, get_permalink( $checkout_redirect ) ) ),
				"ReturnUrl" => get_permalink( $checkout_redirect ),

				"CustomMerchantInfo" => "",
				"BuyerCountry" => $order->billing_country,
				"BuyerFirstname" => $order->billing_first_name,
				"BuyerPatronymic" => '',
				"BuyerLastname" => $order->billing_last_name,
				"BuyerStreet" => $order->billing_address_1,
				"BuyerZone" => $order->billing_state,
				"BuyerZip" => '',
				"BuyerCity" => $order->billing_city,
				"DeliveryFirstname" => $order->shipping_first_name,
				"DeliveryPatronymic" => '',
				"DeliveryLastname" => $order->shipping_last_name,
				"DeliveryZip" => '',
				"DeliveryCountry" => $order->shipping_country,
				"DeliveryStreet" => $order->shipping_address_1,
				"DeliveryCity" => $order->shipping_city,
				"DeliveryZone" => $order->shipping_state,
				);
		
		   $request["Signature"] = md5(strtoupper($this->merchantGuid) .
				number_format($sum, 2, ".", "") . 
				$request["SelectedPaySystemId"] . 
				$request["PaymentDetails"]["EMail"] . 
				$request["PaymentDetails"]["PhoneNumber"] . 
				$request["PaymentDetails"]["MerchantInternalUserId"] . 
				$request["PaymentDetails"]["MerchantInternalPaymentId"] . 
				strtoupper($request["Language"]) . 
				strtoupper($request["Currency"]) . 
				strtoupper($this->merchnatSecretKey));
				
				$response = $this->sendRequestKaznachey(json_encode($request), "CreatePaymentEx");
				$result = json_decode($response, true);
				
				if($result['ErrorCode'] != 0){
					wp_redirect( home_url() ); exit;
				}
			
				echo(base64_decode($result["ExternalForm"]));
				jigoshop_cart::empty_cart();
				exit();
		}
			
		function process_payment($order_id) {
			$order = new jigoshop_order($order_id);
	
			return array(
				'result' 	=> 'success',
				'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(jigoshop_get_page_id('pay'))))
			);
		}
			
		function payment_fields() {
			if($this->description){
				echo wpautop(wptexturize($this->description));
			}
		}
			
		function check_ipn_response() {
			$this->kaznachey_init();
			$request_json = file_get_contents('php://input');
			$request = json_decode($request_json, true);

			$request_sign = md5($request["ErrorCode"].
				$request["OrderId"].
				$request["MerchantInternalPaymentId"]. 
				$request["MerchantInternalUserId"]. 
				number_format($request["OrderSum"],2,".",""). 
				number_format($request["Sum"],2,".",""). 
				strtoupper($request["Currency"]). 
				$request["CustomMerchantInfo"]. 
				strtoupper($this->merchnatSecretKey));
			
				if($request['SignatureEx'] == $request_sign) {
				   $order = new jigoshop_order($request["MerchantInternalPaymentId"]);
				   if ($order->id) {
						$order->add_order_note( __('Заказ оплачен. Платеж через www.kaznachey.ua', 'jigoshop') );
						$order->payment_complete();
				   }
				}
		}

		function thankyou_page() {
 			if($status = $_GET['Result']){
 				$order_num = ($OrderId = $_GET['OrderId'])?"№$OrderId":'';
				switch ($status) {
					case 'success':
						echo "<h1>Ваш заказ$order_num оплачен</h1>";
						break;
					case 'deferred':
						echo "<h1>Спасибо за Ваш заказ$order_num.</h1><h2>Вы сможете оплатить его после проверки менеджером. Ссылка на оплату будет выслана Вам по электронной почте.</h2>";
						break;
					default:
					   echo '<h1 style="color:red">Произошла ошибка во время оплаты заказа</h1>';
				} 
			} 
		}
		
		function GetMerchnatInfo($id = false, $first = false){
			$this->kaznachey_init();
			$requestMerchantInfo = Array(
				"MerchantGuid"=>$this->merchantGuid,
				"Signature" => md5(strtoupper($this->merchantGuid) . strtoupper($this->merchnatSecretKey))
			);
			
			$resMerchantInfo = json_decode($this->sendRequestKaznachey(json_encode($requestMerchantInfo), 'GetMerchatInformation'),true); 
			if($first){
				return $resMerchantInfo["PaySystems"][0]['Id'];
			}elseif($id)
			{
				foreach ($resMerchantInfo["PaySystems"] as $key=>$paysystem)
				{
					if($paysystem['Id'] == $id){
						return $paysystem;
					}
				}
			}else{
			
				return $resMerchantInfo;
			}
		}
		
		protected function sendRequestKaznachey($jsonData, $method)
		{
			$curl = curl_init();
			if (!$curl)
				return false;

			curl_setopt($curl, CURLOPT_URL, $this->paymentKaznacheyUrl . $method);
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_HTTPHEADER,
				array("Expect: ", "Content-Type: application/json; charset=UTF-8", 'Content-Length: '
					. strlen($jsonData)));
			curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonData);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, True);
			$response = curl_exec($curl);
			curl_close($curl);

			return $response;
		}
		
	}
}
