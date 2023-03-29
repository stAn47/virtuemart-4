<?php
/**
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the MultiSafepay plugin
 * to newer versions in the future. If you wish to customize the plugin for your
 * needs please document your changes and make backups before you update.
 *
 * @author      MultiSafepay <integration@multisafepay.com>
 * @copyright   Copyright (c) MultiSafepay, Inc. (https://www.multisafepay.com)
 * @license     http://www.gnu.org/licenses/gpl-3.0.html
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
 * PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN
 * ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

defined('_JEXEC') or die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

if (!class_exists('vmPSPlugin'))
{
    require(VMPATH_PLUGINLIBS . DS . 'vmpsplugin.php');
}

require_once(__DIR__ . '/multisafepay_api/MultiSafepay.combined.php');

class plgVmPaymentMultisafepay extends vmPSPlugin
{
    public const GATEWAYS_REQUIRE_SHOPPING_CART = [
        'AFTERPAY',
        'EINVOICE',
        'IN3',
        'KLARNA',
        'PAYAFTER',
        'BNPL_INSTM'
    ];
    public array $tableFields;
    public int $_virtuemart_paymentmethod_id;

    /**
     * @param $subject
     * @param $config
     */
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->_loggable = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';
        $varsToPush = $this->getVarsToPush();
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    /**
     * @return string
     */
    protected function getVmPluginCreateTableSQL(): string
    {
        return $this->createTableSQL('Payment MultiSafepay Table');
    }

    /**
     * @return array
     */
    public function getTableSQLFields(): array
    {
        return [
            'id' => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(11) UNSIGNED',
            'order_number' => 'char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name' => "char(255) NOT NULL DEFAULT ''",
            'payment_order_total' => "decimal(15,5) NOT NULL DEFAULT '0.00000'",
            'payment_currency' => 'char(3)',
            'cost_per_transaction' => 'decimal(10,2)',
            'cost_percent_total' => 'decimal(10,2)',
            'tax_id' => 'smallint(11)',
            'multisafepay_order_id' => 'int(11) UNSIGNED',
            'multisafepay_transaction_id' => 'char(64)',
            'multisafepay_gateway' => 'char(32)',
            'multisafepay_ip_address' => 'char(32)',
            'multisafepay_status' => "char(32) DEFAULT 'NEW'"
        ];
    }

    /**
     * @param $cart
     * @param $order
     * @return ?bool
     * @throws Exception
     */
    public function plgVmConfirmedOrder($cart, $order): ?bool
    {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $app = JFactory::getApplication();
        if (!is_null($app)) {
            $lang = $app->getLanguage();
        } else {
            $lang = JFactory::getLanguage();
        }

        $filename = 'com_virtuemart';
        $lang->load($filename, JPATH_ADMINISTRATOR);
        $vendorId = 0;

        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }

        self::getPaymentCurrency($method);

        $q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
        $db = JFactory::getContainer()->get('DatabaseDriver');
        $db->setQuery($q);
        $currency_code_3 = $db->loadResult();

        $paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
        $totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2);

        $this->_virtuemart_paymentmethod_id = $order['details']['BT']->virtuemart_paymentmethod_id;
        $issuer = $this->_getSelectedBank($this->_virtuemart_paymentmethod_id) ?? '';

        $dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['payment_name'] = $this->renderPluginName($method);
        $dbValues['virtuemart_paymentmethod_id'] = $this->_virtuemart_paymentmethod_id;
        $dbValues['cost_per_transaction'] = $method->cost_per_transaction;
        $dbValues['cost_percent_total'] = $method->cost_percent_total;
        $dbValues['payment_currency'] = $currency_code_3;
        $dbValues['payment_order_total'] = $totalInPaymentCurrency;
        $dbValues['tax_id'] = $method->tax_id;
        $dbValues['multisafepay_order_id'] = $cart->virtuemart_order_id;
        $dbValues['multisafepay_status'] = 'NEW';
        $this->storePSPluginInternalData($dbValues);

        $amount = $totalInPaymentCurrency * 100;

        $items = '<ul>';
        foreach ($order['items'] as $k) {
            $items .= '<li>' . $k->product_quantity . ' x ' . $k->order_item_name . '</li>';
        }
        $items .= '</ul>';

        $locale = $lang->getTag();
        $locale = str_replace('-', '_', $locale);
        $referrer = '';
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $referrer = htmlspecialchars($_SERVER['HTTP_REFERER']);
        }
        $user_agent = JBrowser::getInstance()->getAgentString();

        $msp = new MultiSafepay();
        $msp->test = (string)$method->sandbox === '1';
        $msp->merchant['account_id'] = $method->multisafepay_account_id;
        $msp->merchant['site_id'] = $method->multisafepay_site_id;
        $msp->merchant['site_code'] = $method->multisafepay_secure_code;
        $msp->merchant['api_key'] = $method->multisafepay_api_key;

        $msp->merchant['notification_url'] = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginResponseReceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&type=initial');
        $msp->merchant['cancel_url'] = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id);
        $msp->merchant['redirect_url'] = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginResponseReceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&type=redirect');
        $msp->merchant['close_window'] = '1';

        $msp->customer['locale'] = $locale;
        $msp->customer['firstname'] = $order['details']['BT']->first_name;
        $msp->customer['lastname'] = $order['details']['BT']->last_name;
        $msp->customer['zipcode'] = $order['details']['BT']->zip;
        $msp->customer['city'] = $order['details']['BT']->city;
        $msp->customer['state'] = @ShopFunctions::getStateByID($order['details']['BT']->virtuemart_state_id ?? 0);
        $msp->customer['country'] = @ShopFunctions::getCountryByID($order['details']['BT']->virtuemart_country_id ?? 0, 'country_2_code');
        $msp->customer['phone'] = $order['details']['BT']->phone_1;
        $msp->customer['email'] = $order['details']['BT']->email;
        $msp->customer['referrer'] = $referrer;
        $msp->customer['user_agent'] = $user_agent ?: '';
        $msp->parseCustomerAddress($order['details']['BT']->address_1);

        if ((string)$msp->customer['housenumber'] === '') {
            $msp->customer['address1'] = $order['details']['BT']->address_1;
            $msp->customer['housenumber'] = $order['details']['BT']->address_2;
        }

        $address = ($order['details']['ST'] ?? $order['details']['BT']);

        $msp->delivery['firstname'] = $address->first_name;
        $msp->delivery['lastname'] = $address->last_name;
        $msp->delivery['zipcode'] = $address->zip;
        $msp->delivery['city'] = $address->city;
        $msp->delivery['state'] = @ShopFunctions::getStateByID($address->virtuemart_state_id ?? 0);
        $msp->delivery['country'] = @ShopFunctions::getCountryByID($address->virtuemart_country_id ?? 0, 'country_2_code');
        $msp->delivery['phone'] = $address->phone_1;
        $msp->delivery['email'] = $address->email;
        $msp->parseDeliveryAddress($address->address_1);

        if ((string)$msp->delivery['housenumber'] === '') {
            $msp->delivery['address1'] = $address->address_1;
            $msp->delivery['housenumber'] = $address->address_2;
        }

        $msp->gatewayinfo['referrer'] = $referrer;
        $msp->gatewayinfo['user_agent'] = $user_agent ?: '';
        $msp->gatewayinfo['phone'] = $order['details']['BT']->phone_1;
        $msp->gatewayinfo['email'] = $order['details']['BT']->email;
        $msp->gatewayinfo['issuer'] = $issuer;

        $msp->transaction['id'] = $order['details']['BT']->order_number ?: $order['details']['ST']->order_number; // Generally the shop's order ID is used here
        $msp->transaction['currency'] = $currency_code_3;
        $msp->transaction['amount'] = $amount;
        $msp->transaction['description'] = 'Order #' . $msp->transaction['id'];
        $msp->transaction['items'] = $items;
        if ($method->multisafepay_gateway) {
            // User could be writing gateway name in lowercase
            $method->multisafepay_gateway = strtoupper(trim($method->multisafepay_gateway));
            $msp->transaction['gateway'] = $method->multisafepay_gateway;
        }
        $msp->transaction['daysactive'] = $method->multisafepay_days_active;
        $msp->plugin_name = 'Virtuemart ' . VM_VERSION;
        $msp->plugin['shop'] = 'Virtuemart';
        $msp->plugin['shop_version'] = VM_VERSION;
        $msp->plugin['plugin_version'] = $msp->version;
        $msp->plugin['partner'] = '';
        $msp->plugin['shop_root_url'] = JURI::root();

        if (in_array($method->multisafepay_gateway, self::GATEWAYS_REQUIRE_SHOPPING_CART)) {
            $tax_array = [];

            // Products
            foreach ($order['items'] as $item) {
                $product_tax = $item->product_tax;

                $product_tax_percentage = 0.00;
                if ((float)$item->product_priceWithoutTax > 0) {
                    $product_tax_percentage = round($product_tax / (float)$item->product_priceWithoutTax, 2);
                }
                $product_tax_percentage = number_format($product_tax_percentage, 2, '.', '');

                if (!in_array($product_tax_percentage, $tax_array, true)) {
                    $tax_array[] = $product_tax_percentage;
                }

                $product_price = $item->product_priceWithoutTax;
                $product_name = $item->order_item_name;
                $c_item = new MspItem($product_name, '', $item->product_quantity, $product_price, '', 0);
                $msp->cart->AddItem($c_item);
                $c_item->SetMerchantItemId($item->virtuemart_product_id);
                $c_item->SetTaxTableSelector($product_tax_percentage);
            }

            if ((string)$order['details']['BT']->coupon_discount !== '0.00') {
                if (!in_array('0.00', $tax_array, true)) {
                    $tax_array[] = '0.00';
                }

                $coupon_price = $order['details']['BT']->coupon_discount;
                $coupon_name = 'Coupon';
                $c_item = new MspItem($coupon_name, '', 1, $coupon_price, '', 0);
                $msp->cart->AddItem($c_item);
                $c_item->SetMerchantItemId('Coupon');
                $c_item->SetTaxTableSelector('0.00');
            }

            if (!empty($cart->pricesUnformatted['billDiscountAmount'])) {
                $c_item = new MspItem('Discount', '', '1', $cart->pricesUnformatted['billDiscountAmount'], 'KG', 0);
                $c_item->SetMerchantItemId('Discount');
                $c_item->SetTaxTableSelector('0.00');
                if (!in_array('0.00', $tax_array, true)) {
                    $tax_array[] = '0.00';
                }
                $msp->cart->AddItem($c_item);
            }

            // Shipping
            $shipping_tax = (float)$order['details']['ST']->order_shipment_tax;
            $shipping_price = (float)$order['details']['ST']->order_shipment;

            if ((string)$order['details']['STsameAsBT'] === '1') {
                $shipping_tax = (float)$order['details']['BT']->order_shipment_tax;
                $shipping_price = (float)$order['details']['BT']->order_shipment;
            }

            $shipping_tax_percentage = 0.00;
            if (($shipping_tax > 0) && ($shipping_price > 0)) {
                $shipping_tax_percentage = round($shipping_tax / $shipping_price, 2);
            }
            // Converts numbers to string with 2 decimals
            $shipping_tax_percentage = number_format($shipping_tax_percentage, 2, '.', '');

            if (!in_array($shipping_tax_percentage, $tax_array, true)) {
                $tax_array[] = $shipping_tax_percentage;
            }

            $shipping_name = 'Shipping';
            $c_item = new MspItem($shipping_name, '', 1, $shipping_price, '', 0);
            $msp->cart->AddItem($c_item);
            $c_item->SetMerchantItemId('msp-shipping');
            $c_item->SetTaxTableSelector($shipping_tax_percentage);

            $payment_tax = (float)$order['details']['BT']->order_payment_tax;
            $payment_price = (float)$order['details']['BT']->order_payment;

            $payment_tax_percentage = 0.00;
            if (($payment_tax > 0) && ($payment_price > 0)) {
                $payment_tax_percentage = round($payment_tax / $payment_price, 2);
            }
            $payment_tax_percentage = number_format($payment_tax_percentage, 2, '.', '');

            if (!in_array($payment_tax_percentage, $tax_array, true)) {
                $tax_array[] = $payment_tax_percentage;
            }

            if ($payment_price > 0) {
                $payment_name = 'Payment Fee';
                $c_item = new MspItem($payment_name, '', 1, $payment_price, '', 0);
                $msp->cart->AddItem($c_item);
                $c_item->SetMerchantItemId('PaymentFee');
                $c_item->SetTaxTableSelector($payment_tax_percentage);
            }

            foreach ($tax_array as $rule) {
                $table = new MspAlternateTaxTable();
                $table->name = $rule;
                $table->standalone = 'true';
                $rule = new MspAlternateTaxRule($rule);
                $table->AddAlternateTaxRules($rule);
                $msp->cart->AddAlternateTaxTables($table);
            }

            $url = $msp->startCheckout();
        } elseif (($method->multisafepay_gateway === 'IDEAL') && !empty($issuer)) {
            $msp->extravars = $issuer;
            $url = $msp->startDirectXMLTransaction();
        } else {
            $url = $msp->startTransaction();
        }

        $url = htmlspecialchars_decode($url);

        if (!empty($msp->error)) {
            $html = 'Error ' . $msp->error_code . ': ' . $msp->error;
            vRequest::setVar('html', $html);
            echo $html;
            exit();
        }

        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }

        if (!class_exists('VirtueMartCart')) {
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
        }
        $modelOrder = VmModel::getModel('orders');

        $order['customer_notified'] = 1;
        $order['comments'] = '';
        $modelOrder->updateStatusForOneOrder($order['details']['BT']->virtuemart_order_id, $order, true);

        $cart->_confirmDone = false;
        $cart->_dataValidated = false;
        $cart->setCartIntoSession();

        if (!is_null($app)) {
            $app->redirect($url, 301);
            $app->close();
        }
        exit();
    }

    /**
     * @param $paymentCurrencyId
     * @param $virtuemart_paymentmethod_id
     * @return void
     */
    public function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId): void
    {
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return;
        }
        self::getPaymentCurrency($method);
        $paymentCurrencyId = $method->payment_currency;
    }

    /**
     * Function to handle all responses
     *
     * @throws Exception
     */
    public function plgVmOnPaymentResponseReceived(&$html): mixed
    {
        if (!class_exists('VirtueMartModelOrders')) {
            require_once(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }

        $virtuemart_paymentmethod_id = vRequest::getInt('pm');

        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null;
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        if (isset($_GET['type']) && ((string)$_GET['type'] === 'feed')) {
            $this->processFeed();
            exit;
        }

        $msp = new MultiSafepay();
        $order_number = htmlspecialchars(trim($_GET['transactionid']));
        $modelOrder = new VirtueMartModelOrders();
        $order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
        $order_object = $modelOrder->getOrder($order_id);

        $msp->test = (string)$method->sandbox === '1';
        $msp->merchant['account_id'] = $method->multisafepay_account_id;
        $msp->merchant['site_id'] = $method->multisafepay_site_id;
        $msp->merchant['site_code'] = $method->multisafepay_secure_code;
        $msp->merchant['api_key'] = $method->multisafepay_api_key;
        $msp->transaction['id'] = $order_number;
        $status = $msp->getStatus();
        $details = $msp->details;

        switch ($status) {
            case 'initialized':
                vRequest::setVar('multisafepay_msg', Jtext::_('VMPAYMENT_MULTISAFEPAY_MSG_INITIALIZED'));
                $html = $this->_getPaymentResponseHtml($details, $this->renderPluginName($method));
                break;
            case 'completed':
                vRequest::setVar('multisafepay_msg', Jtext::_('VMPAYMENT_MULTISAFEPAY_MSG_COMPLETED'));
                $html = $this->_getPaymentResponseHtml($details, $this->renderPluginName($method));
                break;
            case 'uncleared':
                vRequest::setVar('multisafepay_msg', Jtext::_('VMPAYMENT_UNCLEARED_MSG_UNCLEARED'));
                $html = $this->_getPaymentResponseHtml($details, $this->renderPluginName($method));
                break;
            case 'void':
                vRequest::setVar('multisafepay_msg', Jtext::_('VMPAYMENT_MULTISAFEPAY_MSG_VOID'));
                $html = $this->_getPaymentResponseHtml($details, $this->renderPluginName($method));
                break;
            case 'declined':
                vRequest::setVar('multisafepay_msg', Jtext::_('VMPAYMENT_MULTISAFEPAY_MSG_DECLINED'));
                $html = $this->_getPaymentResponseHtml($details, $this->renderPluginName($method));
                break;
            case 'refunded':
                vRequest::setVar('multisafepay_msg', Jtext::_('VMPAYMENT_MULTISAFEPAY_MSG_REFUNDED'));
                $html = $this->_getPaymentResponseHtml($details, $this->renderPluginName($method));
                break;
            case 'expired':
                vRequest::setVar('multisafepay_msg', Jtext::_('VMPAYMENT_MULTISAFEPAY_MSG_EXPIRED'));
                $html = $this->_getPaymentResponseHtml($details, $this->renderPluginName($method));
                break;
            case 'cancelled':
                vRequest::setVar('multisafepay_msg', Jtext::_('VMPAYMENT_MULTISAFEPAY_MSG_CANCELED'));
                $html = $this->_getPaymentResponseHtml($details, $this->renderPluginName($method));
                break;
            case 'shipped':
                vRequest::setVar('multisafepay_msg', Jtext::_('VMPAYMENT_MULTISAFEPAY_MSG_SHIPPED'));
                $html = $this->_getPaymentResponseHtml($details, $this->renderPluginName($method));
                break;
        }

        $order = [];

        switch ($status) {
            case 'initialized':
                $order['order_status'] = $method->status_initialized;
                break;
            case 'completed':
                $order['order_status'] = $method->status_completed;
                break;
            case 'uncleared':
                $order['order_status'] = $method->status_uncleared;
                break;
            case 'void':
                $order['order_status'] = $method->status_void;
                break;
            case 'declined':
                $order['order_status'] = $method->status_declined;
                break;
            case 'refunded':
                $order['order_status'] = $method->status_refunded;
                break;
            case 'expired':
                $order['order_status'] = $method->status_expired;
                break;
            case 'cancelled':
                $order['order_status'] = $method->status_canceled;
                break;
            case 'shipped':
                $order['order_status'] = $method->status_shipped;
                break;
        }

        if (((string)$order['order_status'] !== (string)$order_object['details']['BT']->order_status) && ((string)$order_object['details']['BT']->order_status !== 'S')) {
            $order['virtuemart_order_id'] = $order_id;
            $order['comments'] = '';
            if ($order['order_status'] !== $method->status_canceled)
            {
                $order['customer_notified'] = 1; // Validate this one. Can we trigger the notification to customer for the initial pending status?
            }
            else
            {
                $order['customer_notified'] = 0;
            }
            $modelOrder->updateStatusForOneOrder($order_id, $order);
        }
        if ($status !== 'cancelled') {
            $this->emptyCart();
        }

        if (isset($_GET['type'])) {
            if ((string)$_GET['type'] === 'initial') {
                $url = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginResponseReceived&on=' . $order_object['details']['BT']->order_number . '&pm=' . $order_object['details']['BT']->virtuemart_paymentmethod_id . '&transactionid=' . htmlspecialchars(trim($_GET['transactionid'])) . '&type=redirect');
                echo '<a href="' . $url . '">' . JText::_('MULTISAFEPAY_BACK_TO_STORE') . '</a>';
                exit;
            }

            if ((string)$_GET['type'] === 'redirect') {
                return $html;
            }
            echo 'OK';
            exit;
        }
        echo 'OK';
        exit;
    }

    /**
     * @param int $selected
     * @param mixed $htmlIn
     * @param VirtueMartCart $cart
     * @return bool
     * @throws Exception
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, int $selected = 0, mixed &$htmlIn = []): bool
    {
        if ((string)$this->getPluginMethods($cart->vendorId) === '0') {
            if (empty($this->_name)) {
                $app = JFactory::getApplication();
                if (!is_null($app)) {
                    $app->enqueueMessage(vmText::_('COM_VIRTUEMART_CART_NO_' . strtoupper($this->_psType)));
                }
            }

            return false;
        }

        $htmla = [];
        vmdebug('methods', $this->methods);
        VmLanguage::loadJLang('com_virtuemart');
        $currency = CurrencyDisplay::getInstance();
        foreach ($this->methods as $method) {
            if ($method->multisafepay_gateway) {
                $method->multisafepay_gateway = strtoupper(trim($method->multisafepay_gateway));
            }
            if (($method->multisafepay_gateway === 'IDEAL') && $this->checkConditions($cart, $method, $cart->cartPrices)) {
                $methodSalesPrice = $this->calculateSalesPrice($cart, $method, $cart->cartPrices);

                $msp = new MultiSafepay();
                $msp->test = (string)$method->sandbox === '1';
                $msp->merchant['account_id'] = $method->multisafepay_account_id;
                $msp->merchant['site_id'] = $method->multisafepay_site_id;
                $msp->merchant['site_code'] = $method->multisafepay_secure_code;
                $msp->merchant['api_key'] = $method->multisafepay_api_key;
                $relatedBanks = $msp->getIdealIssuers();

                $selected_bank = $this->_getSelectedBank($method->virtuemart_paymentmethod_id);

                $relatedBanksDropDown = $this->getRelatedBanksDropDown($relatedBanks, $method->virtuemart_paymentmethod_id, $selected_bank);
                $logo = $this->displayLogos($method->payment_logos);
                $payment_cost = '';
                if ($methodSalesPrice) {
                    $payment_cost = $currency->priceDisplay($methodSalesPrice);
                }
                if ($selected === (int)$method->virtuemart_paymentmethod_id) {
                    $checked = 'checked="checked"';
                } else {
                    $checked = '';
                }

                $html = $this->renderByLayout('display_payment', [
                    'plugin' => $method,
                    'checked' => $checked,
                    'payment_logo' => $logo,
                    'payment_cost' => $payment_cost,
                    'relatedBanks' => $relatedBanksDropDown
                ]);

                $htmla[] = $html;
            }
            elseif ($this->checkConditions($cart, $method, $cart->cartPrices)) {
                $methodSalesPrice = $this->calculateSalesPrice($cart, $method, $cart->cartPrices);
                $logo = $this->displayLogos($method->payment_logos);
                $payment_cost = '';
                if ($methodSalesPrice) {
                    $payment_cost = $currency->priceDisplay($methodSalesPrice);
                }
                if ($selected === (int)$method->virtuemart_paymentmethod_id) {
                    $checked = 'checked="checked"';
                } else {
                    $checked = '';
                }

                $html = $this->renderByLayout('display_payment_no_html', [
                    'plugin' => $method,
                    'checked' => $checked,
                    'payment_logo' => $logo,
                    'payment_cost' => $payment_cost
                ]);

                $htmla[] = $html;
            }
        }
        if (!empty($htmla) && !is_null($htmlIn)) {
            $htmlIn[] = $htmla;
        }

        return true;
    }

    /**
     * @param $relatedBanks
     * @param $paymentmethod_id
     * @param $selected_bank
     * @return mixed
     */
    private function getRelatedBanksDropDown($relatedBanks, $paymentmethod_id, $selected_bank): mixed
    {
        if (!($method = $this->getVmPluginMethod($paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }

        $attrs = '';
        if (VmConfig::get('oncheckout_ajax', false)) {
            $attrs = 'onchange="document.getElementById(\'payment_id_' . $paymentmethod_id . '\').checked=true; Virtuemart.updFormS(); return;"';
        }
        $idA = 'multisafepay_ideal_bank_selected_' . $paymentmethod_id;
        $listOptions[] = ['value' => '', 'text' => vmText::_('VMPAYMENT_MULTISAFEPAY_IDEAL_PLEASE_SELECT_BANK')];

        if (!empty($relatedBanks['issuers']['issuer'])) {
            foreach ($relatedBanks['issuers']['issuer'] as $relatedBank) {
                $listOptions[] = JHTML::_('select.option', $relatedBank['code']['VALUE'], $relatedBank['description']['VALUE']);
            }
        }

        return JHTML::_('select.genericlist', $listOptions, $idA, $attrs, 'value', 'text', $selected_bank);
    }

    /**
     * @param $paymentmethod_id
     * @return ?string
     * @throws Exception
     */
    private function _getSelectedBank($paymentmethod_id): ?string
    {
        $session_params = self::_getMultiSafepayIdealFromSession();
        if (!empty($session_params)) {
            $var = 'multisafepay_ideal_bank_selected_' . $paymentmethod_id;
            return $session_params->$var;
        }
        return null;
    }

    /**
     * @param $data
     * @throws Exception
     */
    private static function _setMultiSafepayIdealIntoSession($data): void
    {
        $app = JFactory::getApplication();
        if (!is_null($app)) {
            $app->getSession()->set('MultiSafepayIdeal', json_encode($data), 'vm');
        }
    }

    /**
     * @return mixed
     * @throws Exception
     */
    private static function _getMultiSafepayIdealFromSession(): mixed
    {
        $app = JFactory::getApplication();
        if (!is_null($app)) {
            $data = $app->getSession()->get('MultiSafepayIdeal', 0, 'vm');
        }

        if (empty($data)) {
            return null;
        }

        return json_decode($data, false);
    }

    /**
     * @param string $where
     * @param $plugin
     * @return string
     * @throws Exception
     */
    protected function renderPluginName($plugin, string $where = 'checkout'): string
    {
        $display_logos = '';
        $payment_param = [];
        $session_params = self::_getMultiSafepayIdealFromSession();
        if (empty($session_params)) {
            $payment_param = self::getEmptyPaymentParams($plugin->virtuemart_paymentmethod_id);
        } else {
            foreach ($session_params as $key => $session_param) {
                $payment_param[$key] = json_decode($session_param, false);
            }
        }

        $logos = $plugin->payment_logos;
        if (!empty($logos)) {
            $display_logos = $this->displayLogos($logos) . ' ';
        }
        $payment_name = $plugin->payment_name;
        $var = 'multisafepay_ideal_bank_selected_' . $plugin->virtuemart_paymentmethod_id;
        $bank_name = $session_params->$var ?? '';
        vmdebug('renderPluginName', $payment_param);

        return $this->renderByLayout('render_pluginname',[
            'logo' => $display_logos,
            'payment_name' => $payment_name,
            'bank_name' => $bank_name,
            'payment_description' => $plugin->payment_desc,
        ]);
    }

    /**
     * @param $paymentmethod_id
     * @return array
     */
    private static function getEmptyPaymentParams($paymentmethod_id): array
    {
        $payment_params['multisafepay_ideal_bank_selected_' . $paymentmethod_id] = '';

        return $payment_params;
    }

    /**
     * @return void
     */
    public function processFeed(): void
    {
        echo 'process feed';
    }

    /**
     * @return ?bool
     * @throws Exception
     */
    public function plgVmOnUserPaymentCancel(): ?bool
    {
        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }

        $order_number = htmlspecialchars(trim($_GET['transactionid']));
        $virtuemart_paymentmethod_id = vRequest::getInt('pm');

        if (empty($order_number) || empty($virtuemart_paymentmethod_id) || !$this->selectedThisByMethodId($virtuemart_paymentmethod_id)) {
            return null;
        }

        if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
            return null;
        }

        VmInfo(Jtext::_('VMPAYMENT_MULTISAFEPAY_STATUS_CANCELED_DESC'));
        $this->handlePaymentUserCancel($virtuemart_order_id);

        return true;
    }

    /**
     * Display stored payment data for an order
     * @see components/com_virtuemart/helpers/vmPSPlugin::plgVmOnShowOrderBEPayment()
     *
     * @param $payment_method_id
     * @param $virtuemart_order_id
     * @return ?string
     */
    public function plgVmOnShowOrderBEPayment($virtuemart_order_id, $payment_method_id): ?string
    {
        if (!$this->selectedThisByMethodId($payment_method_id)) {
            return null; // Another method was selected, do nothing
        }

        $db = JFactory::getContainer()->get('DatabaseDriver');
        $q = 'SELECT * FROM `' . $this->_tablename . '` WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
        $db->setQuery($q);

        if (!($paymentTable = $db->loadObject())) {
            return '';
        }
        self::getPaymentCurrency($paymentTable);
        $q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id` = "' . $paymentTable->payment_currency . '"';
        $db = JFactory::getContainer()->get('DatabaseDriver');
        $db->setQuery($q);
        $currency_code_3 = $db->loadResult();

        $html = '<table class="adminlist">' . "\n";
        $html .= $this->getHtmlHeaderBE();
        $html .= $this->getHtmlRowBE('MULTISAFEPAY_PAYMENT_NAME', $paymentTable->payment_name);
        $html .= $this->getHtmlRowBE('MULTISAFEPAY_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total . ' ' . $currency_code_3);
        $html .= '</table>' . "\n";

        return $html;
    }

    /**
     * @param $payment_name
     * @param $data
     * @return string
     */
    public function _getPaymentResponseHtml($data, $payment_name): string
    {
        $html = '<table style="padding:0;margin:0;" class="multisafepay-table">' . "\n";
        $html .= $this->getHtmlRow('MULTISAFEPAY_PAYMENT_NAME', $payment_name);
        $html .= $this->getHtmlRow('MULTISAFEPAY_STATUS', $data['ewallet']['status']);
        $html .= $this->getHtmlRow('MULTISAFEPAY_PAYMENT_TRANSACTIONID', $data['transaction']['id']);
        $html .= '</table>' . "\n";

        return $html;
    }

    /**
     * @param $method
     * @param  $cart_prices
     * @param VirtueMartCart $cart
     * @return float
     */
    public function getCosts(VirtueMartCart $cart, $method, $cart_prices): float
    {
        if (str_ends_with($method->cost_percent_total, '%')) {
            $cost_percent_total = substr($method->cost_percent_total, 0, -1);
        } else {
            $cost_percent_total = $method->cost_percent_total;
        }

        return ((float)$method->cost_per_transaction + ((float)$cart_prices['salesPrice'] * (float)$cost_percent_total * 0.01));
    }

    /**
     * Check if the payment conditions are fulfilled for this payment method
     *
     * @param $method
     * @param $cart_prices : cart prices
     * @param $cart
     * @return true if the conditions are fulfilled, false otherwise
     */
    protected function checkConditions($cart, $method, $cart_prices): bool
    {
        if ($method->multisafepay_ip_validation) {
            $ip = explode(';', $method->multisafepay_ip_address);

            if (!in_array($_SERVER['REMOTE_ADDR'], $ip)) {
                $test = false;
            } else {
                $test = true;
            }
        } else {
            $test = true;
        }

        if (method_exists($cart, 'getST')) {
            $address = $cart->getST();
        } else {
            $address = (
            (
                (
                    (int)$cart->ST === 0
                ) ||
                (
                    (int)$cart->STSameAsBT === 1
                )
            ) ? $cart->BT : $cart->ST
            );
        }

        $amount = (float)$cart_prices['salesPrice'];
        $amount_cond = (
            (
                ($amount >= (float)$method->min_amount) &&
                ($amount <= (float)$method->max_amount)
            ) ||
            (
                ((float)$method->min_amount <= $amount) &&
                ((float)$method->max_amount === 0.0)
            )
        );

        $countries = [];
        if (!empty($method->countries)) {
            if (!is_array($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }
        // Probably did not give his BT:ST address
        if (!is_array($address)) {
            $address = [];
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id'])) {
            $address['virtuemart_country_id'] = 0;
        }

        if (in_array($address['virtuemart_country_id'], $countries) || (count($countries) === 0)) {
            if ($amount_cond && $test) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     *
     * @param string $jplugin_id
     * @return ?bool
     */
    public function plgVmOnStoreInstallPaymentPluginTable(string $jplugin_id): ?bool
    {
        if ($jplugin_id !== (string)$this->_jid) {
            return false;
        }
        return $this->onStoreInstallPluginTable();
    }

    /**
     * This event is fired after the payment method has been selected. It can be used to store
     * additional payment info in the cart.
     *
     * @param $msg
     * @param VirtueMartCart $cart The actual cart
     * @return ?bool if the payment was not selected, true if the data is valid, error message if the data is not valid
     * @throws Exception
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg): ?bool
    {
        if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
            return null; // Another method was selected, do nothing
        }

        if (!($method = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }

        if ($method->multisafepay_gateway) {
            $method->multisafepay_gateway = strtoupper(trim($method->multisafepay_gateway));

            if ($method->multisafepay_gateway === 'IDEAL') {
                $payment_params['multisafepay_ideal_bank_selected_' . $cart->virtuemart_paymentmethod_id] = vRequest::getVar('multisafepay_ideal_bank_selected_' . $cart->virtuemart_paymentmethod_id);
                if (empty($payment_params['multisafepay_ideal_bank_selected_' . $cart->virtuemart_paymentmethod_id])) {
                    vmInfo('VMPAYMENT_MULTISAFEPAY_IDEAL_PLEASE_SELECT_BANK');
                    return false;
                }
                self::_setMultiSafepayIdealIntoSession($payment_params);
            }
        }

        return true;
    }

    /**
     * Calculate the price (value, tax_id) of the selected method.
     * It is called by the calculator.
     * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
     *
     * @param array $cart_prices
     * @param $cart_prices_name
     * @param VirtueMartCart $cart
     * @return ?bool if the method was not selected, false if the shipping rate is not valid anymore, true otherwise
     */
    public function plgVmOnSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name): ?bool
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    /**
     * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type
     *
     * @param VirtueMartCart $cart cart: the cart object
     * @return null if no plugin was found, 0 if more than one plugin was found, virtuemart_xxx_id if only one plugin is found
     */
    public function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = []): ?array
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices);
    }

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the method-specific data.
     *
     * @param $virtuemart_paymentmethod_id
     * @param $payment_name
     * @param $virtuemart_order_id
     * @return void Null for methods that aren't active, text (HTML) otherwise
     */
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name): void
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    /**
     * This event is fired during the checkout process. It can be used to validate the method data as entered by the user.
     *
     * @param VirtueMartCart $cart
     * @return ?bool True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
     * @throws Exception
     */
    public function plgVmOnCheckoutCheckDataPayment(VirtueMartCart $cart): ?bool
    {
        if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
            return null; // Another method was selected, do nothing
        }

        if (!($method = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }

        if ($method->multisafepay_gateway) {
            $method->multisafepay_gateway = strtoupper(trim($method->multisafepay_gateway));

            if ($method->multisafepay_gateway === 'IDEAL') {
                $payment_params['multisafepay_ideal_bank_selected_' . $cart->virtuemart_paymentmethod_id] = vRequest::getVar('multisafepay_ideal_bank_selected_' . $cart->virtuemart_paymentmethod_id);

                if (empty($payment_params['multisafepay_ideal_bank_selected_' . $cart->virtuemart_paymentmethod_id])) {
                    $payment_params['multisafepay_ideal_bank_selected_' . $cart->virtuemart_paymentmethod_id] = $this->_getSelectedBank($cart->virtuemart_paymentmethod_id);
                }

                if (empty($payment_params['multisafepay_ideal_bank_selected_' . $cart->virtuemart_paymentmethod_id])) {
                    vmInfo('VMPAYMENT_MULTISAFEPAY_IDEAL_PLEASE_SELECT_BANK');
                    return false;
                }
                self::_setMultiSafepayIdealIntoSession($payment_params);
            }
        }

        return true;
    }

    /**
     * This method is fired when showing when printing an Order
     * It displays the payment method-specific data.
     *
     * @param int $method_id method used for this order
     * @param $order_number
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     */
    public function plgVmOnShowOrderPrintPayment($order_number, int $method_id): mixed
    {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    /**
     * @param $id
     * @param $table
     * @param $name
     * @return bool
     */
    public function plgVmSetOnTablePluginParamsPayment($name, $id, &$table): bool
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    /**
     * @param $data
     * @return bool
     */
    public function plgVmDeclarePluginParamsPaymentVM3(&$data): bool
    {
        return $this->declarePluginParams('payment', $data);
    }
}
