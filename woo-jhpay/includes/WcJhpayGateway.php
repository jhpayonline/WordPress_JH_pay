<?php

use GuzzleHttp\Client;

class WcJhpayGateway extends WC_Payment_Gateway
{
    public string $webhook_name = 'pay-jhpay-payment-result';
    public string $webhook_url = '';
    public string $base_url = 'https://pay.jhpay.online/api/pay/';

    public function __construct()
    {
        $this->id = 'pay-jhpay';
        $this->method_title = 'Платёжный шлюз jhpay.online';
        $this->method_description = 'Платёжный шлюз jhpay.online';

        $this->supports = ['products'];

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');

        $this->currency = $this->get_option('currency');
        $this->token = $this->get_option('token');

        $this->webhook_url = site_url("/wc-api/$this->webhook_name");

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action("woocommerce_api_$this->webhook_name", [$this, 'webhook']);
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title' => 'Включен/Выключен',
                'label' => 'Включить платёжный шлюз',
                'type' => 'checkbox',
                'default' => 'no'
            ],
            'title' => [
                'title' => 'Заголовок',
                'type' => 'text',
                'description' => 'Этот текст отображается пользователю как название метода оплаты на странице оформления заказа',
                'default' => 'Оплатить картой через jhpay.online',
                'desc_tip' => true,
            ],
            'description' => [
                'title' => 'Описание',
                'type' => 'textarea',
                'description' => 'Описание этого метода оплаты, которое будет отображаться пользователю на странице оформления заказа.',
                'default' => '',
            ],
            'token' => [
                'title' => 'Токен',
                'type' => 'text'
            ],
            'currency' => [
                'title' => 'Валюта',
                'type' => 'select',
                'options' => [
                    643 => 'RUB',
                    840 => 'USD',
                    980 => 'UAH',
                    398 => 'KZT',
                    860 => 'UZS',
                ],
                'default' => 643
            ],
        ];
    }

    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);
        $email = $order->get_billing_email();
        $phone_number = $order->get_billing_phone();
        $name = $order->get_billing_first_name();
        $last_name = $order->get_billing_last_name();
        $full_name = implode(' ', [$name, $last_name]);

        $client = new Client([
            'base_uri' => $this->base_url,
            'http_errors' => false,
            'verify' => false,
            'headers' => [
                'API-TOKEN' => $this->token,
            ]
        ]);

        $response = $client->post('order/create', [
            'json' => [
                'orderNumber' => $order_id . '-' . time(),
                'description' => $order_id,
                'amount' => $order->get_total(),
                'currency' => $this->currency,
                'phone' => $phone_number,
                'email' => $email,
                'name' => $full_name,
            ]
        ]);

        $content = $response->getBody()->getContents();
        $result = json_decode($content, true);

        if (is_array($result) && isset($result['formUrl'])) {
            return [
                'result' => 'success',
                'redirect' => $result['formUrl']
            ];
        }

        return [
            'result' => 'fail',
        ];
    }

    public function webhook()
    {
        $post = $_POST;

        if (empty($post)) {
            $post = json_decode(file_get_contents('php://input'), true);
        }

        update_option('pay-jhpay-debug', json_encode($post, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $sign = $_SERVER['HTTP_SIGNATURE'];
        $sign2 = hash_hmac('sha256', $post['id'] . '|' . $post['createdDateTime'] . '|' . $post['amount'], $this->token);

        if ($sign !== $sign2) {
            wp_send_json(['success' => false]);
            wp_die();
        }

        $order_id = explode('-', $post['order_id']);
        $order = wc_get_order($order_id[0]);

        if (!$order) {
            wp_send_json(['success' => false]);
            wp_die();
        }

        if (isset($post['status']) && $post['status'] === 'PAID') {
            $order->payment_complete();
        }

        wp_send_json(['success' => true]);
        wp_die();
    }
}
