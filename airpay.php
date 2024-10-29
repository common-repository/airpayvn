<?php
/**
 * Plugin Name: Airpay.vn cho Woocommerce
 * Plugin URI: http://airpay.vn
 * Description: Tích hợp Cổng thanh toán Airpay vào Woocommerce
 * Version: 1.0
 * Author: Airpay Group
 * Author URI: http://airpay.vn
 * License: GPL2
 */

add_action('plugins_loaded', 'woocommerce_AIRPAYVN_init', 0);

function woocommerce_AIRPAYVN_init(){
  if(!class_exists('WC_Payment_Gateway')) return;

  class WC_AIRPAYVN extends WC_Payment_Gateway{
    public function __construct(){
      $this -> id = 'AIRPAYVN';
      $this -> medthod_title = 'Airpay (Airpay.vn)';
      $this -> has_fields = false;

      $this -> init_form_fields();
      $this -> init_settings();

      $this -> title = $this -> settings['title'];
      $this -> description = $this -> settings['description'];
      $this -> merchant_id = $this -> settings['merchant_id'];
      $this->rate = $this->settings['rate'];
      //
      $this -> redirect_page_id = $this -> settings['redirect_page_id'];
      $this -> liveurl = 'http://airpay.vn/payment';
      $this->notify_url        = add_query_arg( 'wc-api', 'WC_AIRPAYVN', home_url( '/' ));

      $this -> msg['message'] = "";
      $this -> msg['class'] = "";

      if ( version_compare( WOOCOMMERCE_VERSION, '2.0.8', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
             } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            }
      add_action('woocommerce_receipt_AIRPAYVN', array(&$this, 'receipt_page'));
      add_action('woocommerce_api_wc_airpayvn', array( $this, 'check_ipn_response' ) );
      
   }
    function init_form_fields(){

       $this -> form_fields = array(
                'enabled' => array(
                    'title' => __('Bật / Tắt', 'mAIRPAYVN'),
                    'type' => 'checkbox',
                    'label' => __('Kích hoạt cổng thanh toán Airpay cho Woocommerce', 'mAIRPAYVN'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('Tên:', 'mAIRPAYVN'),
                    'type'=> 'text',
                    'description' => __('Hiển thị phương thức thanh toán ( khi khách hàng chọn phương thức thanh toán )', 'mAIRPAYVN'),
                    'default' => __('Cổng thanh toán Airpay', 'mAIRPAYVN')),
                'description' => array(
                    'title' => __('Mô tả:', 'mAIRPAYVN'),
                    'type' => 'textarea',
                    'description' => __('Mô tả phương thức thanh toán.', 'mAIRPAYVN'),
                    'default' => __('Cổng thanh toán trực tuyến an toàn và đơn giản, nhanh chóng.', 'mAIRPAYVN')),
                'merchant_id' => array(
                    'title' => __('Tài khoản Airpay', 'mAIRPAYVN'),
                    'type' => 'text',
                    'description' => __('Đây là mã Merchant_id tài khoản airpay của bạn.')),
                'rate' => array(
                    'title' => __('Tỷ giá đồng Dollar', 'mAIRPAYVN'),
                    'type' => 'text',
                    'description' => __('Đây là quy đổi tỷ lệ của 1 đồng Dollar ra vnđ.')),
                /**/
                'redirect_page_id' => array(
                    'title' => __('Trang trả về'),
                    'type' => 'select',
                    'options' => $this -> get_pages('Hãy chọn...'),
                    'description' => "Hãy chọn trang/url để chuyển đến sau khi khách hàng đã thanh toán tại Airpay thành công."
                )
            );
    }

       public function admin_options(){
        echo '<h3>'.__('Airpayvn Payment Gateway', 'mAIRPAYVN').'</h3>';
        echo '<p>'.__('Airpay.vn - Thanh toán trực tuyến an toàn, đơn giản và nhanh chóng với Airpay').'</p>';
        echo '<table class="form-table">';
        
        // Generate the HTML For the settings form.
        $this -> generate_settings_html();
        echo '</table>';

    }
    
    /**
	 * Check for Airpay IPN Response
	 *
	 * @access public
	 * @return void
	 */
	function check_ipn_response() {
        global $woocommerce;
		@ob_clean();
        
		$ipn_response = ! empty( $_POST ) ? $_POST : false;

		if ($ipn_response) {
		    // post lai
            foreach ($_POST as $key => $value) {
            	$value = $value;
            	$fields .= "&$key=$value";
            }

            $ch = curl_init("http://airpay.vn/ipn");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $result = curl_exec($ch);
            if($result == "VERIFIED"){
                // thuc thi tai day
                $order_id = intval($_POST['order_id']);
                $order = new WC_Order( $order_id );
                
                if (!isset( $order->id ) ) {
        			if ( 'yes' == $this->debug ) {
        				$this->log->add( 'Airpay', 'Error: Order id not match : ' . $order_id );
        			}
        		}
                
                // cap nhat ve hoan thanh don hang va xoa gio hang
                // Remove cart
    	       $woocommerce->cart->empty_cart();
               $order->payment_complete();
            }
			else {
                // ghi log loi ipn
                if ( 'yes' == $this->debug ) {
    				$this->log->add( 'Airpay', 'Error : ' . $result );
    			}
                wp_die( "Airpay IPN Error : " . $result, "Airpay IPN", array( 'response' => 200 ) );
			}

		} 
        else {
			wp_die( "Airpay IPN Request Invalid!", "Airpay IPN", array( 'response' => 200 ) );
		}

	}

    /**
     *  There are no payment fields for AIRPAYVN, but we want to show the description if set.
     **/
    function payment_fields(){
        if($this -> description) echo wpautop(wptexturize($this -> description));
    }
    /**
     * Receipt Page
     **/
    function receipt_page($order){
        echo '<p>'.__('Đơn hàng đã được xử lý thành công!').'</p>';
        echo $this -> generate_AIRPAYVN_form($order);
    }
    
    /**
     * Generate AIRPAYVN button link
     **/
    public function generate_AIRPAYVN_form($order_id){

       global $woocommerce;
        $order = new WC_Order( $order_id );
        $item_names = array();
        
        if(sizeof($order->get_items()) > 0){
        	foreach($order->get_items() as $item){
        		if($item['qty']){
        			$item_names[] = array('name' => $item['name'], 'des' => '', 'price' =>($item['line_subtotal']/$item['qty']*$this->rate), 'quantity' => $item['qty']);
        		}
        	}
        }       

        $redirect_url = ($this->redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
        return '<form id="vpg_cpayment_form" action="'.$this->liveurl.'" method="post">
            <input type="hidden" name="merchant_id" value="'.$this->merchant_id.'"/>
            <input type="hidden" name="order_id" value="'.$order_id.'"/>
            <input type="hidden" name="arr_products" value="'. base64_encode(serialize($item_names)) .'"/>
            <input type="hidden" name="total_amount" value="'.$order->get_total() * $this->rate.'" />
            <input type="hidden" name="url_success" value="'.$redirect_url.'" />
            <input type="hidden" name="url_cancel" value="'.$order->get_cancel_order_url().'" />
            <input type="hidden" name="url_listener" value="'.$this->notify_url.'" />
            <input type="submit" value="Thanh toán" />
            </form>';
    }
    /**
     * Process the payment and return the result
     **/
    function process_payment( $order_id ) {
        global $woocommerce;
      $order = new WC_Order( $order_id );
      
        return array(
          'result'  => 'success',
          'redirect'  => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay' ))))
        );  
    }


    function showMessage($content){
            return '<div class="box '.$this -> msg['class'].'-box">'.$this -> msg['message'].'</div>'.$content;
        }
     // get all pages
    function get_pages($title = false, $indent = true) {
        $wp_pages = get_pages('sort_column=menu_order');
        $page_list = array();
        if ($title) $page_list[] = $title;
        foreach ($wp_pages as $page) {
            $prefix = '';
            // show indented child pages?
            if ($indent) {
                $has_parent = $page->post_parent;
                while($has_parent) {
                    $prefix .=  ' - ';
                    $next_page = get_page($has_parent);
                    $has_parent = $next_page->post_parent;
                }
            }
            // add to page list array array
            $page_list[$page->ID] = $prefix . $page->post_title;
        }
        return $page_list;
    }
}

    function woocommerce_add_AIRPAYVN_gateway($methods) {
        $methods[] = 'WC_AIRPAYVN';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_AIRPAYVN_gateway' );
}