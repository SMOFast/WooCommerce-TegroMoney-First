<?php
/*
    Plugin Name: tegro Payment Gateway eCommerce
    Plugin URI: https://tegro.money
    Description: Интернет-эквайринг tegro (прием платежей), интеграция с другими платежными системами.
    Version: 1.0.0
    Tags: WooCommerce, WordPress, Gateways, Payments, Payment, Money, WooCommerce, WordPress, Plugin, Module, Store, Modules, Plugins, Payment system, Website
    Author: tergo.money
    Author URI: https://tegro.money

    Copyright: © 2020 Ros.Media. Modified
	License: GNU General Public License v3.0
	License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

    if (!defined('ABSPATH'))
    {
        exit;
    }

    add_action('plugins_loaded', 'woocommerce_tegro', 0);



    function woocommerce_tegro()
    {
        if (!class_exists('WC_Payment_Gateway'))
        {
            return;
        }

        if (class_exists('WC_tegro'))
        {
            return;
        }

        class WC_tegro extends WC_Payment_Gateway
        {
            public function __construct()
            {
                global $woocommerce;

                $plugin_dir = plugin_dir_url(__FILE__);

                $this->id = 'tegro';
                $this->source = 'WP 0.2.2';
                $this->icon = apply_filters('woocommerce_tegro_icon', $plugin_dir . 'assets/tegro_logo_blue.png');
                $this->has_fields = false;
                $this->init_form_fields();
                $this->init_settings();
                $this->title = $this->get_option('title');
                $this->tegro_url = $this->get_option('tegro_url');
                $this->tegro_shop_id = $this->get_option('tegro_shop_id');
                $this->tegro_secret_key = $this->get_option('tegro_secret_key');
                $this->email_error = $this->get_option('email_error');
                $this->ip_filter = $this->get_option('ip_filter');
                $this->log_file = $this->get_option('log_file');
                $this->test_mode = $this->get_option('test_mode');

                $this->method_title = 'Интернет-эквайринг tegro';
                $this->method_description = 'Интернет-эквайринг tegro (прием платежей), интеграция с другими платежными системами.';

                add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
                add_action('woocommerce_api_wc_' . $this->id, array($this, 'check_ipn_response'));

                if (!$this->is_valid_for_use())
                {
                    $this->enabled = false;
                }
            }

            function is_valid_for_use()
            {
                return true;
            }


            public function admin_options()
            {
                ?>
                <h3><?php _e('tegro', 'woocommerce'); ?></h3>
                <p><?php _e('Настройка приема электронных платежей через tegro.', 'woocommerce'); ?></p>

                <?php if ( $this->is_valid_for_use() ) : ?>
                  <table class="form-table">
                      <?php $this->generate_settings_html(); ?>
                  </table>

                  <?php else : ?>
                      <div class="inline error">
                        <p>
                          <strong><?php _e('Шлюз отключен', 'woocommerce'); ?></strong>:
                          <?php _e('tegro не поддерживает валюты Вашего магазина.', 'woocommerce' ); ?>
                      </p>
                  </div>
                  <?php
              endif;
          }

          function init_form_fields()
          {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Включить/Выключить', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Включен', 'woocommerce'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Название', 'woocommerce'),
                    'type' => 'text',
                    'description' => __( 'Это название, которое пользователь видит во время выбора способа оплаты.', 'woocommerce' ),
                    'default' => __('tegro', 'woocommerce')
                ),
                'tegro_url' => array(
                    'title' => __('URL мерчанта', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('url для оплаты в системе tegro', 'woocommerce'),
                    'default' => 'https://tegro.money/pay/form/'
                ),
                'tegro_shop_id' => array(
                    'title' => __('Public KEY магазина', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Public KEY магазина, зарегистрированного в системе "tegro".<br/>Узнать его можно в аккаунте tegro: "Аккаунт -> Настройки".', 'woocommerce'),
                    'default' => ''
                ),
                'tegro_secret_key' => array(
                    'title' => __('Секретный ключ', 'woocommerce'),
                    'type' => 'password',
                    'description' => __('Секретный ключ оповещения о выполнении платежа,<br/>который используется для проверки целостности полученной информации<br/>и однозначной идентификации отправителя.<br/>Должен совпадать с секретным ключем, указанным в аккаунте tegro: "Аккаунт -> Настройки".', 'woocommerce'),
                    'default' => ''
                ),
                'log_file' => array(
                    'title' => __('Путь до файла для журнала оплат через tegro (например, /tegro_orders.log)', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Если путь не указан, то журнал не записывается', 'woocommerce'),
                    'default' => ''
                ),
                'ip_filter' => array(
                    'title' => __('IP фильтр', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Список доверенных ip адресов, можно указать маску', 'woocommerce'),
                    'default' => ''
                ),
                'email_error' => array(
                    'title' => __('Email для ошибок', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Email для отправки ошибок оплаты', 'woocommerce'),
                    'default' => ''
                ),
                'test_mode' => array(
                    'title' => __('Тестовый режим', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Включен', 'woocommerce'),
                    'default' => 'yes'
                ),
                'payment_system' => array(
                    'title' => __('ID платежной системы', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Переход на оплату определенной платежной системы (список можно посмотреть тут https://tegro.gitbook.io/tegro/spravochnaya-informaciya/payment-systems)', 'woocommerce'),
                    'default' => ''
                ),
            );
        }

        function payment_fields()
        {
            if ($this->description)
            {
                echo wpautop(wptexturize($this->description));
            }
        }

        public function generate_form($order_id)
        {
            global $woocommerce;

            $order = new WC_Order($order_id);

            $form_data = array(
                'shop_id'=>$this->tegro_shop_id,
                'amount'=>round($order->order_total, 2),
                'order_id'=>$order_id,
                'currency'=>$order->order_currency == 'RUR' ? 'RUB' : $order->order_currency,
            );

            setcookie( 'order_id_for_callback', $order_id, time() + 600, COOKIEPATH, COOKIE_DOMAIN );

            if ($this->get_option('test_mode') == 'yes') {
                $form_data['test'] = 1;
            }

            ksort($form_data);
            $str = http_build_query($form_data);
            $form_data['sign'] = md5($str . $this->tegro_secret_key);

            //$form_data['fields[email]'] = $order->get_billing_email();
            //$form_data['source'] = $this->source;

            $form =  '<form method="POST" action="' . $this->tegro_url . '">';
            foreach ($form_data as $k=>$v) {
                $form .= '<input type="hidden" name="'.$k.'" value="'.$v.'">';
            }
            $form .= '	<input type="submit" name="submit" value="Оплатить" /></form>';

            return $form;
        }

        function process_payment($order_id)
        {
            $order = new WC_Order($order_id);

            return array(
                'result' => 'success',
                'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
            );
        }

        function receipt_page($order)
        {
            echo '<p>'.__('Спасибо за Ваш заказ, пожалуйста, нажмите кнопку ниже, чтобы заплатить.', 'woocommerce').'</p>';
            echo $this->generate_form($order);
        }

        function check_ipn_response()
        {
            global $woocommerce;

            if (isset($_GET['tegro']) && $_GET['tegro'] == 'result')
            {
                if (isset($_POST["order_id"]) && isset($_POST["sign"]))
                {
                    $err = false;
                    $message = '';

                    // запись логов

                    $log_text =
                    "--------------------------------------------------------\n" .
                    "public key         " . sanitize_text_field($_POST['public_key']) . "\n" .
                    "amount             " . sanitize_text_field($_POST['amount']) . "\n" .
                    "order id           " . sanitize_text_field($_POST['order_id']) . "\n" .
                    "currency           " . sanitize_text_field($_POST['currency']) . "\n" .
                    "test               " . sanitize_text_field($_POST['test']) . "\n" .
                    "sign               " . sanitize_text_field($_POST['sign']) . "\n\n";

                    $log_file = $this->log_file;

                    if (!empty($log_file))
                    {
                        file_put_contents($_SERVER['DOCUMENT_ROOT'] . $log_file, $log_text, FILE_APPEND);
                    }

                    // проверка цифровой подписи и ip

                    // we must use all POST request for sign, $data never user after
                    $data = $_POST;
                    unset($data['sign']);
                    ksort($data);
                    $str = http_build_query($data);
                    $sign_hash = md5($str . $this->tegro_secret_key);

                    $valid_ip = true;
                    $sIP = str_replace(' ', '', $this->ip_filter);

                    if (!empty($sIP))
                    {
                        $arrIP = explode('.', $_SERVER['REMOTE_ADDR']);
                        if (!preg_match('/(^|,)(' . $arrIP[0] . '|\*{1})(\.)' .
                            '(' . $arrIP[1] . '|\*{1})(\.)' .
                            '(' . $arrIP[2] . '|\*{1})(\.)' .
                            '(' . $arrIP[3] . '|\*{1})($|,)/', $sIP))
                        {
                            $valid_ip = false;
                        }
                    }

                    if (!$valid_ip)
                    {
                        $message .= " - ip-адрес сервера не является доверенным\n" .
                        "   доверенные ip: " . $sIP . "\n" .
                        "   ip текущего сервера: " . $_SERVER['REMOTE_ADDR'] . "\n";
                        $err = true;
                    }

                    if ($_POST["sign"] != $sign_hash)
                    {
                        $message .= " - не совпадают цифровые подписи\n";
                        $err = true;
                    }

                    if (!$err)
                    {
                        // загрузка заказа

                        $order = new WC_Order(sanitize_text_field($_POST['order_id']));
                        $order_curr = ($order->order_currency == 'RUR') ? 'RUB' : $order->order_currency;
                        $order_amount = number_format($order->order_total, 2, '.', '');

                        // проверка суммы и валюты

                        if (number_format($_POST['amount'], 2, '.', '') !== $order_amount)
                        {
                            $message .= " - неправильная сумма\n";
                            $err = true;
                        }

                        if ($_POST['currency'] !== $order_curr)
                        {
                            $message .= " - неправильная валюта\n";
                            $err = true;
                        }

                        // проверка статуса

                        if (!$err)
                        {
                            if ($order->post_status != 'wc-processing')
                            {
                                $order->update_status('processing', __('Платеж успешно оплачен', 'woocommerce'));
                                WC()->cart->empty_cart();
                            }
                        }
                    }

                    if ($err)
                    {
                        $to = $this->email_error;

                        if (!empty($to))
                        {
                            $message = "Не удалось провести платёж через систему tegro по следующим причинам:\n\n" . $message . "\n" . $log_text;
                            $headers = "From: no-reply@" . $_SERVER['HTTP_HOST'] . "\r\n" .
                            "Content-type: text/plain; charset=utf-8 \r\n";
                            mail($to, 'Ошибка оплаты', $message, $headers);
                        }

                        if (!empty($log_file))
                        {
                            file_put_contents($_SERVER['DOCUMENT_ROOT'] . $log_file, "ERR: $message", FILE_APPEND);
                        }

                        die(sanitize_text_field($_POST['order_id']) . '|error');
                    }
                    else
                    {
                        die(sanitize_text_field($_POST['order_id']) . '|success');
                    }
                }
                else
                {
                    wp_die('IPN Request Failure');
                }
            }
            else if (isset($_GET['tegro']))
            {
                WC()->cart->empty_cart();

                if (isset($_COOKIE['order_id_for_callback']) && !empty($_COOKIE['order_id_for_callback'])) {

                    $order_id_for_callback = $_COOKIE['order_id_for_callback'];

                } else if (isset($_GET['order_id']) && !empty($_GET['order_id'])) {

                    $order_id_for_callback = $_GET['order_id'];

                }

                $order = new WC_Order(sanitize_text_field($order_id_for_callback));

                if ($_GET['tegro'] == 'success') {

                    $order->payment_complete();
                    $order->add_order_note(__('Платеж успешно оплачен через Роскассу', 'tegro'));

                } elseif ($_GET['tegro'] == 'fail') {

                    $order->update_status('failed', __('Платеж не оплачен', 'tegro'));
                    $order->add_order_note(__('Платеж не оплачен', 'tegro'));

                }

                wp_redirect($this->get_return_url($order));

            }
        }
    }

    function add_tegro_gateway($methods)
    {
        $methods[] = 'WC_tegro';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_tegro_gateway');

    function tegro_settings_link( $links ) {
        $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=tegro">' . __( 'Settings' ) . '</a>';


        array_push( $links, $settings_link );
        return $links;
    }
    $plugin = plugin_basename( __FILE__ );
    add_filter( "plugin_action_links_$plugin", 'tegro_settings_link' );
}
?>
