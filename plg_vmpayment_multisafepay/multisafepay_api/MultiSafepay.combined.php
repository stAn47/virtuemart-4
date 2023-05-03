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

if (!class_exists('MultiSafepay'))
{
    class MultiSafepay
    {
        public const MSP_VERSION = '1.0.1';

        public string $plugin_name = '';
        public string $version = '';
        // Test or Live Api
        public bool $test = false;
        public string $custom_api = '';
        public string $extravars = '';
        public bool $use_shipping_notification = false;

        // Merchant data
        public array $merchant = [
            'account_id' => '', // Required
            'site_id' => '', // Required
            'site_code' => '', // Required
            'api_key' => '', // Required
            'notification_url' => '',
            'cancel_url' => '',
            'redirect_url' => '',
            'close_window' => ''
        ];

        // Customer data
        public array $customer = [
            'locale' => '', // Advised
            'ipaddress' => '',
            'forwardedip' => '',
            'firstname' => '',
            'lastname' => '',
            'address1' => '',
            'address2' => '',
            'housenumber' => '',
            'zipcode' => '',
            'city' => '',
            'state' => '',
            'country' => '',
            'phone' => '',
            'email' => '', // advised
            'accountid' => '',
            'accountholdername' => '',
            'accountholdercity' => '',
            'accountholdercountry' => '',
            'user_agent' => '',
            'referrer' => '',
            'bankaccount' => '',
            'birthday' => ''
        ];

        // Customer-delivery data
        public array $delivery = [
            'firstname' => '',
            'lastname' => '',
            'address1' => '',
            'address2' => '',
            'housenumber' => '',
            'zipcode' => '',
            'city' => '',
            'state' => '',
            'country' => '',
            'phone' => '',
            'email' => ''
        ];

        // Transaction data
        public array $transaction = [
            'id' => '', // Required
            'currency' => '', // Required
            'amount' => '', // Required
            'description' => '', // Required
            'var1' => '',
            'var2' => '',
            'var3' => '',
            'items' => '',
            'manual' => 'false',
            'gateway' => '',
            'daysactive' => '',
            'invoice_id' => '',
            'shipdate' => ''
        ];

        public array $gatewayinfo = [
            'user_agent' => '',
            'referrer' => '',
            'bankaccount' => '',
            'birthday' => '',
            'phone' => '',
            'email' => '',
            'issuer' => ''
        ];

        public array $plugin = [
            'shop' => '',
            'shop_version' => '',
            'plugin_version' => '',
            'partner' => '',
            'shop_root_url' => ''
        ];

        public array $ganalytics = [
            'account' => '',
            'domainName' => 'none'
        ];

        public MspCart $cart;
        public MspCustomFields $fields;
        // Signature
        public string $cart_xml;
        public string $fields_xml;
        public string $signature;
        // Return public
        public string $api_url;
        public string $request_xml;
        public bool|string $reply_xml;
        public string $payment_url;
        public mixed $status;
        public mixed $error_code;
        public array $details;
        public array $parsed_xml;
        public ?string $parsed_root;

        /**
         * MultiSafepay constructor
         *
         * @since 1.7
         */
        public function __construct()
        {
            $this->cart = new MspCart();
            $this->fields = new MspCustomFields();
            $this->version = self::MSP_VERSION;
        }

        /**
         * Start direct xml function. Direct ideal gateway etc
         *
         * @since 1.7
         * @return bool|string
         */
        public function startDirectXMLTransaction(): bool|string
        {
            $this->checkSettings();

            $this->setIp();
            $this->createSignature();

            // Create request
            $this->request_xml = $this->createDirectXMLTransactionRequest();

            // Post request and get reply
            $this->api_url = $this->getApiUrl();
            $this->reply_xml = $this->xmlPost($this->api_url, $this->request_xml);

            // Communication error
            if (!$this->reply_xml)
            {
                return false;
            }

            // Parse xml
            $rootNode = $this->parseXmlResponse($this->reply_xml);
            if (!$rootNode)
            {
                return false;
            }

            // Return payment url
            $this->payment_url = $this->xmlUnescape($rootNode['gatewayinfo']['redirecturl']['VALUE']);

            return $this->payment_url;
        }

        /**
         * @since 1.7
         *
         * @return string
         */
        public function startDirectBankTransfer(): string
        {
            $this->checkSettings();

            $this->setIp();
            $this->createSignature();

            // Create request
            $this->request_xml = $this->createDirectBankTransferTransactionRequest();

            // Post request and get reply
            $this->api_url = $this->getApiUrl();
            $this->reply_xml = $this->xmlPost($this->api_url, $this->request_xml);

            // Communication error
            if (!$this->reply_xml)
            {
                return false;
            }

            // Parse xml
            $rootNode = $this->parseXmlResponse($this->reply_xml);
            if (!$rootNode)
            {
                return false;
            }

            // Return payment url
            $this->payment_url = '';

            return $this->payment_url;
        }

        /**
         * Check the settings before using them
         *
         * @since 1.7
         *
         * @return void
         */
        public function checkSettings(): void
        {
            // Trim any spaces
            $this->merchant['account_id'] = trim($this->merchant['account_id']);
            $this->merchant['site_id'] = trim($this->merchant['site_id']);
            $this->merchant['site_code'] = trim($this->merchant['site_code']);
            $this->merchant['api_key'] = trim($this->merchant['api_key']);
        }

        /**
         * @since 1.7
         *
         * @return mixed
         */
        public function getIdealIssuers(): mixed
        {
            $this->request_xml = $this->createIdealIssuersRequest();
            $this->api_url = $this->getApiUrl();
            $this->reply_xml = $this->xmlPost($this->api_url, $this->request_xml);

            return $this->parseXmlResponse($this->reply_xml);
        }

        /**
         * @since 1.7
         *
         * @return string
         */
        public function createIdealIssuersRequest(): string
        {
            return '<?xml version="1.0" encoding="UTF-8"?>
		<idealissuers ua="iDeal Issuers Request">
			<merchant>
				<account>' . $this->xmlEscape($this->merchant['account_id']) . '</account>
				<site_id>' . $this->xmlEscape($this->merchant['site_id']) . '</site_id>
				<site_secure_code>' . $this->xmlEscape($this->merchant['site_code']) . '</site_secure_code>
			</merchant>
		</idealissuers>';
        }

        /**
         * Starts a transaction and returns the payment url
         *
         * @since 1.7
         *
         * @return bool|string
         */
        public function startTransaction(): bool|string
        {
            $this->checkSettings();

            $this->setIp();
            $this->createSignature();
            // Referer
            $this->SetRef();

            // Create request
            $this->request_xml = $this->createTransactionRequest();

            // Post request and get reply
            $this->api_url = $this->getApiUrl();
            $this->reply_xml = $this->xmlPost($this->api_url, $this->request_xml);

            // Communication error
            if (!$this->reply_xml)
            {
                return false;
            }

            // Parse xml
            $rootNode = $this->parseXmlResponse($this->reply_xml);
            if (!$rootNode)
            {
                return false;
            }

            // Return payment url
            $this->payment_url = $this->xmlUnescape($rootNode['transaction']['payment_url']['VALUE']);

            return $this->payment_url;
        }

        /**
         * Starts a checkout transaction and returns the payment url
         *
         * @since 1.7
         *
         * @return bool|string
         */
        public function startCheckout(): bool|string
        {
            $this->checkSettings();

            $this->setIp();
            $this->createSignature();

            // Create request
            $this->request_xml = $this->createCheckoutRequest();

            // Post request and get reply
            $this->api_url = $this->getApiUrl();
            $this->reply_xml = $this->xmlPost($this->api_url, $this->request_xml);

            // Communication error
            if (!$this->reply_xml)
            {
                return false;
            }

            // Parse xml
            $rootNode = $this->parseXmlResponse($this->reply_xml);
            if (!$rootNode)
            {
                return false;
            }

            // Return payment url
            $this->payment_url = $this->xmlUnescape($rootNode['transaction']['payment_url']['VALUE']);

            return $this->payment_url;
        }

        /**
         * Return the status for the specified transactionid
         *
         * @since 1.7
         */
        public function getStatus()
        {
            $this->checkSettings();

            // Generate request
            $this->request_xml = $this->createStatusRequest();

            // Post request and get reply
            $this->api_url = $this->getApiUrl();
            $this->reply_xml = $this->xmlPost($this->api_url, $this->request_xml);

            // Communication error
            if (!$this->reply_xml)
            {
                return false;
            }

            // Parse xml
            $rootNode = $this->parseXmlResponse($this->reply_xml);
            if (!$rootNode)
            {
                return false;
            }

            // parse all the order details
            $details = $this->processStatusReply($rootNode);
            $this->details = $details;

            // Return status
            $this->status = $rootNode['ewallet']['status']['VALUE'];

            return $this->status;
        }

        /**
         * Send update transaction
         *
         * @since 1.7
         *
         * @return bool
         */
        public function updateTransaction(): bool
        {
            $this->checkSettings();

            // Generate request
            $this->request_xml = $this->createUpdateTransactionRequest();

            // Post request and get reply
            $this->api_url = $this->getApiUrl();
            $this->reply_xml = $this->xmlPost($this->api_url, $this->request_xml);

            // Communication error
            if (!$this->reply_xml)
            {
                return false;
            }

            // Parse xml
            $rootNode = $this->parseXmlResponse($this->reply_xml);
            if (!$rootNode)
            {
                return false;
            }

            // Parse all the order details
            $details = $this->processStatusReply($rootNode);
            $this->details = $details;

            return true;
        }

        /**
         * @since 1.7
         *
         * @param $section
         *
         * @return bool
         */
        public function _isXmlSectionEmpty($section): bool
        {
            return isset($section['VALUE']);
        }

        /**
         * @since 1.7
         *
         * @param $rootNode
         *
         * @return array
         */
        public function processStatusReply($rootNode): array
        {
            $xml = $rootNode;
            $result = [];

            $copy = ['ewallet', 'customer', 'customer-delivery', 'transaction', 'paymentdetails'];

            foreach ($copy as $section)
            {
                if (isset($xml[$section]) && !$this->_isXmlSectionEmpty($xml[$section]))
                {
                    foreach ($xml[$section] as $k => $v)
                    {
                        if (isset($v['VALUE']))
                        {
                            $result[$section][$k] = $this->xmlUnescape($v['VALUE']);
                        }
                    }
                }
            }

            if (isset($xml['checkoutdata']['shopping-cart']['items']['item']))
            {
                $returnCart = [];

                if (!isset($xml['checkoutdata']['shopping-cart']['items']['item'][0]))
                {
                    $xml['checkoutdata']['shopping-cart']['items']['item'] = [$xml['checkoutdata']['shopping-cart']['items']['item']];
                }

                foreach ($xml['checkoutdata']['shopping-cart']['items']['item'] as $item)
                {
                    $returnItem = [];

                    foreach ($item as $k => $v)
                    {
                        if ((string)$k === 'merchant-private-item-data')
                        {
                            $returnItem[$k] = $v;
                            continue;
                        }

                        if ((string)$k === 'unit-price')
                        {
                            $returnItem['currency'] = $v['currency'];
                        }
                        $returnItem[$k] = $v['VALUE'];
                    }
                    $returnCart[] = $returnItem;
                }
                $result['shopping-cart'] = $returnCart;
            }

            if (!empty($xml['checkoutdata']['order-adjustment']['shipping']))
            {
                $returnShipping = [];

                foreach ($xml['checkoutdata']['order-adjustment']['shipping'] as $type => $shipping)
                {
                    $returnShipping['type'] = $type;
                    $returnShipping['name'] = $shipping['shipping-name']['VALUE'];
                    $returnShipping['cost'] = $shipping['shipping-cost']['VALUE'];
                    $returnShipping['currency'] = $shipping['shipping-cost']['currency'];
                }
                $result['shipping'] = $returnShipping;
            }

            if (!empty($xml['checkoutdata']['order-adjustment']['total-tax']))
            {
                $returnAdjustment = [];

                $returnAdjustment['total'] = $xml['checkoutdata']['order-adjustment']['total-tax']['VALUE'];
                $returnAdjustment['currency'] = $xml['checkoutdata']['order-adjustment']['total-tax']['currency'];

                $result['total-tax'] = $returnAdjustment;
            }

            if (!empty($xml['checkoutdata']['order-adjustment']['adjustment-total']))
            {
                $returnAdjustment = [];

                $returnAdjustment['total'] = $xml['checkoutdata']['order-adjustment']['adjustment-total']['VALUE'];
                $returnAdjustment['currency'] = $xml['checkoutdata']['order-adjustment']['adjustment-total']['currency'];

                $result['adjustment-total'] = $returnAdjustment;
            }

            if (!empty($xml['checkoutdata']['order-total']))
            {
                $returnTotal = [];

                $returnTotal['total'] = $xml['checkoutdata']['order-total']['VALUE'];
                $returnTotal['currency'] = $xml['checkoutdata']['order-total']['currency'];

                $result['order-total'] = $returnTotal;
            }

            if (!empty($xml['checkoutdata']['custom-fields']) && !$this->_isXmlSectionEmpty($xml['checkoutdata']['custom-fields']))
            {
                $result['custom-fields'] = [];

                foreach ($xml['checkoutdata']['custom-fields'] as $k => $v)
                {
                    $result['custom-fields'][$k] = $v['VALUE'];
                }
            }

            return $result;
        }

        /**
         * Returns an associative array with the ids and the descriptions of the available gateways
         * TODO-> Check error logs. This function gate an error on a private server. Research this problem or ask the merchants error log
         *
         * @since 1.7
         *
         * @return bool|array
         */
        public function getGateways(): bool|array
        {
            $this->checkSettings();

            // Generate request
            $this->request_xml = $this->createGatewaysRequest();

            // Post request and get reply
            $this->api_url = $this->getApiUrl();
            $this->reply_xml = $this->xmlPost($this->api_url, $this->request_xml);

            // Communication error
            if (!$this->reply_xml)
            {
                return false;
            }

            // Parse xml
            $rootNode = $this->parseXmlResponse($this->reply_xml);
            if (!$rootNode)
            {
                return false;
            }

            // Fix for when there's only one gateway
            $xml_gateways = $rootNode['gateways']['gateway'];
            if (!isset($xml_gateways[0]))
            {
                $xml_gateways = [$xml_gateways];
                $rootNode['gateways']['gateway'] = $xml_gateways;
            }

            // Get gateways
            $gateways = [];
            foreach ($rootNode['gateways']['gateway'] as $xml_gateway)
            {
                $gateway = [];
                $gateway['id'] = $xml_gateway['id']['VALUE'];
                $gateway['description'] = $xml_gateway['description']['VALUE'];

                // Issuers
                if (isset($xml_gateway['issuers']))
                {
                    $issuers = [];

                    foreach ($xml_gateway['issuers']['issuer'] as $xml_issuer)
                    {
                        $issuer = [];
                        $issuer['id'] = $xml_issuer['id']['VALUE'];
                        $issuer['description'] = $xml_issuer['description']['VALUE'];
                        $issuers[$issuer['id']] = $issuer;
                    }
                    $gateway['issuers'] = $issuers;
                }
                $gateways[$gateway['id']] = $gateway;
            }

            // Return
            return $gateways;
        }

        /**
         * Create the transaction request xml
         *
         * @since 1.7
         *
         * @return string
         */
        public function createTransactionRequest(): string
        {
            // Issuer attribute
            $issuer = '';
            if (!empty($this->issuer))
            {
                $issuer = ' issuer="' . $this->xmlEscape($this->issuer) . '"';
            }

            return '<?xml version="1.0" encoding="UTF-8"?>
                <redirecttransaction ua="' . $this->plugin_name . ' ' . $this->version . '">
                    <merchant>
                        <account>' . $this->xmlEscape($this->merchant['account_id']) . '</account>
                        <site_id>' . $this->xmlEscape($this->merchant['site_id']) . '</site_id>
                        <site_secure_code>' . $this->xmlEscape($this->merchant['site_code']) . '</site_secure_code>
                        <notification_url>' . $this->xmlEscape($this->merchant['notification_url']) . '</notification_url>
                        <cancel_url>' . $this->xmlEscape($this->merchant['cancel_url']) . '</cancel_url>
                        <redirect_url>' . $this->xmlEscape($this->merchant['redirect_url']) . '</redirect_url>
                        <close_window>' . $this->xmlEscape($this->merchant['close_window']) . '</close_window>
                    </merchant>
                    <plugin>
                        <shop>' . $this->xmlEscape($this->plugin['shop']) . '</shop>
                        <shop_version>' . $this->xmlEscape($this->plugin['shop_version']) . '</shop_version>
                        <plugin_version>' . $this->xmlEscape($this->plugin['plugin_version']) . '</plugin_version>
                        <partner>' . $this->xmlEscape($this->plugin['partner']) . '</partner>
                        <shop_root_url>' . $this->xmlEscape($this->plugin['shop_root_url']) . '</shop_root_url>
                    </plugin>
                    <customer>
                        <locale>' . $this->xmlEscape($this->customer['locale']) . '</locale>
                        <ipaddress>' . $this->xmlEscape($this->customer['ipaddress']) . '</ipaddress>
                        <forwardedip>' . $this->xmlEscape($this->customer['forwardedip']) . '</forwardedip>
                        <firstname>' . $this->xmlEscape($this->customer['firstname']) . '</firstname>
                        <lastname>' . $this->xmlEscape($this->customer['lastname']) . '</lastname>
                        <address1>' . $this->xmlEscape($this->customer['address1']) . '</address1>
                        <address2>' . $this->xmlEscape($this->customer['address2']) . '</address2>
                        <housenumber>' . $this->xmlEscape($this->customer['housenumber']) . '</housenumber>
                        <zipcode>' . $this->xmlEscape($this->customer['zipcode']) . '</zipcode>
                        <city>' . $this->xmlEscape($this->customer['city']) . '</city>
                        <state>' . $this->xmlEscape($this->customer['state']) . '</state>
                        <country>' . $this->xmlEscape($this->customer['country']) . '</country>
                        <phone>' . $this->xmlEscape($this->customer['phone']) . '</phone>
                        <email>' . $this->xmlEscape($this->customer['email']) . '</email>
                        <birthday>' . $this->xmlEscape($this->customer['birthday']) . '</birthday>
                        <referrer>' . $this->xmlEscape($this->customer['referrer']) . '</referrer>
                        <user_agent>' . $this->xmlEscape($this->customer['user_agent']) . '</user_agent>
                    </customer>
                    <customer-delivery>
                        <firstname>' . $this->xmlEscape($this->delivery['firstname']) . '</firstname>
                        <lastname>' . $this->xmlEscape($this->delivery['lastname']) . '</lastname>
                        <address1>' . $this->xmlEscape($this->delivery['address1']) . '</address1>
                        <address2>' . $this->xmlEscape($this->delivery['address2']) . '</address2>
                        <housenumber>' . $this->xmlEscape($this->delivery['housenumber']) . '</housenumber>
                        <zipcode>' . $this->xmlEscape($this->delivery['zipcode']) . '</zipcode>
                        <city>' . $this->xmlEscape($this->delivery['city']) . '</city>
                        <state>' . $this->xmlEscape($this->delivery['state']) . '</state>
                        <country>' . $this->xmlEscape($this->delivery['country']) . '</country>
                        <phone>' . $this->xmlEscape($this->delivery['phone']) . '</phone>
                        <email>' . $this->xmlEscape($this->delivery['email']) . '</email>
                    </customer-delivery>
                    <transaction>
                        <id>' . $this->xmlEscape($this->transaction['id']) . '</id>
                        <currency>' . $this->xmlEscape($this->transaction['currency']) . '</currency>
                        <amount>' . $this->xmlEscape($this->transaction['amount']) . '</amount>
                        <description>' . $this->xmlEscape($this->transaction['description']) . '</description>
                        <var1>' . $this->xmlEscape($this->transaction['var1']) . '</var1>
                        <var2>' . $this->xmlEscape($this->transaction['var2']) . '</var2>
                        <var3>' . $this->xmlEscape($this->transaction['var3']) . '</var3>
                        <items>' . $this->xmlEscape($this->transaction['items']) . '</items>
                        <manual>' . $this->xmlEscape($this->transaction['manual']) . '</manual>
                        <daysactive>' . $this->xmlEscape($this->transaction['daysactive']) . '</daysactive>
                        <gateway' . $issuer . '>' . $this->xmlEscape($this->transaction['gateway']) . '</gateway>
                    </transaction>
                    <signature>' . $this->xmlEscape($this->signature) . '</signature>
                </redirecttransaction>';
        }

        /**
         * @since 1.7
         *
         * @return string
         */
        public function createDirectXMLTransactionRequest(): string
        {
            $issuer = $gatewayinfo = '';
            if (!empty($this->issuer))
            {
                $issuer = ' issuer="' . $this->xmlEscape($this->issuer) . '"';
            }
            if ($this->extravars !== '')
            {
                $gatewayinfo = '<gatewayinfo>
                        <issuerid>' . $this->extravars . '</issuerid>
                    </gatewayinfo>';
            }

            return '<?xml version="1.0" encoding="UTF-8"?>
                <directtransaction ua="' . $this->plugin_name . ' ' . $this->version . '">
                    <transaction>
                        <id>' . $this->xmlEscape($this->transaction['id']) . '</id>
                        <currency>' . $this->xmlEscape($this->transaction['currency']) . '</currency>
                        <amount>' . $this->xmlEscape($this->transaction['amount']) . '</amount>
                        <description>' . $this->xmlEscape($this->transaction['description']) . '</description>
                        <var1>' . $this->xmlEscape($this->transaction['var1']) . '</var1>
                        <var2>' . $this->xmlEscape($this->transaction['var2']) . '</var2>
                        <var3>' . $this->xmlEscape($this->transaction['var3']) . '</var3>
                        <items>' . $this->xmlEscape($this->transaction['items']) . '</items>
                        <manual>' . $this->xmlEscape($this->transaction['manual']) . '</manual>
                        <daysactive>' . $this->xmlEscape($this->transaction['daysactive']) . '</daysactive>
                        <gateway' . $issuer . '>' . $this->xmlEscape($this->transaction['gateway']) . '</gateway>
                    </transaction>
                    <merchant>
                        <account>' . $this->xmlEscape($this->merchant['account_id']) . '</account>
                        <site_id>' . $this->xmlEscape($this->merchant['site_id']) . '</site_id>
                        <site_secure_code>' . $this->xmlEscape($this->merchant['site_code']) . '</site_secure_code>
                        <notification_url>' . $this->xmlEscape($this->merchant['notification_url']) . '</notification_url>
                        <cancel_url>' . $this->xmlEscape($this->merchant['cancel_url']) . '</cancel_url>
                        <redirect_url>' . $this->xmlEscape($this->merchant['redirect_url']) . '</redirect_url>
                        <close_window>' . $this->xmlEscape($this->merchant['close_window']) . '</close_window>
                    </merchant>
                    <plugin>
                        <shop>' . $this->xmlEscape($this->plugin['shop']) . '</shop>
                        <shop_version>' . $this->xmlEscape($this->plugin['shop_version']) . '</shop_version>
                        <plugin_version>' . $this->xmlEscape($this->plugin['plugin_version']) . '</plugin_version>
                        <partner>' . $this->xmlEscape($this->plugin['partner']) . '</partner>
                        <shop_root_url>' . $this->xmlEscape($this->plugin['shop_root_url']) . '</shop_root_url>
                    </plugin>
                    <customer>
                        <locale>' . $this->xmlEscape($this->customer['locale']) . '</locale>
                        <ipaddress>' . $this->xmlEscape($this->customer['ipaddress']) . '</ipaddress>
                        <forwardedip>' . $this->xmlEscape($this->customer['forwardedip']) . '</forwardedip>
                        <firstname>' . $this->xmlEscape($this->customer['firstname']) . '</firstname>
                        <lastname>' . $this->xmlEscape($this->customer['lastname']) . '</lastname>
                        <address1>' . $this->xmlEscape($this->customer['address1']) . '</address1>
                        <address2>' . $this->xmlEscape($this->customer['address2']) . '</address2>
                        <housenumber>' . $this->xmlEscape($this->customer['housenumber']) . '</housenumber>
                        <zipcode>' . $this->xmlEscape($this->customer['zipcode']) . '</zipcode>
                        <city>' . $this->xmlEscape($this->customer['city']) . '</city>
                        <state>' . $this->xmlEscape($this->customer['state']) . '</state>
                        <country>' . $this->xmlEscape($this->customer['country']) . '</country>
                        <phone>' . $this->xmlEscape($this->customer['phone']) . '</phone>
                        <email>' . $this->xmlEscape($this->customer['email']) . '</email>
                        <birthday>' . $this->xmlEscape($this->customer['birthday']) . '</birthday>
                        <referrer>' . $this->xmlEscape($this->customer['referrer']) . '</referrer>
                        <user_agent>' . $this->xmlEscape($this->customer['user_agent']) . '</user_agent>
                    </customer>
                    <customer-delivery>
                        <firstname>' . $this->xmlEscape($this->delivery['firstname']) . '</firstname>
                        <lastname>' . $this->xmlEscape($this->delivery['lastname']) . '</lastname>
                        <address1>' . $this->xmlEscape($this->delivery['address1']) . '</address1>
                        <address2>' . $this->xmlEscape($this->delivery['address2']) . '</address2>
                        <housenumber>' . $this->xmlEscape($this->delivery['housenumber']) . '</housenumber>
                        <zipcode>' . $this->xmlEscape($this->delivery['zipcode']) . '</zipcode>
                        <city>' . $this->xmlEscape($this->delivery['city']) . '</city>
                        <state>' . $this->xmlEscape($this->delivery['state']) . '</state>
                        <country>' . $this->xmlEscape($this->delivery['country']) . '</country>
                        <phone>' . $this->xmlEscape($this->delivery['phone']) . '</phone>
                        <email>' . $this->xmlEscape($this->delivery['email']) . '</email>
                    </customer-delivery>
                    ' . $gatewayinfo . '
                    <signature>' . $this->xmlEscape($this->signature) . '</signature>
                </directtransaction>';
        }

        /**
         * @since 1.7
         *
         * @return string
         */
        public function createDirectBankTransferTransactionRequest(): string
        {
            $issuer = '';
            if (!empty($this->issuer))
            {
                $issuer = ' issuer="' . $this->xmlEscape($this->issuer) . '"';
            }

            return '<?xml version="1.0" encoding="UTF-8"?>
                <directtransaction ua="' . $this->plugin_name . ' ' . $this->version . '">
                    <transaction>
                        <id>' . $this->xmlEscape($this->transaction['id']) . '</id>
                        <currency>' . $this->xmlEscape($this->transaction['currency']) . '</currency>
                        <amount>' . $this->xmlEscape($this->transaction['amount']) . '</amount>
                        <description>' . $this->xmlEscape($this->transaction['description']) . '</description>
                        <var1>' . $this->xmlEscape($this->transaction['var1']) . '</var1>
                        <var2>' . $this->xmlEscape($this->transaction['var2']) . '</var2>
                        <var3>' . $this->xmlEscape($this->transaction['var3']) . '</var3>
                        <items>' . $this->xmlEscape($this->transaction['items']) . '</items>
                        <manual>' . $this->xmlEscape($this->transaction['manual']) . '</manual>
                        <daysactive>' . $this->xmlEscape($this->transaction['daysactive']) . '</daysactive>
                        <gateway' . $issuer . '>' . $this->xmlEscape($this->transaction['gateway']) . '</gateway>
                    </transaction>
                    <merchant>
                        <account>' . $this->xmlEscape($this->merchant['account_id']) . '</account>
                        <site_id>' . $this->xmlEscape($this->merchant['site_id']) . '</site_id>
                        <site_secure_code>' . $this->xmlEscape($this->merchant['site_code']) . '</site_secure_code>
                        <notification_url>' . $this->xmlEscape($this->merchant['notification_url']) . '</notification_url>
                        <cancel_url>' . $this->xmlEscape($this->merchant['cancel_url']) . '</cancel_url>
                        <redirect_url>' . $this->xmlEscape($this->merchant['redirect_url']) . '</redirect_url>
                        <close_window>' . $this->xmlEscape($this->merchant['close_window']) . '</close_window>
                    </merchant>
                    <plugin>
                        <shop>' . $this->xmlEscape($this->plugin['shop']) . '</shop>
                        <shop_version>' . $this->xmlEscape($this->plugin['shop_version']) . '</shop_version>
                        <plugin_version>' . $this->xmlEscape($this->plugin['plugin_version']) . '</plugin_version>
                        <partner>' . $this->xmlEscape($this->plugin['partner']) . '</partner>
                        <shop_root_url>' . $this->xmlEscape($this->plugin['shop_root_url']) . '</shop_root_url>
                    </plugin>
                    <customer>
                        <locale>' . $this->xmlEscape($this->customer['locale']) . '</locale>
                        <ipaddress>' . $this->xmlEscape($this->customer['ipaddress']) . '</ipaddress>
                        <forwardedip>' . $this->xmlEscape($this->customer['forwardedip']) . '</forwardedip>
                        <firstname>' . $this->xmlEscape($this->customer['firstname']) . '</firstname>
                        <lastname>' . $this->xmlEscape($this->customer['lastname']) . '</lastname>
                        <address1>' . $this->xmlEscape($this->customer['address1']) . '</address1>
                        <address2>' . $this->xmlEscape($this->customer['address2']) . '</address2>
                        <housenumber>' . $this->xmlEscape($this->customer['housenumber']) . '</housenumber>
                        <zipcode>' . $this->xmlEscape($this->customer['zipcode']) . '</zipcode>
                        <city>' . $this->xmlEscape($this->customer['city']) . '</city>
                        <state>' . $this->xmlEscape($this->customer['state']) . '</state>
                        <country>' . $this->xmlEscape($this->customer['country']) . '</country>
                        <phone>' . $this->xmlEscape($this->customer['phone']) . '</phone>
                        <email>' . $this->xmlEscape($this->customer['email']) . '</email>
                        <birthday>' . $this->xmlEscape($this->customer['birthday']) . '</birthday>
                        <referrer>' . $this->xmlEscape($this->customer['referrer']) . '</referrer>
                        <user_agent>' . $this->xmlEscape($this->customer['user_agent']) . '</user_agent>
                    </customer>
                    <customer-delivery>
                        <firstname>' . $this->xmlEscape($this->delivery['firstname']) . '</firstname>
                        <lastname>' . $this->xmlEscape($this->delivery['lastname']) . '</lastname>
                        <address1>' . $this->xmlEscape($this->delivery['address1']) . '</address1>
                        <address2>' . $this->xmlEscape($this->delivery['address2']) . '</address2>
                        <housenumber>' . $this->xmlEscape($this->delivery['housenumber']) . '</housenumber>
                        <zipcode>' . $this->xmlEscape($this->delivery['zipcode']) . '</zipcode>
                        <city>' . $this->xmlEscape($this->delivery['city']) . '</city>
                        <state>' . $this->xmlEscape($this->delivery['state']) . '</state>
                        <country>' . $this->xmlEscape($this->delivery['country']) . '</country>
                        <phone>' . $this->xmlEscape($this->delivery['phone']) . '</phone>
                        <email>' . $this->xmlEscape($this->delivery['email']) . '</email>
                    </customer-delivery>
                    <gatewayinfo>
                        <accountid>' . $this->xmlEscape($this->customer['accountid']) . '</accountid>
                        <accountholdername>' . $this->xmlEscape($this->customer['accountholdername']) . '</accountholdername>
                        <accountholdercity>' . $this->xmlEscape($this->customer['accountholdercity']) . '</accountholdercity>
                        <accountholdercountry>' . $this->xmlEscape($this->customer['accountholdercountry']) . '</accountholdercountry>
                    </gatewayinfo>
                    <signature>' . $this->xmlEscape($this->signature) . '</signature>
                </directtransaction>';
        }

        /**
         * Create the checkout request xml
         *
         * @since 1.7
         *
         * @return string
         */
        public function createCheckoutRequest(): string
        {
            $this->cart_xml = $this->cart->GetXML();
            $this->fields_xml = $this->fields->GetXML();

            $ganalytics = '';
            if (!empty($this->ganalytics['account']))
            {
                $ganalytics .= '<google-analytics>';
                $ganalytics .= '  <account>' . $this->xmlEscape($this->ganalytics['account']) . '</account>';
                $ganalytics .= '</google-analytics>';
            }

            // JB:if setting $use_shipping_notification is true, add extra element
            if ($this->use_shipping_notification)
            {
                $use_shipping_xml = "<checkout-settings>
    									<use-shipping-notification>true</use-shipping-notification>
    							</checkout-settings>";
            }
            else
            {
                $use_shipping_xml = '';
            }

            if ((string)$this->transaction['gateway'] !== '')
            {
                $trans_type = 'redirecttransaction';
            }
            else
            {
                $trans_type = 'checkouttransaction';
            }

            return '<?xml version="1.0" encoding="UTF-8"?>
            <' . $trans_type . ' ua="' . $this->plugin_name . ' ' . $this->version . '">
                <merchant>
                    <account>' . $this->xmlEscape($this->merchant['account_id']) . '</account>
                    <site_id>' . $this->xmlEscape($this->merchant['site_id']) . '</site_id>
                    <site_secure_code>' . $this->xmlEscape($this->merchant['site_code']) . '</site_secure_code>
                    <notification_url>' . $this->xmlEscape($this->merchant['notification_url']) . '</notification_url>
                    <cancel_url>' . $this->xmlEscape($this->merchant['cancel_url']) . '</cancel_url>
                    <redirect_url>' . $this->xmlEscape($this->merchant['redirect_url']) . '</redirect_url>
                    <close_window>' . $this->xmlEscape($this->merchant['close_window']) . '</close_window>
                </merchant>
                 <plugin>
                    <shop>' . $this->xmlEscape($this->plugin['shop']) . '</shop>
                    <shop_version>' . $this->xmlEscape($this->plugin['shop_version']) . '</shop_version>
                    <plugin_version>' . $this->xmlEscape($this->plugin['plugin_version']) . '</plugin_version>
                    <partner>' . $this->xmlEscape($this->plugin['partner']) . '</partner>
                    <shop_root_url>' . $this->xmlEscape($this->plugin['shop_root_url']) . '</shop_root_url>
                </plugin>
                <customer>
                    <locale>' . $this->xmlEscape($this->customer['locale']) . '</locale>
                    <ipaddress>' . $this->xmlEscape($this->customer['ipaddress']) . '</ipaddress>
                    <forwardedip>' . $this->xmlEscape($this->customer['forwardedip']) . '</forwardedip>
                    <firstname>' . $this->xmlEscape($this->customer['firstname']) . '</firstname>
                    <lastname>' . $this->xmlEscape($this->customer['lastname']) . '</lastname>
                    <address1>' . $this->xmlEscape($this->customer['address1']) . '</address1>
                    <address2>' . $this->xmlEscape($this->customer['address2']) . '</address2>
                    <housenumber>' . $this->xmlEscape($this->customer['housenumber']) . '</housenumber>
                    <zipcode>' . $this->xmlEscape($this->customer['zipcode']) . '</zipcode>
                    <city>' . $this->xmlEscape($this->customer['city']) . '</city>
                    <state>' . $this->xmlEscape($this->customer['state']) . '</state>
                    <country>' . $this->xmlEscape($this->customer['country']) . '</country>
                    <phone>' . $this->xmlEscape($this->customer['phone']) . '</phone>
                    <email>' . $this->xmlEscape($this->customer['email']) . '</email>
                    <referrer>' . $this->xmlEscape($this->customer['referrer']) . '</referrer>
                    <user_agent>' . $this->xmlEscape($this->customer['user_agent']) . '</user_agent>
                    <birthday>' . $this->xmlEscape($this->customer['birthday']) . '</birthday>
                    <bankaccount>' . $this->xmlEscape($this->customer['bankaccount']) . '</bankaccount>
                </customer>
                <customer-delivery>
                    <firstname>' . $this->xmlEscape($this->delivery['firstname']) . '</firstname>
                    <lastname>' . $this->xmlEscape($this->delivery['lastname']) . '</lastname>
                    <address1>' . $this->xmlEscape($this->delivery['address1']) . '</address1>
                    <address2>' . $this->xmlEscape($this->delivery['address2']) . '</address2>
                    <housenumber>' . $this->xmlEscape($this->delivery['housenumber']) . '</housenumber>
                    <zipcode>' . $this->xmlEscape($this->delivery['zipcode']) . '</zipcode>
                    <city>' . $this->xmlEscape($this->delivery['city']) . '</city>
                    <state>' . $this->xmlEscape($this->delivery['state']) . '</state>
                    <country>' . $this->xmlEscape($this->delivery['country']) . '</country>
                    <phone>' . $this->xmlEscape($this->delivery['phone']) . '</phone>
                    <email>' . $this->xmlEscape($this->delivery['email']) . '</email>
                </customer-delivery>
                ' . $this->cart_xml . '
                ' . $this->fields_xml . '
                ' . $ganalytics . '
                ' . $use_shipping_xml . '
                <gatewayinfo>
                    <referrer>' . $this->xmlEscape($this->gatewayinfo['referrer']) . '</referrer>
                    <user_agent>' . $this->xmlEscape($this->gatewayinfo['user_agent']) . '</user_agent>
                    <birthday>' . $this->xmlEscape($this->gatewayinfo['birthday']) . '</birthday>
                    <bankaccount>' . $this->xmlEscape($this->gatewayinfo['bankaccount']) . '</bankaccount>
                    <phone>' . $this->xmlEscape($this->gatewayinfo['phone']) . '</phone>
                    <email>' . $this->xmlEscape($this->gatewayinfo['email']) . '</email>
                    <issuerid>' . $this->xmlEscape($this->gatewayinfo['issuer']) . '</issuerid>
                </gatewayinfo>
                <transaction>
                    <id>' . $this->xmlEscape($this->transaction['id']) . '</id>
                    <currency>' . $this->xmlEscape($this->transaction['currency']) . '</currency>
                    <amount>' . $this->xmlEscape($this->transaction['amount']) . '</amount>
                    <description>' . $this->xmlEscape($this->transaction['description']) . '</description>
                    <var1>' . $this->xmlEscape($this->transaction['var1']) . '</var1>
                    <var2>' . $this->xmlEscape($this->transaction['var2']) . '</var2>
                    <var3>' . $this->xmlEscape($this->transaction['var3']) . '</var3>
                    <items>' . $this->xmlEscape($this->transaction['items']) . '</items>
                    <manual>' . $this->xmlEscape($this->transaction['manual']) . '</manual>
                    <gateway>' . $this->xmlEscape($this->transaction['gateway']) . '</gateway>
                </transaction>
                <signature>' . $this->xmlEscape($this->signature) . '</signature>
            </' . $trans_type . '>';
        }

        /**
         * Create the status request xml
         *
         * @since 1.7
         *
         * @return string
         */
        public function createStatusRequest(): string
        {
            return '<?xml version="1.0" encoding="UTF-8"?>
                <status ua="' . $this->plugin_name . ' ' . $this->version . '">
                    <merchant>
                        <account>' . $this->xmlEscape($this->merchant['account_id']) . '</account>
                        <site_id>' . $this->xmlEscape($this->merchant['site_id']) . '</site_id>
                        <site_secure_code>' . $this->xmlEscape($this->merchant['site_code']) . '</site_secure_code>
                    </merchant>
                    <transaction>
                        <id>' . $this->xmlEscape($this->transaction['id']) . '</id>
                    </transaction>
                </status>';
        }

        /**
         * Create the gateway request xml
         *
         * @since 1.7
         *
         * @return string
         */
        public function createGatewaysRequest(): string
        {
            return '<?xml version="1.0" encoding="UTF-8"?>
                <gateways ua="' . $this->plugin_name . ' ' . $this->version . '">
                    <merchant>
                        <account>' . $this->xmlEscape($this->merchant['account_id']) . '</account>
                        <site_id>' . $this->xmlEscape($this->merchant['site_id']) . '</site_id>
                        <site_secure_code>' . $this->xmlEscape($this->merchant['site_code']) . '</site_secure_code>
                    </merchant>
                    <customer>
                        <country>' . $this->xmlEscape($this->customer['country']) . '</country>
                    </customer>
                </gateways>';
        }

        /**
         * Create the update transaction request xml
         *
         * @since 1.7
         *
         * @return string
         */
        public function createUpdateTransactionRequest(): string
        {
            return '<?xml version="1.0" encoding="UTF-8"?>
                <updatetransaction>
                    <merchant>
                        <account>' . $this->xmlEscape($this->merchant['account_id']) . '</account>
                        <site_id>' . $this->xmlEscape($this->merchant['site_id']) . '</site_id>
                        <site_secure_code>' . $this->xmlEscape($this->merchant['site_code']) . '</site_secure_code>
                    </merchant>
                    <transaction>
                        <id>' . $this->xmlEscape($this->transaction['id']) . '</id>
                        <invoiceid>' . $this->xmlEscape($this->transaction['invoice_id']) . '</invoiceid>
                        <shipdate>' . $this->xmlEscape($this->transaction['shipdate']) . '</shipdate>
                        </transaction>
                </updatetransaction>';
        }

        /**
         * Creates the signature
         *
         * @since 1.7
         */
        public function createSignature(): void
        {
            $this->signature = md5(
                $this->transaction['amount'] .
                $this->transaction['currency'] .
                $this->merchant['account_id'] .
                $this->merchant['site_id'] .
                $this->transaction['id']
            );
        }

        /**
         * Sets the customers ip variables
         *
         * @since 1.7
         *
         * @return void
         */
        public function setIp(): void
        {
            $this->customer['ipaddress'] = $_SERVER['REMOTE_ADDR'];

            if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            {
                $this->customer['forwardedip'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
            }
        }

        /**
         * @since 1.7
         *
         * @return void
         */
        public function SetRef(): void
        {
            if (isset($_SERVER['HTTP_REFERER']))
            {
                $this->customer['referer'] = $_SERVER['HTTP_REFERER'];
            }
            else
            {
                $this->customer['referer'] = '';
            }
        }

        /**
         * Parses and sets customer address
         *
         * @since 1.7
         *
         * @param $street_address
         *
         * @return void
         */
        public function parseCustomerAddress($street_address): void
        {
            [$address, $apartment] = $this->parseAddress($street_address);
            $this->customer['address1'] = $address;
            $this->customer['housenumber'] = $apartment;
        }

        /**
         * Parses and sets delivery address
         *
         * @since 1.7
         */
        public function parseDeliveryAddress($street_address): void
        {
            [$address, $apartment] = $this->parseAddress($street_address);
            $this->delivery['address1'] = $address;
            $this->delivery['housenumber'] = $apartment;
        }

        /**
         * Parses and splits up an address in street and housenumber
         *
         * @since 1.7
         *
         * @param string|array $street_address
         *
         * @return array
         */
        public function parseAddress(string|array $street_address): array
        {
            $address = $street_address;
            $apartment = '';

            $offset = strlen($street_address);

            while (($offset = $this->rstrpos($street_address, ' ', $offset)) !== false)
            {
                if (($offset < (strlen($street_address) - 1)) && is_numeric($street_address[$offset + 1]))
                {
                    $address = trim(substr($street_address, 0, $offset));
                    $apartment = trim(substr($street_address, $offset + 1));
                    break;
                }
            }

            if (empty($apartment) && ($street_address !== '') && is_numeric($street_address[0]))
            {
                $pos = strpos($street_address, ' ');

                if ($pos !== false)
                {
                    $apartment = trim(substr($street_address, 0, $pos), ", \t\n\r\0\x0B");
                    $address = trim(substr($street_address, $pos + 1));
                }
            }

            return [$address, $apartment];
        }

        /**
         * @param bool $globalRate
         * @param bool $shippingTaxed
         *
         * @return void
         */
        public function setDefaultTaxZones(bool $globalRate = true, bool $shippingTaxed = true): void
        {
            if ($globalRate)
            {
                $rule = new MspDefaultTaxRule('0.21', $shippingTaxed);
                $this->cart->AddDefaultTaxRules($rule);
            }

            $table = new MspAlternateTaxTable('BTW21', 'true');
            $rule = new MspAlternateTaxRule('0.21');
            $table->AddAlternateTaxRules($rule);
            $this->cart->AddAlternateTaxTables($table);

            $table = new MspAlternateTaxTable('BTW6', 'true');
            $rule = new MspAlternateTaxRule('0.06');
            $table->AddAlternateTaxRules($rule);
            $this->cart->AddAlternateTaxTables($table);

            $table = new MspAlternateTaxTable('BTW0', 'true');
            $rule = new MspAlternateTaxRule('0.00');
            $table->AddAlternateTaxRules($rule);
            $this->cart->AddAlternateTaxTables($table);
        }

        /**
         * Returns the api url
         *
         * @since 1.7
         */
        public function getApiUrl(): string
        {
            if ($this->custom_api)
            {
                return $this->custom_api;
            }

            if ($this->test)
            {
                return 'https://testapi.multisafepay.com/ewx/';
            }

            return 'https://api.multisafepay.com/ewx/';
        }

        /**
         * Parse an xml response
         *
         * @since 1.7
         */
        public function parseXmlResponse($response)
        {
            // Strip XML line
            $response = preg_replace('#</\?xml[^>]*>#i', '', $response);

            // Parse
            $parser = new msp_gc_xmlparser($response);
            $this->parsed_xml = $parser->GetData();
            $this->parsed_root = $parser->GetRoot();

            if (!empty($this->parsed_xml[$this->parsed_root]['error']))
            {
                $this->error_code = $this->parsed_xml[$this->parsed_root]['error']['code']['VALUE'];
                $this->error = $this->parsed_xml[$this->parsed_root]['error']['description']['VALUE'];

                return false;
            }
            $rootNode = $this->parsed_xml[$this->parsed_root];

            // Check if valid response?
            // Check for error
            $result = $this->parsed_xml[$this->parsed_root]['result'];
            if (strtolower($result) !== 'ok')
            {
                $this->error_code = $rootNode['error']['code']['VALUE'];
                $this->error = $rootNode['error']['description']['VALUE'];

                return false;
            }

            return $rootNode;
        }

        /**
         * Returns the string escaped for use in XML documents
         *
         * @since 1.7
         *
         * @param string|null $str
         *
         * @return string
         */
        public function xmlEscape(?string $str): string
        {
            if (is_null($str))
            {
                return '';
            }

            return htmlspecialchars($str, ENT_COMPAT, 'UTF-8');
        }

        /**
         * Returns the string with all XML escaping removed
         *
         * @since 1.7
         *
         * @param ?string $str
         *
         * @return string
         */
        public function xmlUnescape(?string $str): string
        {
            if (is_null($str))
            {
                return '';
            }

            return html_entity_decode($str, ENT_COMPAT, 'UTF-8');
        }

        /**
         * Post the supplied XML data and return the reply
         *
         * @since 1.7
         */
        public function xmlPost(string $url = '', $request_xml = '', bool $verify_peer = false): bool|string
        {
            $curl_available = extension_loaded('curl');

            // Generate request
            $header = [];

            if (!$curl_available)
            {
                $url = parse_url($url);

                if (empty($url['port']))
                {
                    $url['port'] = (string)$url['scheme'] === 'https' ? 443 : 80;
                }

                $header[] = 'POST ' . $url['path'] . '?' . $url['query'] . ' HTTP/1.1';
                $header[] = 'Host: ' . $url['host'] . ':' . $url['port'];
                $header[] = 'Content-Length: ' . strlen($request_xml);
            }

            $header[] = 'Content-Type: text/xml';
            $header[] = 'Connection: close';

            // Issue request
            if ($curl_available)
            {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $request_xml);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 120);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify_peer);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
                curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
                curl_setopt($ch, CURLOPT_HEADER, true);
                $reply_data = curl_exec($ch);
            }
            else
            {
                $request_data = implode("\r\n", $header);
                $request_data .= "\r\n\r\n";
                $request_data .= $request_xml;
                $reply_data = '';

                $errno = 0;
                $errstr = '';

                $fp = fsockopen(((string)$url['scheme'] === 'https' ? 'ssl://' : '') . $url['host'], $url['port'], $errno, $errstr, 30);

                if ($fp)
                {
                    if (function_exists('stream_context_set_params'))
                    {
                        stream_context_set_params($fp, [
                            'ssl' => [
                                'verify_peer' => $verify_peer,
                                'allow_self_signed' => $verify_peer
                            ]
                        ]);
                    }

                    fwrite($fp, $request_data);
                    fflush($fp);

                    while (!feof($fp))
                    {
                        $reply_data .= fread($fp, 1024);
                    }
                    fclose($fp);
                }
            }

            // Check response
            if ($curl_available)
            {
                if (curl_errno($ch))
                {
                    $this->error_code = -1;
                    $this->error = 'curl error: ' . curl_errno($ch);

                    return false;
                }

                $reply_info = curl_getinfo($ch);
                curl_close($ch);
            }
            else
            {
                if ($errno)
                {
                    $this->error_code = -1;
                    $this->error = 'connection error: ' . $errno;

                    return false;
                }

                $header_size = strpos($reply_data, "\r\n\r\n");
                $header_data = substr($reply_data, 0, $header_size);
                $header = explode("\r\n", $header_data);
                $status_line = explode(' ', $header[0]);
                $content_type = 'application/octet-stream';

                foreach ($header as $header_line)
                {
                    $header_parts = explode(':', $header_line);

                    if (strtolower($header_parts[0]) === 'content-type')
                    {
                        $content_type = trim($header_parts[1]);
                        break;
                    }
                }

                $reply_info = [
                    'http_code' => (int)$status_line[1],
                    'content_type' => $content_type,
                    'header_size' => (int)$header_size + 4
                ];
            }

            if ((int)$reply_info['http_code'] !== 200)
            {
                $this->error_code = -1;
                $this->error = 'http error: ' . $reply_info['http_code'];

                return false;
            }

            if (!str_contains($reply_info['content_type'], '/xml'))
            {
                $this->error_code = -1;
                $this->error = 'content type error: ' . $reply_info['content_type'];

                return false;
            }

            // Split header and body
            $reply_xml = substr($reply_data, $reply_info['header_size']);

            if (empty($reply_xml))
            {
                $this->error_code = -1;
                $this->error = 'received empty response';

                return false;
            }

            return $reply_xml;
        }

        /**
         * @since 1.7
         *
         * @link From http://www.php.net/manual/en/function.strrpos.php#78556
         */
        public function rstrpos($haystack, $needle, $offset = null): bool|int
        {
            $size = strlen($haystack);

            if (is_null($offset))
            {
                $offset = $size;
            }

            $pos = strpos(strrev($haystack), strrev($needle), $size - $offset);

            if ($pos === false)
            {
                return false;
            }

            return $size - $pos - strlen($needle);
        }
    }
}

/**
 * Classes used to parse xml data
 */
if (!class_exists('msp_gc_xmlparser'))
{
    class msp_gc_xmlparser
    {
        public array $params = []; //Stores the object representation of XML data
        public mixed $root = null;
        public mixed $fold = false;

        /**
         * Constructor for the class
         * Takes in XML data as input( do not include the <xml> tag
         *
         * @since 1.7
         */
        public function __construct($input, $xmlParams = [XML_OPTION_CASE_FOLDING => 0])
        {
            // XML PARSE BUG: http://bugs.php.net/bug.php?id=45996
            $input = str_replace('&amp;', '[msp-amp]', $input);

            $xmlp = xml_parser_create();
            foreach ($xmlParams as $opt => $optVal)
            {
                switch ($opt)
                {
                    case XML_OPTION_CASE_FOLDING:
                        $this->fold = $optVal;
                        break;
                    default:
                        break;
                }
                xml_parser_set_option($xmlp, $opt, $optVal);
            }

            if (xml_parse_into_struct($xmlp, $input, $vals))
            {
                $this->root = $this->_foldCase($vals[0]['tag']);
                $this->params = $this->xml2ary($vals);
            }
            xml_parser_free($xmlp);
        }

        /**
         * @since 1.7
         *
         * @param $arg
         *
         * @return mixed|string
         */
        public function _foldCase($arg): mixed
        {
            return ($this->fold ? strtoupper($arg) : $arg);
        }

        /**
         * Credits for the structure of this function
         * Adapted by Ropu - 05/23/2007
         *
         * @since 1.7
         *
         * @link http://mysrc.blogspot.com/2007/02/php-xml-to-array-and-backwards.html
         */
        public function xml2ary($vals): array
        {
            $mnary = [];
            $ary = &$mnary;
            foreach ($vals as $r)
            {
                $t = $r['tag'];
                if ((string)$r['type'] === 'open')
                {
                    if (isset($ary[$t]) && !empty($ary[$t]))
                    {
                        if (isset($ary[$t][0]))
                        {
                            $ary[$t][] = [];
                        }
                        else
                        {
                            $ary[$t] = [$ary[$t], []];
                        }
                        $cv = &$ary[$t][count($ary[$t]) - 1];
                    }
                    else
                    {
                        $cv = &$ary[$t];
                    }
                    $cv = [];
                    if (isset($r['attributes']))
                    {
                        foreach ($r['attributes'] as $k => $v)
                        {
                            $cv[$k] = $v;
                        }
                    }

                    $cv['_p'] = &$ary;
                    $ary = &$cv;
                }
                elseif ((string)$r['type'] === 'complete')
                {
                    if (isset($ary[$t]) && !empty($ary[$t]))
                    { // same as open
                        if (isset($ary[$t][0]))
                        {
                            $ary[$t][] = [];
                        }
                        else
                        {
                            $ary[$t] = [$ary[$t], []];
                        }
                        $cv = &$ary[$t][count($ary[$t]) - 1];
                    }
                    else
                    {
                        $cv = &$ary[$t];
                    }
                    if (isset($r['attributes']))
                    {
                        foreach ($r['attributes'] as $k => $v)
                        {
                            $cv[$k] = $v;
                        }
                    }
                    $cv['VALUE'] = ($r['value'] ?? '');

                    // XML PARSE BUG: http://bugs.php.net/bug.php?id=45996
                    $cv['VALUE'] = str_replace('[msp-amp]', '&amp;', $cv['VALUE']);
                    //
                }
                elseif ((string)$r['type'] === 'close')
                {
                    $ary = &$ary['_p'];
                }
            }

            if (!function_exists('_del_p'))
            {
                function _del_p(&$ary): void
                {
                    foreach ($ary as $k => $v)
                    {
                        if ($k === '_p')
                        {
                            unset($ary[$k]);
                        }
                        elseif (is_array($v))
                        {
                            _del_p($v);
                        }
                    }
                }
            }

            $this->_del_p($mnary);

            return $mnary;
        }

        /**
         * _Internal: Remove recursion in result array
         *
         * @since 1.7
         */
        private function _del_p(&$ary): void
        {
            foreach ($ary as $k => $v)
            {
                if ($k === '_p')
                {
                    unset($ary[$k]);
                }
                elseif (is_array($v))
                {
                    _del_p($v);
                }
            }
        }

        /**
         * Returns the root of the XML data
         *
         * @since 1.7
         */
        public function GetRoot()
        {
            return $this->root;
        }

        /**
         * Returns the array representing the XML data
         *
         * @since 1.7
         */
        public function GetData(): array
        {
            return $this->params;
        }
    }
}

/**
 * Classes used to generate XML data
 * Based on sample code available at http://simon.incutio.com/code/php/XmlWriter.class.php.txt
 */

/**
 * Generates xml data
 */
if (!class_exists('msp_gc_XmlBuilder'))
{
    class msp_gc_XmlBuilder
    {
        public string $xml = '';
        public mixed $indent = '  ';
        public array $stack = [];

        /**
         * @since 1.7
         *
         * @param $indent
         */
        public function __construct($indent = '  ')
        {
            $this->indent = $indent;
            $this->xml = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
        }

        /**
         * @since 1.7
         *
         * @return void
         */
        public function _indent(): void
        {
            for ($i = 0, $j = count($this->stack); $i < $j; $i++)
            {
                $this->xml .= $this->indent;
            }
        }

        /**
         * Used when an element has sub-elements
         * This function add an open tag to the output
         *
         * @since 1.7
         */
        public function Push($element, $attributes = []): void
        {
            $this->_indent();
            $this->xml .= '<' . $element;
            foreach ($attributes as $key => $value)
            {
                $this->xml .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
            }
            $this->xml .= ">\n";
            $this->stack[] = $element;
        }

        /**
         * Used when an element has no sub-elements.
         * Data within the open and close tags are provided with the contents variable
         *
         * @since 1.7
         *
         * @param $content
         * @param $attributes
         * @param $element
         *
         * @return void
         */
        public function Element($element, $content, $attributes = []): void
        {
            $this->_indent();
            $this->xml .= '<' . $element;
            foreach ($attributes as $key => $value)
            {
                $this->xml .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
            }
            $this->xml .= '>' . htmlspecialchars($content) . '</' . $element . '>' . "\n";
        }

        /**
         * @since 1.7
         *
         * @param $attributes
         * @param $element
         *
         * @return void
         */
        public function EmptyElement($element, $attributes = []): void
        {
            $this->_indent();
            $this->xml .= '<' . $element;
            foreach ($attributes as $key => $value)
            {
                $this->xml .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
            }
            $this->xml .= " />\n";
        }

        /**
         * Used to close an open tag
         *
         * @since 1.7
         *
         * @param $pop_element
         *
         * @return void
         */
        public function Pop($pop_element): void
        {
            $element = array_pop($this->stack);
            $this->_indent();
            if ($element !== $pop_element)
            {
                die('XML Error: Tag Mismatch when trying to close "' . $pop_element . '"');
            }
            $this->xml .= "</$element>\n";
        }

        /**
         * @since 1.7
         *
         * @return string|void
         */
        public function GetXML()
        {
            if (count($this->stack) !== 0)
            {
                die('XML Error: No matching closing tag found for " ' . array_pop($this->stack) . '"');
            }

            return $this->xml;
        }
    }
}

/**
 * Copyright (C) 2007 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Classes used to build a shopping cart and submit it to Google Checkout
 *
 * @version $Id: googlecart.php 1234 2007-09-25 14:58:57Z ropu $
 */
if (!defined('MAX_DIGITAL_DESC'))
{
    define('MAX_DIGITAL_DESC', 1024);
}

/**
 * Creates a Google Checkout shopping cart and posts it
 * to the Google checkout sandbox or production environment
 * Refer demo/cartdemo.php for different use case scenarios for this code
 */
if (!class_exists('MspCart'))
{
    class MspCart
    {
        public string $merchant_id = '';
        public string $merchant_key = '';
        public bool|string $variant = false;
        public string $currency = '';
        public string $server_url = '';
        public string $schema_url = '';
        public string $base_url = '';
        public string $checkout_url = '';
        public string $cart_expiration = '';
        public mixed $merchant_private_data = '';
        public string $edit_cart_url = '';
        public string $continue_shopping_url = '';
        public string $request_buyer_phone = '';
        public string $merchant_calculated_tax = '';
        public string $merchant_calculations_url = '';
        public string $accept_merchant_coupons = '';
        public string $accept_gift_certificates = '';
        public string $rounding_mode = '';
        public string $rounding_rule = '';
        public string $analytics_data = '';
        public array $item_arr = [];
        public array $shipping_arr = [];
        public array $default_tax_rules_arr = [];
        public array $alternate_tax_tables_arr = [];
        public bool|string $googleAnalytics_id = false;
        public bool|string $thirdPartyTackingUrl = false;
        public array $thirdPartyTackingParams = [];

        /**
         * For HTML API Conversion
         * These tags are those that can be used more than once as a sub tag
         * so a "-#" must be added always
         used when using the html api
         * tags that can be used more than once, so they need to be numbered
         * ("-#" suffix)
         *
         * @since 1.7
         */
        public array $multiple_tags = [
            'flat-rate-shipping' => [],
            'merchant-calculated-shipping' => [],
            'pickup' => [],
            'parameterized-url' => [],
            'url-parameter' => [],
            'item' => [],
            'us-state-area' => ['tax-area'],
            'us-zip-area' => ['tax-area'],
            'us-country-area' => ['tax-area'],
            'postal-area' => ['tax-area'],
            'alternate-tax-table' => [],
            'world-area' => ['tax-area'],
            'default-tax-rule' => [],
            'alternate-tax-rule' => [],
            'gift-certificate-adjustment' => [],
            'coupon-adjustment' => [],
            'coupon-result' => [],
            'gift-certificate-result' => [],
            'method' => [],
            'anonymous-address' => [],
            'result' => [],
            'string' => []
        ];

        public array $ignore_tags = [
            'xmlns' => true,
            'checkout-shopping-cart' => true,
            'merchant-private-data' => true,
            'merchant-private-item-data' => true
        ];

        /**
         * @since 1.7
         *
         * @param string $key the merchant key
         * @param string $server_type the server type of the server to be used, one
         *                            of 'sandbox' or 'production'.
         *                            default to 'sandbox'
         * @param string $currency the currency of the items to be added to the cart
         *                            as of now values can be 'USD' or 'GBP'.
         *                            defaults to 'USD'
         *
         * Has all the logic to build the cart's xml (or html) request to be
         * posted to google's servers.
         * @param string $id the merchant id
         */
        public function __construct(string $id = '', string $key = '', string $server_type = 'sandbox', string $currency = 'EUR')
        {
            $this->merchant_id = $id;
            $this->merchant_key = $key;
            $this->currency = $currency;

            if (strtolower($server_type) === 'sandbox')
            {
                $this->server_url = 'https://sandbox.google.com/checkout/';
            }
            else
            {
                $this->server_url = 'https://checkout.google.com/';
            }

            $this->schema_url = '';
            $this->base_url = $this->server_url . 'api/checkout/v2/';
            $this->checkout_url = $this->base_url . 'checkout/Merchant/' . $this->merchant_id;
            $this->checkoutForm_url = $this->base_url . 'checkoutForm/Merchant/' . $this->merchant_id;

            // The item, shipping and tax table arrays are initialized
            $this->item_arr = [];
            $this->shipping_arr = [];
            $this->alternate_tax_tables_arr = [];
        }

        /**
         * Sets the cart's expiration date
         *
         * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_good-until-date <good-until-date>}
         *
         * @since 1.7
         *
         * @param string $cart_expire a string representing a date in ISO 8601 date and time format: {@link http://www.w3.org/TR/NOTE-datetime}
         *
         * @return void
         */
        public function SetCartExpiration(string $cart_expire): void
        {
            $this->cart_expiration = $cart_expire;
        }

        /**
         * Sets the merchant's private data.
         *
         * Google Checkout will return this data in the
         * <merchant-calculation-callback> and the
         * <new-order-notification> for the order.
         *
         * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_merchant-private-data <merchant-private-data>}
         *
         * @since 1.7
         *
         * @param MerchantPrivateData $data an object which contains the data to be sent as merchant-private-data
         *
         * @return void
         */
        public function SetMerchantPrivateData(MerchantPrivateData $data): void
        {
            $this->merchant_private_data = $data;
        }

        /**
         * Sets the url where the customer can edit his cart.
         *
         * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_edit-cart-url <edit-cart-url>}
         *
         * @since 1.7
         *
         * @param string $url the merchant's site edit cart url
         *
         * @return void
         */
        public function SetEditCartUrl(string $url): void
        {
            $this->edit_cart_url = $url;
        }

        /**
         * Sets to continue shopping url, which allows the customer to return
         * to the merchant's site after confirming an order.
         *
         * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_continue-shopping-url <continue-shopping-url>}
         *
         * @param string $url the merchant's site continue shopping url
         *
         * @since 1.7
         *
         * @return void
         */
        public function SetContinueShoppingUrl(string $url): void
        {
            $this->continue_shopping_url = $url;
        }

        /**
         * Sets whether the customer must enter a phone number to complete an order.
         * If set to true, the customer must enter a number, which Google Checkout
         * will return in the new order notification for the order.
         *
         * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_request-buyer-phone-number <request-buyer-phone-number>}
         *
         * @param bool $req true if the customer's phone number is *required* to complete an order.
         *                  defaults to false.
         *
         * @since 1.7
         *
         * @return void
         */
        public function SetRequestBuyerPhone(bool $req): void
        {
            $this->request_buyer_phone = $this->_GetBooleanValue($req, false);
        }

        /**
         * Sets the information about calculations that will be performed by the merchant.
         *
         * @since 1.7
         *
         * @param bool $tax_option true if the merchant has to do tax calculations.
         *                         defaults to false.
         * @param bool $coupons true if the merchant accepts discount coupons.
         *                         defaults to false.
         * @param bool $gift_cert true if the merchant accepts gift certificates.
         *                         defaults to false.
         * @param string $url the merchant calculations callback url
         *
         * @return void
         *
         * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_merchant-calculations <merchant-calculations>}
         */
        public function SetMerchantCalculations(string $url, bool $tax_option = false, bool $coupons = false, bool $gift_cert = false): void
        {
            $this->merchant_calculations_url = $url;
            $this->merchant_calculated_tax = $this->_GetBooleanValue($tax_option, false);
            $this->accept_merchant_coupons = $this->_GetBooleanValue($coupons, false);
            $this->accept_gift_certificates = $this->_GetBooleanValue($gift_cert, false);
        }

        /**
         * Add an item to the cart.
         *
         * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_item <item>}
         *
         * @since 1.7
         *
         * @param MspItem $google_item an object that represents an item (defined in googleitem.php)
         *
         * @return void
         */
        public function AddItem(MspItem $google_item): void
        {
            $this->item_arr[] = $google_item;
        }

        /**
         * Add a shipping method to the cart.
         *
         * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_shipping-methods <shipping-methods>}
         *
         * @since 1.7
         *
         * @param object $ship an object that represents a shipping method, must be one of the methods defined in googleshipping.php
         *
         * @return void
         */
        public function AddShipping(object $ship): void
        {
            $this->shipping_arr[] = $ship;
        }

        /**
         * Add a default tax rule to the cart.
         *
         * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_default-tax-rule <default-tax-rule>}
         *
         * @since 1.7
         *
         * @param GoogleDefaultTaxRule $rules an object that represents a default tax rule (defined in googletax.php)
         *
         * @return void
         */
        public function AddDefaultTaxRules(GoogleDefaultTaxRule $rules): void
        {
            $this->default_tax_table = true;
            $this->default_tax_rules_arr[] = $rules;
        }

        /**
         * Add an alternate tax table to the cart.
         *
         * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_alternate-tax-table <alternate-tax-table>}
         *
         * @since 1.7
         *
         * @param MspAlternateTaxTable $tax an object that represents an alternate tax table (defined in googletax.php)
         *
         * @return void
         */
        public function AddAlternateTaxTables(MspAlternateTaxTable $tax): void
        {
            $this->alternate_tax_tables_arr[] = $tax;
        }

        /**
         * Set the policy to be used to round monetary values.
         * Rounding policy explanation here:
         * {@link http://code.google.com/apis/checkout/developer/Google_Checkout_Rounding_Policy.html}
         *
         * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_rounding-policy <rounding-policy>}
         *
         * @since 1.7
         *
         * @param string $rule one of "PER_LINE", "TOTAL"
         *
         * @param string $mode one of "UP", "DOWN", "CEILING", "HALF_DOWN"
         *                      or "HALF_EVEN", described here: {@link http://java.sun.com/j2se/1.5.0/docs/api/java/math/RoundingMode.html}
         *
         * @return void
         */
        public function AddRoundingPolicy(string $mode, string $rule): void
        {
            switch ($mode)
            {
                case 'UP':
                case 'DOWN':
                case 'CEILING':
                case 'HALF_UP':
                case 'HALF_DOWN':
                case 'HALF_EVEN':
                    $this->rounding_mode = $mode;
                    break;
                default:
                    break;
            }
            switch ($rule)
            {
                case 'PER_ITEM':
                case 'PER_LINE':
                case 'TOTAL':
                    $this->rounding_rule = $rule;
                    break;
                default:
                    break;
            }
        }

        /**
         * Set the google analytics data.
         *
         * {@link http://code.google.com/apis/checkout/developer/checkout_analytics_integration.html info on Checkout and Analytics integration}
         *
         * @since 1.7
         *
         * @param string $data the analytics data
         *
         * @return void
         */
        public function SetAnalyticsData(string $data): void
        {
            $this->analytics_data = $data;
        }

        /**
         * Add a Google Analytics tracking id.
         *
         * {@link http://code.google.com/apis/checkout/developer/checkout_analytics_integration.html info on Checkout and Analytics integration}
         *
         * @since 1.7
         *
         * @param string $GA_id the Google Analytics id
         *
         * @return void
         */
        public function AddGoogleAnalyticsTracking(string $GA_id): void
        {
            $this->googleAnalytics_id = $GA_id;
        }

        /**
         * Add third-party tracking to the cart
         *
         * Described here:
         * {@link http://code.google.com/apis/checkout/developer/checkout_analytics_integration.html#googleCheckoutAnalyticsIntegrationAlternate}
         *
         * @since 1.7
         *
         * @param array $tracking_param_types
         *                        To be tracked, one of
         *                             ('buyer-id',
         *                             'order-id',
         *                             'order-subtotal',
         *                             'order-subtotal-plus-tax',
         *                             'order-subtotal-plus-shipping',
         *                             'order-total',
         *                             'tax-amount',
         *                             'shipping-amount',
         *                             'coupon-amount',
         *                             'coupon-amount',
         *                             'billing-city',
         *                             'billing-region',
         *                             'billing-postal-code',
         *                             'billing-country-code',
         *                             'shipping-city',
         *                             'shipping-region',
         *                             'shipping-postal-code',
         *                             'shipping-country-code')
         * More info http://code.google.com/apis/checkout/developer/checkout_pixel_tracking.html#googleCheckout_tag_url-parameter
         */
        public function AddThirdPartyTracking(string $url, array $tracking_param_types = []): void
        {
            $this->thirdPartyTackingUrl = $url;
            $this->thirdPartyTackingParams = $tracking_param_types;
        }

        /**
         * Builds the cart's xml to be sent to Google Checkout.
         *
         * @return string|null the cart's xml
         * @since 1.7
         */
        public function GetXML(): ?string
        {
            $xml_data = new msp_gc_XmlBuilder();
            $xml_data->xml = '';

            $xml_data->Push('checkout-shopping-cart', ['xmlns' => $this->schema_url]);
            $xml_data->Push('shopping-cart');

            // Add cart expiration if set
            if ($this->cart_expiration !== '')
            {
                $xml_data->Push('cart-expiration');
                $xml_data->Element('good-until-date', $this->cart_expiration);
                $xml_data->Pop('cart-expiration');
            }

            // Add XML data for each of the items
            $xml_data->Push('items');
            foreach ($this->item_arr as $item)
            {
                $xml_data->Push('item');
                $xml_data->Element('item-name', $item->item_name);
                $xml_data->Element('item-description', $item->item_description);
                $xml_data->Element('unit-price', $item->unit_price, ['currency' => $this->currency]);
                $xml_data->Element('quantity', $item->quantity);

                if ((string)$item->merchant_private_item_data !== '')
                {
                    if (is_a($item->merchant_private_item_data, 'merchantprivate'))
                    {
                        $item->merchant_private_item_data->AddMerchantPrivateToXML($xml_data);
                    }
                    else
                    {
                        $xml_data->Element('merchant-private-item-data', $item->merchant_private_item_data);
                    }
                }
                if ((string)$item->merchant_item_id !== '')
                {
                    $xml_data->Element('merchant-item-id', $item->merchant_item_id);
                }
                if ((string)$item->tax_table_selector !== '')
                {
                    $xml_data->Element('tax-table-selector', $item->tax_table_selector);
                }

                // Carrier calculation
                if (((string)$item->item_weight !== '') && ((string)$item->numeric_weight !== ''))
                {
                    $xml_data->EmptyElement('item-weight', [
                        'unit' => $item->item_weight,
                        'value' => $item->numeric_weight
                    ]);
                }

                // New Digital Delivery Tags
                if ($item->digital_content)
                {
                    $xml_data->push('digital-content');
                    if (!empty($item->digital_url))
                    {
                        $xml_data->element('description', substr($item->digital_description, 0, MAX_DIGITAL_DESC));
                        $xml_data->element('url', $item->digital_url);
                        // To avoid NULL key message in GC confirmation Page
                        if (!empty($item->digital_key))
                        {
                            $xml_data->element('key', $item->digital_key);
                        }
                    }
                    else
                    {
                        $xml_data->element('email-delivery', $this->_GetBooleanValue($item->email_delivery, true));
                    }
                    $xml_data->pop('digital-content');
                }
                $xml_data->Pop('item');
            }
            $xml_data->Pop('items');

            if ((string)$this->merchant_private_data !== '')
            {
                if (is_a($this->merchant_private_data, 'merchantprivate'))
                {
                    $this->merchant_private_data->AddMerchantPrivateToXML($xml_data);
                }
                else
                {
                    $xml_data->Element('merchant-private-data', $this->merchant_private_data);
                }
            }
            $xml_data->Pop('shopping-cart');

            $xml_data->Push('checkout-flow-support');
            $xml_data->Push('merchant-checkout-flow-support');

            if ($this->edit_cart_url !== '')
            {
                $xml_data->Element('edit-cart-url', $this->edit_cart_url);
            }
            if ($this->continue_shopping_url !== '')
            {
                $xml_data->Element('continue-shopping-url', $this->continue_shopping_url);
            }

            if (count($this->shipping_arr) > 0)
            {
                $xml_data->Push('shipping-methods');
            }

            // Add the shipping methods
            foreach ($this->shipping_arr as $ship)
            {
                // Pickup shipping handled in else part
                if (((string)$ship->type === 'flat-rate-shipping') || ((string)$ship->type === 'merchant-calculated-shipping'))
                {
                    $xml_data->Push($ship->type, ['name' => $ship->name]);
                    $xml_data->Element('price', $ship->price, ['currency' => $this->currency]);

                    $shipping_restrictions = $ship->shipping_restrictions;
                    if (isset($shipping_restrictions))
                    {
                        $xml_data->Push('shipping-restrictions');

                        if ((bool)$shipping_restrictions->allow_us_po_box === true)
                        {
                            $xml_data->Element('allow-us-po-box', "true");
                        }
                        else
                        {
                            $xml_data->Element('allow-us-po-box', "false");
                        }

                        //Check if allowed restrictions specified
                        if ($shipping_restrictions->allowed_restrictions)
                        {
                            $xml_data->Push('allowed-areas');
                            if ((string)$shipping_restrictions->allowed_country_area !== '')
                            {
                                $xml_data->EmptyElement('us-country-area', ['country-area' => $shipping_restrictions->allowed_country_area]);
                            }
                            foreach ($shipping_restrictions->allowed_state_areas_arr as $current)
                            {
                                $xml_data->Push('us-state-area');
                                $xml_data->Element('state', $current);
                                $xml_data->Pop('us-state-area');
                            }
                            foreach ($shipping_restrictions->allowed_zip_patterns_arr as $current)
                            {
                                $xml_data->Push('us-zip-area');
                                $xml_data->Element('zip-pattern', $current);
                                $xml_data->Pop('us-zip-area');
                            }
                            if ((bool)$shipping_restrictions->allowed_world_area === true)
                            {
                                $xml_data->EmptyElement('world-area');
                            }
                            for ($i = 0, $iMax = count($shipping_restrictions->allowed_country_codes_arr); $i < $iMax; $i++)
                            {
                                $xml_data->Push('postal-area');
                                $country_code = $shipping_restrictions->allowed_country_codes_arr[$i];
                                $postal_pattern = $shipping_restrictions->allowed_postal_patterns_arr[$i];
                                $xml_data->Element('country-code', $country_code);
                                if ((string)$postal_pattern !== '')
                                {
                                    $xml_data->Element('postal-code-pattern', $postal_pattern);
                                }
                                $xml_data->Pop('postal-area');
                            }
                            $xml_data->Pop('allowed-areas');
                        }

                        if ($shipping_restrictions->excluded_restrictions)
                        {
                            if (!$shipping_restrictions->allowed_restrictions)
                            {
                                $xml_data->EmptyElement('allowed-areas');
                            }
                            $xml_data->Push('excluded-areas');
                            if ((string)$shipping_restrictions->excluded_country_area !== '')
                            {
                                $xml_data->EmptyElement('us-country-area', ['country-area' => $shipping_restrictions->excluded_country_area]);
                            }
                            foreach ($shipping_restrictions->excluded_state_areas_arr as $current)
                            {
                                $xml_data->Push('us-state-area');
                                $xml_data->Element('state', $current);
                                $xml_data->Pop('us-state-area');
                            }
                            foreach ($shipping_restrictions->excluded_zip_patterns_arr as $current)
                            {
                                $xml_data->Push('us-zip-area');
                                $xml_data->Element('zip-pattern', $current);
                                $xml_data->Pop('us-zip-area');
                            }
                            for ($i = 0, $iMax = count($shipping_restrictions->excluded_country_codes_arr); $i < $iMax; $i++)
                            {
                                $xml_data->Push('postal-area');
                                $country_code = $shipping_restrictions->excluded_country_codes_arr[$i];
                                $postal_pattern = $shipping_restrictions->excluded_postal_patterns_arr[$i];
                                $xml_data->Element('country-code', $country_code);
                                if ((string)$postal_pattern !== '')
                                {
                                    $xml_data->Element('postal-code-pattern', $postal_pattern);
                                }
                                $xml_data->Pop('postal-area');
                            }
                            $xml_data->Pop('excluded-areas');
                        }
                        $xml_data->Pop('shipping-restrictions');
                    }

                    if ((string)$ship->type === 'merchant-calculated-shipping')
                    {
                        $address_filters = $ship->address_filters;
                        if (isset($address_filters))
                        {
                            $xml_data->Push('address-filters');

                            if ((bool)$address_filters->allow_us_po_box === true)
                            {
                                $xml_data->Element('allow-us-po-box', "true");
                            }
                            else
                            {
                                $xml_data->Element('allow-us-po-box', "false");
                            }

                            // Check if allowed restrictions specified
                            if ($address_filters->allowed_restrictions)
                            {
                                $xml_data->Push('allowed-areas');
                                if ((string)$address_filters->allowed_country_area !== '')
                                {
                                    $xml_data->EmptyElement('us-country-area', ['country-area' => $address_filters->allowed_country_area]);
                                }
                                foreach ($address_filters->allowed_state_areas_arr as $current)
                                {
                                    $xml_data->Push('us-state-area');
                                    $xml_data->Element('state', $current);
                                    $xml_data->Pop('us-state-area');
                                }
                                foreach ($address_filters->allowed_zip_patterns_arr as $current)
                                {
                                    $xml_data->Push('us-zip-area');
                                    $xml_data->Element('zip-pattern', $current);
                                    $xml_data->Pop('us-zip-area');
                                }
                                if ((bool)$address_filters->allowed_world_area === true)
                                {
                                    $xml_data->EmptyElement('world-area');
                                }
                                for ($i = 0, $iMax = count($address_filters->allowed_country_codes_arr); $i < $iMax; $i++)
                                {
                                    $xml_data->Push('postal-area');
                                    $country_code = $address_filters->allowed_country_codes_arr[$i];
                                    $postal_pattern = $address_filters->allowed_postal_patterns_arr[$i];
                                    $xml_data->Element('country-code', $country_code);
                                    if ((string)$postal_pattern !== '')
                                    {
                                        $xml_data->Element('postal-code-pattern', $postal_pattern);
                                    }
                                    $xml_data->Pop('postal-area');
                                }
                                $xml_data->Pop('allowed-areas');
                            }

                            if ($address_filters->excluded_restrictions)
                            {
                                if (!$address_filters->allowed_restrictions)
                                {
                                    $xml_data->EmptyElement('allowed-areas');
                                }
                                $xml_data->Push('excluded-areas');
                                if ((string)$address_filters->excluded_country_area !== '')
                                {
                                    $xml_data->EmptyElement('us-country-area', ['country-area' => $address_filters->excluded_country_area]);
                                }
                                foreach ($address_filters->excluded_state_areas_arr as $current)
                                {
                                    $xml_data->Push('us-state-area');
                                    $xml_data->Element('state', $current);
                                    $xml_data->Pop('us-state-area');
                                }
                                foreach ($address_filters->excluded_zip_patterns_arr as $current)
                                {
                                    $xml_data->Push('us-zip-area');
                                    $xml_data->Element('zip-pattern', $current);
                                    $xml_data->Pop('us-zip-area');
                                }
                                for ($i = 0, $iMax = count($address_filters->excluded_country_codes_arr); $i < $iMax; $i++)
                                {
                                    $xml_data->Push('postal-area');
                                    $country_code = $address_filters->excluded_country_codes_arr[$i];
                                    $postal_pattern = $address_filters->excluded_postal_patterns_arr[$i];
                                    $xml_data->Element('country-code', $country_code);
                                    if ((string)$postal_pattern !== '')
                                    {
                                        $xml_data->Element('postal-code-pattern', $postal_pattern);
                                    }
                                    $xml_data->Pop('postal-area');
                                }
                                $xml_data->Pop('excluded-areas');
                            }
                            $xml_data->Pop('address-filters');
                        }
                    }
                    $xml_data->Pop($ship->type);
                }
                elseif ((string)$ship->type === 'carrier-calculated-shipping')
                {
                    $xml_data->Push($ship->type);
                    $xml_data->Push('carrier-calculated-shipping-options');
                    $CCSoptions = $ship->CarrierCalculatedShippingOptions;
                    foreach ($CCSoptions as $CCSoption)
                    {
                        $xml_data->Push('carrier-calculated-shipping-option');
                        $xml_data->Element('price', $CCSoption->price, ['currency' => $this->currency]);
                        $xml_data->Element('shipping-company', $CCSoption->shipping_company);
                        $xml_data->Element('shipping-type', $CCSoption->shipping_type);
                        $xml_data->Element('carrier-pickup', $CCSoption->carrier_pickup);
                        if (!empty($CCSoption->additional_fixed_charge))
                        {
                            $xml_data->Element('additional-fixed-charge', $CCSoption->additional_fixed_charge, ['currency' => $this->currency]);
                        }
                        if (!empty($CCSoption->additional_variable_charge_percent))
                        {
                            $xml_data->Element('additional-variable-charge-percent', $CCSoption->additional_variable_charge_percent);
                        }
                        $xml_data->Pop('carrier-calculated-shipping-option');
                    }
                    $xml_data->Pop('carrier-calculated-shipping-options');
                    $xml_data->Push('shipping-packages');
                    $xml_data->Push('shipping-package');
                    $xml_data->Push('ship-from', ['id' => $ship->ShippingPackage->ship_from->id]);
                    $xml_data->Element('city', $ship->ShippingPackage->ship_from->city);
                    $xml_data->Element('region', $ship->ShippingPackage->ship_from->region);
                    $xml_data->Element('postal-code', $ship->ShippingPackage->ship_from->postal_code);
                    $xml_data->Element('country-code', $ship->ShippingPackage->ship_from->country_code);
                    $xml_data->Pop('ship-from');

                    $xml_data->EmptyElement('width', [
                        'unit' => $ship->ShippingPackage->unit,
                        'value' => $ship->ShippingPackage->width
                    ]);
                    $xml_data->EmptyElement('length', [
                        'unit' => $ship->ShippingPackage->unit,
                        'value' => $ship->ShippingPackage->length
                    ]);
                    $xml_data->EmptyElement('height', [
                        'unit' => $ship->ShippingPackage->unit,
                        'value' => $ship->ShippingPackage->height
                    ]);
                    $xml_data->Element('delivery-address-category', $ship->ShippingPackage->delivery_address_category);
                    $xml_data->Pop('shipping-package');
                    $xml_data->Pop('shipping-packages');

                    $xml_data->Pop($ship->type);
                }
                elseif ((string)$ship->type === 'pickup')
                {
                    $xml_data->Push('pickup', ['name' => $ship->name]);
                    $xml_data->Element('price', $ship->price, ['currency' => $this->currency]);
                    $xml_data->Pop('pickup');
                }
            }
            if (count($this->shipping_arr) > 0)
            {
                $xml_data->Pop('shipping-methods');
            }

            if ($this->request_buyer_phone !== '')
            {
                $xml_data->Element('request-buyer-phone-number', $this->request_buyer_phone);
            }

            if ($this->merchant_calculations_url !== '')
            {
                $xml_data->Push('merchant-calculations');
                $xml_data->Element('merchant-calculations-url', $this->merchant_calculations_url);
                if ($this->accept_merchant_coupons !== '')
                {
                    $xml_data->Element('accept-merchant-coupons', $this->accept_merchant_coupons);
                }
                if ($this->accept_gift_certificates !== '')
                {
                    $xml_data->Element('accept-gift-certificates', $this->accept_gift_certificates);
                }
                $xml_data->Pop('merchant-calculations');
            }
            // Set Third party Tracking
            if ($this->thirdPartyTackingUrl)
            {
                $xml_data->push('parameterized-urls');
                $xml_data->push('parameterized-url', ['url' => $this->thirdPartyTackingUrl]);
                if (count($this->thirdPartyTackingParams) > 0)
                {
                    $xml_data->push('parameters');
                    foreach ($this->thirdPartyTackingParams as $tracking_param_name => $tracking_param_type)
                    {
                        $xml_data->emptyElement('url-parameter', [
                            'name' => $tracking_param_name,
                            'type' => $tracking_param_type
                        ]);
                    }
                    $xml_data->pop('parameters');
                }
                $xml_data->pop('parameterized-url');
                $xml_data->pop('parameterized-urls');
            }

            // Set Default and Alternate tax tables
            if ((count($this->alternate_tax_tables_arr) !== 0) || (count($this->default_tax_rules_arr) !== 0))
            {
                if ($this->merchant_calculated_tax !== '')
                {
                    $xml_data->Push('tax-tables', ['merchant-calculated' => $this->merchant_calculated_tax]);
                }
                else
                {
                    $xml_data->Push('tax-tables');
                }
                if (count($this->default_tax_rules_arr) !== 0)
                {
                    $xml_data->Push('default-tax-table');
                    $xml_data->Push('tax-rules');
                    foreach ($this->default_tax_rules_arr as $curr_rule)
                    {
                        $rule_added = false;
                        if ((string)$curr_rule->country_area !== '')
                        {
                            $xml_data->Push('default-tax-rule');
                            $xml_data->Element('shipping-taxed', $curr_rule->shipping_taxed);
                            $xml_data->Element('rate', $curr_rule->tax_rate);
                            $xml_data->Push('tax-area');
                            $xml_data->EmptyElement('us-country-area', ['country-area' => $curr_rule->country_area]);
                            $xml_data->Pop('tax-area');
                            $xml_data->Pop('default-tax-rule');
                            $rule_added = true;
                        }

                        foreach ($curr_rule->state_areas_arr as $current)
                        {
                            $xml_data->Push('default-tax-rule');
                            $xml_data->Element('shipping-taxed', $curr_rule->shipping_taxed);
                            $xml_data->Element('rate', $curr_rule->tax_rate);
                            $xml_data->Push('tax-area');
                            $xml_data->Push('us-state-area');
                            $xml_data->Element('state', $current);
                            $xml_data->Pop('us-state-area');
                            $xml_data->Pop('tax-area');
                            $xml_data->Pop('default-tax-rule');
                            $rule_added = true;
                        }

                        foreach ($curr_rule->zip_patterns_arr as $current)
                        {
                            $xml_data->Push('default-tax-rule');
                            $xml_data->Element('shipping-taxed', $curr_rule->shipping_taxed);
                            $xml_data->Element('rate', $curr_rule->tax_rate);
                            $xml_data->Push('tax-area');
                            $xml_data->Push('us-zip-area');
                            $xml_data->Element('zip-pattern', $current);
                            $xml_data->Pop('us-zip-area');
                            $xml_data->Pop('tax-area');
                            $xml_data->Pop('default-tax-rule');
                            $rule_added = true;
                        }

                        for ($i = 0, $iMax = count($curr_rule->country_codes_arr); $i < $iMax; $i++)
                        {
                            $xml_data->Push('default-tax-rule');
                            $xml_data->Element('shipping-taxed', $curr_rule->shipping_taxed);
                            $xml_data->Element('rate', $curr_rule->tax_rate);
                            $xml_data->Push('tax-area');
                            $xml_data->Push('postal-area');
                            $country_code = $curr_rule->country_codes_arr[$i];
                            $postal_pattern = $curr_rule->postal_patterns_arr[$i];
                            $xml_data->Element('country-code', $country_code);
                            if ((string)$postal_pattern !== '')
                            {
                                $xml_data->Element('postal-code-pattern', $postal_pattern);
                            }
                            $xml_data->Pop('postal-area');
                            $xml_data->Pop('tax-area');
                            $xml_data->Pop('default-tax-rule');
                            $rule_added = true;
                        }

                        if ((bool)$curr_rule->world_area === true)
                        {
                            $xml_data->Push('default-tax-rule');
                            $xml_data->Element('shipping-taxed', $curr_rule->shipping_taxed);
                            $xml_data->Element('rate', $curr_rule->tax_rate);
                            $xml_data->Push('tax-area');
                            $xml_data->EmptyElement('world-area');
                            $xml_data->Pop('tax-area');
                            $xml_data->Pop('default-tax-rule');
                            $rule_added = true;
                        }

                        // Msp add
                        if (!$rule_added)
                        {
                            $xml_data->Push('default-tax-rule');
                            $xml_data->Element('shipping-taxed', $curr_rule->shipping_taxed);
                            $xml_data->Element('rate', $curr_rule->tax_rate);
                            $xml_data->Pop('default-tax-rule');
                        }
                        // Msp end
                    }
                    $xml_data->Pop('tax-rules');
                    $xml_data->Pop('default-tax-table');
                }

                if (count($this->alternate_tax_tables_arr) !== 0)
                {
                    $xml_data->Push('alternate-tax-tables');
                    foreach ($this->alternate_tax_tables_arr as $curr_table)
                    {
                        $xml_data->Push('alternate-tax-table', [
                            'standalone' => $curr_table->standalone,
                            'name' => $curr_table->name
                        ]);
                        $xml_data->Push('alternate-tax-rules');
                        $rule_added = false;
                        foreach ($curr_table->tax_rules_arr as $curr_rule)
                        {
                            if ((string)$curr_rule->country_area !== '')
                            {
                                $xml_data->Push('alternate-tax-rule');
                                $xml_data->Element('rate', $curr_rule->tax_rate);
                                $xml_data->Push('tax-area');
                                $xml_data->EmptyElement('us-country-area', ['country-area' => $curr_rule->country_area]);
                                $xml_data->Pop('tax-area');
                                $xml_data->Pop('alternate-tax-rule');
                                $rule_added = true;
                            }

                            foreach ($curr_rule->state_areas_arr as $current)
                            {
                                $xml_data->Push('alternate-tax-rule');
                                $xml_data->Element('rate', $curr_rule->tax_rate);
                                $xml_data->Push('tax-area');
                                $xml_data->Push('us-state-area');
                                $xml_data->Element('state', $current);
                                $xml_data->Pop('us-state-area');
                                $xml_data->Pop('tax-area');
                                $xml_data->Pop('alternate-tax-rule');
                                $rule_added = true;
                            }

                            foreach ($curr_rule->zip_patterns_arr as $current)
                            {
                                $xml_data->Push('alternate-tax-rule');
                                $xml_data->Element('rate', $curr_rule->tax_rate);
                                $xml_data->Push('tax-area');
                                $xml_data->Push('us-zip-area');
                                $xml_data->Element('zip-pattern', $current);
                                $xml_data->Pop('us-zip-area');
                                $xml_data->Pop('tax-area');
                                $xml_data->Pop('alternate-tax-rule');
                                $rule_added = true;
                            }

                            for ($i = 0, $iMax = count($curr_rule->country_codes_arr); $i < $iMax; $i++)
                            {
                                $xml_data->Push('alternate-tax-rule');
                                $xml_data->Element('rate', $curr_rule->tax_rate);
                                $xml_data->Push('tax-area');
                                $xml_data->Push('postal-area');
                                $country_code = $curr_rule->country_codes_arr[$i];
                                $postal_pattern = $curr_rule->postal_patterns_arr[$i];
                                $xml_data->Element('country-code', $country_code);
                                if ((string)$postal_pattern !== '')
                                {
                                    $xml_data->Element('postal-code-pattern', $postal_pattern);
                                }
                                $xml_data->Pop('postal-area');
                                $xml_data->Pop('tax-area');
                                $xml_data->Pop('alternate-tax-rule');
                                $rule_added = true;
                            }

                            if ((bool)$curr_rule->world_area === true)
                            {
                                $xml_data->Push('alternate-tax-rule');
                                $xml_data->Element('rate', $curr_rule->tax_rate);
                                $xml_data->Push('tax-area');
                                $xml_data->EmptyElement('world-area');
                                $xml_data->Pop('tax-area');
                                $xml_data->Pop('alternate-tax-rule');
                                $rule_added = true;
                            }

                            // Msp add
                            if (!$rule_added)
                            {
                                $xml_data->Push('alternate-tax-rule');
                                $xml_data->Element('rate', $curr_rule->tax_rate);
                                $xml_data->Pop('alternate-tax-rule');
                            }
                            // Msp end
                        }
                        $xml_data->Pop('alternate-tax-rules');
                        $xml_data->Pop('alternate-tax-table');
                    }
                    $xml_data->Pop('alternate-tax-tables');
                }
                $xml_data->Pop('tax-tables');
            }

            if (($this->rounding_mode !== '') || ($this->rounding_rule !== ''))
            {
                $xml_data->Push('rounding-policy');
                if ($this->rounding_mode !== '')
                {
                    $xml_data->Element('mode', $this->rounding_mode);
                }
                if ($this->rounding_rule !== '')
                {
                    $xml_data->Element('rule', $this->rounding_rule);
                }
                $xml_data->Pop('rounding-policy');
            }
            if ($this->analytics_data !== '')
            {
                $xml_data->Element('analytics-data', $this->analytics_data);
            }

            $xml_data->Pop('merchant-checkout-flow-support');
            $xml_data->Pop('checkout-flow-support');
            $xml_data->Pop('checkout-shopping-cart');

            return $xml_data->GetXML();
        }

        /**
         * Set the Google Checkout button's variant
         *
         * @since 1.7
         *
         * @param bool $variant true for an enabled button, false for a disabled one
         *
         * @return void
         * {@link http://code.google.com/apis/checkout/developer/index.html#google_checkout_buttons}
         *
         */
        public function SetButtonVariant(bool $variant): void
        {
            switch ($variant)
            {
                case false:
                    $this->variant = 'disabled';
                    break;
                case true:
                default:
                    $this->variant = 'text';
                    break;
            }
        }

        /**
         * Submit a server-to-server request.
         * Creates a GoogleRequest object (defined in googlerequest.php) and sends
         * it to the Google Checkout server.
         *
         * @since 1.7
         *
         * More info:
         * {@link http://code.google.com/apis/checkout/developer/index.html#alternate_technique}
         *
         * @return array with the returned http status code (200 if OK) in index 0
         *               and the redirect url returned by the server in index 1
         */
        public function CheckoutServer2Server($proxy = [], $certPath = ''): array
        {
            ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR . '.');
            require_once('library/googlerequest.php');
            $GRequest = new GoogleRequest($this->merchant_id, $this->merchant_key, $this->server_url === 'https://checkout.google.com/' ? 'Production' : 'sandbox', $this->currency);
            $GRequest->SetProxy($proxy);
            $GRequest->SetCertificatePath($certPath);

            return $GRequest->SendServer2ServerCart($this->GetXML());
        }

        /**
         * Get the Google Checkout button's html to be used in a server-to-server request.
         *
         * @since 1.7
         *
         * @param string $url the merchant's site url where the form will be posted to
         * @param string $size the size of the button, one of 'large', 'medium' or 'small'.
         *                     defaults to 'large'
         * @param bool $variant true for an enabled button, false for a disabled one. defaults to true.
         *                      will be ignored if SetButtonVariant() was used before.
         * @param string $loc the locale of the button's text, the only valid value is 'en_US' (used as default)
         * @param bool $showtext whether to show Google Checkout text or not, defaults to true.
         * @param string $style the background style of the button, one of 'white' or 'trans'. defaults to "trans"
         *
         * @return string the button's html
         *
         * {@link http://code.google.com/apis/checkout/developer/index.html#google_checkout_buttons}
         */
        public function CheckoutServer2ServerButton(string $url, string $size = 'large', bool $variant = true, string $loc = 'en_US', bool $showtext = true, string $style = 'trans'): string
        {
            switch (strtolower($size))
            {
                case 'medium':
                    $width = '168';
                    $height = '44';
                    break;

                case 'small':
                    $width = '160';
                    $height = '43';
                    break;
                case 'large':
                default:
                    $width = '180';
                    $height = '46';
                    break;
            }

            if ((bool)$this->variant === false)
            {
                switch ($variant)
                {
                    case false:
                        $this->variant = 'disabled';
                        break;
                    case true:
                    default:
                        $this->variant = 'text';
                        break;
                }
            }

            $data = '<div style="width: ' . $width . 'px">';
            if ((string)$this->variant === 'text')
            {
                $data .= "<div align=center><form method=\"POST\" action=\"" . $url . "\"" . ($this->googleAnalytics_id ? " onsubmit=\"setUrchinInputCode();\"" : "") . ">
                          <input type=\"image\" name=\"Checkout\" alt=\"Checkout\" src=\"" . $this->server_url . "buttons/checkout.gif?merchant_id=" . $this->merchant_id . "&w=" .
                          $width . "&h=" . $height . "&style=" . $style . "&variant=" . $this->variant . "&loc=" . $loc . "\" height=\"" . $height . "\" width=\"" . $width . "\" />";

                if ($this->googleAnalytics_id)
                {
                    $data .= '<input type="hidden" name="analyticsdata" value="">';
                }
                $data .= '</form></div>';

                if ($this->googleAnalytics_id)
                {
                    $data .= "<!-- Start Google Analytics -->
                              <script src=\"https://ssl.google-analytics.com/urchin.js\" type=\"text/javascript\"></script>
                              <script type=\"text/javascript\">_uacct = \"" . $this->googleAnalytics_id . "\";urchinTracker();</script>
                              <script src=\"https://checkout.google.com/files/digital/urchin_post.js\" type=\"text/javascript\"></script>
                              <!-- End Google Analytics -->";
                }
            }
            else
            {
                $data .= "<div><img alt=\"Checkout\" src=\"" . $this->server_url . "buttons/checkout.gif?merchant_id=" . $this->merchant_id . "&w=" . $width . "&h=" . $height .
                         "&style=" . $style . "&variant=" . $this->variant . "&loc=" . $loc . "\" height=\"" . $height . "\"" . " width=\"" . $width . "\" /></div>";
            }
            $data .= '</div>';

            return $data;
        }

        /**
         * Get the Google Checkout button's html.
         *
         * @since 1.7
         *
         * @param bool $variant true for an enabled button, false for a disabled one. defaults to true.
         *                      will be ignored if SetButtonVariant() was used before.
         * @param string $loc the locale of the button's text, the only valid value
         *                    is 'en_US' (used as default)
         * @param bool $showtext whether to show Google Checkout text or not,
         *                       defaults to true.
         * @param string $style the background style of the button, one of 'white'
         *                      or 'trans'. defaults to "trans"
         *
         * @param string $size the size of the button, one of 'large', 'medium' or 'small'.
         *                     defaults to 'large'
         *
         * @return string the button's html
         *
         * {@link http://code.google.com/apis/checkout/developer/index.html#google_checkout_buttons}
         *
         */
        public function CheckoutButtonCode(string $size = 'large', bool $variant = true, string $loc = 'en_US', bool $showtext = true, string $style = 'trans'): string
        {
            switch (strtolower($size))
            {
                case 'medium':
                    $width = '168';
                    $height = '44';
                    break;

                case 'small':
                    $width = '160';
                    $height = '43';
                    break;
                case 'large':
                default:
                    $width = '180';
                    $height = '46';
                    break;
            }

            if ((bool)$this->variant === false)
            {
                switch ($variant)
                {
                    case false:
                        $this->variant = 'disabled';
                        break;
                    case true:
                    default:
                        $this->variant = 'text';
                        break;
                }
            }

            $data = '<div style="width: ' . $width . 'px">';
            if ((string)$this->variant === 'text')
            {
                $data .= "<div align=center><form method=\"POST\" action=\"" . $this->checkout_url . "\"" . ($this->googleAnalytics_id ? " onsubmit=\"setUrchinInputCode();\"" : "") . ">
                          <input type=\"hidden\" name=\"cart\" value=\"" . base64_encode($this->GetXML()) . "\">
                          <input type=\"hidden\" name=\"signature\" value=\"" . base64_encode($this->CalcHmacSha1($this->GetXML())) . "\">
                          <input type=\"image\" name=\"Checkout\" alt=\"Checkout\" src=\"" . $this->server_url . "buttons/checkout.gif?merchant_id=" . $this->merchant_id . "&w=" . $width .
                          "&h=" . $height . "&style=" . $style . "&variant=" . $this->variant . "&loc=" . $loc . "\" height=\"" . $height . "\" width=\"" . $width . "\" />";

                if ($this->googleAnalytics_id)
                {
                    $data .= "<input type=\"hidden\" name=\"analyticsdata\" value=\"\">";
                }
                $data .= "</form></div>";
                if ($this->googleAnalytics_id)
                {
                    $data .= "<!-- Start Google analytics -->
                              <script src=\"https://ssl.google-analytics.com/urchin.js\" type=\"" . "text/javascript\"></script>
                              <script type=\"text/javascript\">_uacct = \"" . $this->googleAnalytics_id . "\";urchinTracker();</script>
                              <script src=\"https://checkout.google.com/files/digital/urchin_post.js\" type=\"text/javascript\"></script>
                              <!-- End Google Analytics -->";
                }
            }
            else
            {
                $data .= "<div><img alt=\"Checkout\" src=\"" . $this->server_url . "buttons/checkout.gif?merchant_id=" . $this->merchant_id . "&w=" . $width . "&h=" . $height . "&style=" .
                         $style . "&variant=" . $this->variant . "&loc=" . $loc . "\" height=\"" . $height . "\"" . " width=\"" . $width . "\" /></div>";
            }
            if ($showtext)
            {
                $data .= "<div align=\"center\"><a href=\"javascript:void(window.open('http://checkout.google.com/seller/what_is_google_checkout.html'" .
                         ",'whatischeckout','scrollbars=0,resizable=1,directories=0,height=250,width=400'));\" onmouseover=\"return window.status='What is Google Checkout?'\"
                         onmouseout=\"return window.status = ''\"><font " . "size=\"-2\">What is Google Checkout?</font></a></div>";
            }
            $data .= '</div>';

            return $data;
        }

        /**
         * Code for generating Checkout button
         *
         * @since 1.7
         *
         * @param bool $variant will be ignored if SetButtonVariant() was used before
         * @param string $loc
         * @param bool $showtext
         * @param string $style
         *
         * @param string $size
         *
         * @return string
         */
        public function CheckoutButtonNowCode(string $size = 'large', bool $variant = true, string $loc = 'en_US', bool $showtext = true, string $style = 'trans'): string
        {
            switch (strtolower($size))
            {
                case 'small':
                    $width = '121';
                    $height = '44';
                    break;
                case 'large':
                default:
                    $width = '117';
                    $height = '48';
                    break;
            }

            if ((bool)$this->variant === false)
            {
                switch ($variant)
                {
                    case false:
                        $this->variant = 'disabled';
                        break;
                    case true:
                    default:
                        $this->variant = 'text';
                        break;
                }
            }

            $data = '<div style="width: ' . $width . 'px">';
            if ((string)$this->variant === 'text')
            {
                $data .= "<div align=center><form method=\"POST\" action=\"" . $this->checkout_url . "\"" . ($this->googleAnalytics_id ? " onsubmit=\"setUrchinInputCode();\"" : "") . ">
                          <input type=\"hidden\" name=\"buyButtonCart\" value=\"" . base64_encode($this->GetXML()) . "//separator//" . base64_encode($this->CalcHmacSha1($this->GetXML())) . "\">
                          <input type=\"image\" name=\"Checkout\" alt=\"BuyNow\" src=\"" . $this->server_url . "buttons/buy.gif?merchant_id=" . $this->merchant_id . "&w=" . $width . "&h=" .
                          $height . "&style=" . $style . "&variant=" . $this->variant . "&loc=" . $loc . "\" height=\"" . $height . "\" width=\"" . $width . "\" />";

                if ($this->googleAnalytics_id)
                {
                    $data .= "<input type=\"hidden\" name=\"analyticsdata\" value=\"\">";
                }
                $data .= "</form></div>";

                if ($this->googleAnalytics_id)
                {
                    $data .= "<!-- Start Google Analytics -->
                             <script src=\"https://ssl.google-analytics.com/urchin.js\" type=\"text/javascript\"></script>
                             <script type=\"text/javascript\">_uacct = \"" . $this->googleAnalytics_id . "\";urchinTracker();</script>
                             <script src=\"https://checkout.google.com/files/digital/urchin_post.js\" type=\"text/javascript\"></script>
                             <!-- End Google Analytics -->";
                }
                // Ask for link to BuyNow disable button
            }
            else
            {
                $data .= "<div><img alt=\"Checkout\" src=\"" . $this->server_url . "buttons/buy.gif?merchant_id=" . $this->merchant_id . "&w=" . $width . "&h=" . $height . "&style=" . $style .
                         "&variant=" . $this->variant . "&loc=" . $loc . "\" height=\"" . $height . "\"" . " width=\"" . $width . "\" /></div>";
            }
            if ($showtext)
            {
                $data .= "<div align=\"center\"><a href=\"javascript:void(window.open('http://checkout.google.com/seller/what_is_google_checkout.html','whatischeckout','scrollbars=0,resizable=1,directories=0,height=250,width=400'));\"
                          onmouseover=\"return window.status='What is Google Checkout?'\" onmouseout=\"return window.status = ''\"><font size=\"-2\">What is Google Checkout?</font></a></div>";
            }
            $data .= '</div>';

            return $data;
        }

        /**
         * Get the Google Checkout button's html to be used with the html api.
         *
         * @since 1.7
         *
         * {@link http://code.google.com/apis/checkout/developer/index.html#google_checkout_buttons}
         *
         * @param bool $variant true for an enabled button, false for a disabled one. defaults to true. will be ignored if
         *                      SetButtonVariant() was used before.
         * @param string $loc the locale of the button's text, the only valid value is 'en_US' (used as default)
         * @param bool $showtext whether to show Google Checkout text or not. Defaults to true.
         * @param string $style the background style of the button, one of 'white' or 'trans'. defaults to "trans"
         *
         * @param string $size the size of the button, one of 'large', 'medium' or 'small'. Defaults to 'large'
         *
         * @return string the button's html
         */
        public function CheckoutHTMLButtonCode(string $size = 'large', bool $variant = true, string $loc = 'en_US', bool $showtext = true, string $style = 'trans'): string
        {
            switch (strtolower($size))
            {
                case 'medium':
                    $width = '168';
                    $height = '44';
                    break;

                case 'small':
                    $width = '160';
                    $height = '43';
                    break;
                case 'large':
                default:
                    $width = '180';
                    $height = '46';
                    break;
            }

            if ((bool)$this->variant === false)
            {
                switch ($variant)
                {
                    case false:
                        $this->variant = 'disabled';
                        break;
                    case true:
                    default:
                        $this->variant = 'text';
                        break;
                }
            }

            $data = '<div style="width: ' . $width . 'px">';
            if ((string)$this->variant === 'text')
            {
                $data .= "<div align=\"center\"><form method=\"POST\" action=\"" . $this->checkoutForm_url . "\"" . ($this->googleAnalytics_id ? " onsubmit=\"setUrchinInputCode();\"" : "") . ">";

                $request = $this->GetXML();
                require_once('xml-processing/gc_xmlparser.php');
                $xml_parser = new gc_xmlparser($request);
                $root = $xml_parser->GetRoot();
                $XMLdata = $xml_parser->GetData();
                $this->xml2html($XMLdata[$root], '', $data);
                $data .= "<input type=\"image\" name=\"Checkout\" alt=\"Checkout\" " . "src=\"" . $this->server_url . "buttons/checkout.gif?merchant_id=" . $this->merchant_id . "&w=" . $width .
                         "&h=" . $height . "&style=" . $style . "&variant=" . $this->variant . "&loc=" . $loc . "\" height=\"" . $height . "\" width=\"" . $width . "\" />";

                if ($this->googleAnalytics_id)
                {
                    $data .= '<input type="hidden" name="analyticsdata" value="">';
                }
                $data .= "</form></div>";
                if ($this->googleAnalytics_id)
                {
                    $data .= "<!-- Start Google Analytics -->
                             <script src=\"https://ssl.google-analytics.com/urchin.js\" type=\"text/javascript\"></script>
                             <script type=\"text/javascript\">_uacct = \"" . $this->googleAnalytics_id . "\";urchinTracker();</script>
                             <script src=\"https://checkout.google.com/files/digital/urchin_post.js\" type=\"text/javascript\"></script>
                             <!-- End Google Analytics -->";
                }
            }
            else
            {
                $data .= "<div align=\"center\"><img alt=\"Checkout\" src=\"" . $this->server_url . "buttons/checkout.gif?merchant_id=" . $this->merchant_id . "&w=" . $width . "&h=" . $height . "&style=" .
                          $style . "&variant=" . $this->variant . "&loc=" . $loc . "\" height=\"" . $height . "\"" . " width=\"" . $width . "\" /></div>";
            }
            if ($showtext)
            {
                $data .= "<div align=\"center\"><a href=\"javascript:void(window.open('http://checkout.google.com/seller/what_is_google_checkout.html','whatischeckout','scrollbars=0,resizable=1,directories=0,height=250,width=400'));\"
                          onmouseover=\"return window.status = 'What is Google Checkout?'\" onmouseout=\"return window.status = ''\"><font size=\"-2\">What is Google Checkout?</font></a></div>";
            }
            $data .= '</div>';

            return $data;
        }

        /**
         * Converts an XML array to HTML
         *
         * @since 1.7
         *
         * @access private
         *
         * @param string $path
         * @param string $rta
         *
         * @param array $data
         *
         * @return void
         */
        public function xml2html(array $data, string $path = '', string &$rta = ''): void
        {
            foreach ($data as $tag_name => $tag)
            {
                if (isset($this->ignore_tags[$tag_name]))
                {
                    continue;
                }
                if (is_array($tag))
                {
                    if (!$this->is_associative_array($data))
                    {
                        $new_path = $path . '-' . ($tag_name + 1);
                    }
                    else
                    {
                        if (isset($this->multiple_tags[$tag_name]) && $this->is_associative_array($tag) && !$this->isChildOf($path, $this->multiple_tags[$tag_name]))
                        {
                            $tag_name .= '-1';
                        }
                        $new_path = $path . (empty($path) ? '' : '.') . $tag_name;
                    }
                    $this->xml2html($tag, $new_path, $rta);
                }
                else
                {
                    $new_path = $path;
                    if ((string)$tag_name !== 'VALUE')
                    {
                        $new_path = $path . '.' . $tag_name;
                    }
                    $rta .= '<input type="hidden" name="' . $new_path . '" value="' . $tag . '"/>' . "\n";
                }
            }
        }

        /**
         * Returns true if a given variable represents an associative array
         *
         * @since 1.7
         *
         * @access private
         */
        public function is_associative_array($var): bool
        {
            return is_array($var) && !is_numeric(implode('', array_keys($var)));
        }

        /**
         * @since 1.7
         *
         * @access private
         */
        public function isChildOf(string $path = '', array $parents = []): bool
        {
            $intersect = array_intersect(explode('.', $path), $parents);

            return !empty($intersect);
        }

        /**
         * Get the Google Checkout acceptance logos html
         *
         * @since 1.7
         *
         * @param int $type the acceptance logo type, valid values: 1, 2, 3
         *
         * @return string the logo's html
         *
         * {@link http://checkout.google.com/seller/acceptance_logos.html}
         */
        public function CheckoutAcceptanceLogo(int $type = 1): string
        {
            switch ($type)
            {
                case 2:
                    return '<link rel="stylesheet" href="https://checkout.google.com/seller/accept/s.css" type="text/css" media="screen" />
                            <script type="text/javascript" src="https://checkout.google.com/seller/accept/j.js"></script><script type="text/javascript">showMark(1);</script>
                            <noscript><img src="https://checkout.google.com/seller/accept/images/st.gif" width="92" height="88" alt="Google Checkout Acceptance Mark" /></noscript>';
                case 3:
                    return '<link rel="stylesheet" href="https://checkout.google.com/seller/accept/s.css" type="text/css" media="screen" />
                            <script type="text/javascript" src="https://checkout.google.com/seller/accept/j.js"></script><script type="text/javascript">showMark(2);</script>
                            <noscript><img src="https://checkout.google.com/seller/accept/images/ht.gif" width="182" height="44" alt="Google Checkout Acceptance Mark" /></noscript>';
                case 1:
                default:
                    return '<link rel="stylesheet" href="https://checkout.google.com/seller/accept/s.css" type="text/css" media="screen" />
                            <script type="text/javascript" src="https://checkout.google.com/seller/accept/j.js"></script><script type="text/javascript">showMark(3);</script>
                            <noscript><img src="https://checkout.google.com/seller/accept/images/sc.gif" width="72" height="73" alt="Google Checkout Acceptance Mark" /></noscript>';
            }
        }

        /**
         * Calculates the cart's hmac-sha1 signature, this allows Google to verify
         * that the cart hasn't been tampered by a third party
         *
         * @since 1.7
         *
         * @param string $data the cart's xml
         *
         * @return string the cart's signature (in binary format)
         *
         * {@link http://code.google.com/apis/checkout/developer/index.html#create_signature}
         */
        public function CalcHmacSha1(string $data): string
        {
            $key = $this->merchant_key;
            $blocksize = 64;
            $hashfunc = 'sha1';

            if (strlen($key) > $blocksize)
            {
                $key = pack('H*', $hashfunc($key));
            }
            $key = str_pad($key, $blocksize, chr(0x00));
            $ipad = str_repeat(chr(0x36), $blocksize);
            $opad = str_repeat(chr(0x5c), $blocksize);

            return pack(
                'H*', $hashfunc(
                    ($key ^ $opad) . pack(
                        'H*', $hashfunc(
                            ($key ^ $ipad) . $data
                        )
                    )
                )
            );
        }

        /**
         * Method used internally to set true/false cart variables
         *
         * @since 1.7
         *
         * @access private
         */
        public function _GetBooleanValue($value, $default): string
        {
            switch (strtolower($value))
            {
                case 'true':
                    return 'true';
                case 'false':
                    return 'false';
                default:
                    return $default;
            }
        }

        /**
         * Method used internally to set true/false cart variables
         * Deprecated, must NOT use eval, bug-prune function
         *
         * @since 1.7
         *
         * @access private
         *
         * @param string $value
         * @param string $default
         *
         * @param string $string
         *
         * @return void
         */
        public function _SetBooleanValue(string $string, string $value, string $default): void
        {
            if ((strtolower($value) === 'true') || (strtolower($value) === 'false'))
            {
                eval('$this->' . $string . '="' . $value . '";');
            }
            else
            {
                eval('$this->' . $string . '="' . $default . '";');
            }
        }
    }
}

/**
 * @abstract
 * Abstract class that represents the merchant-private-data.
 *
 * See {@link MerchantPrivateData} and {@link MerchantPrivateItemData}
 *
 * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_merchant-private-data <merchant-private-data>}
 */
if (!class_exists('MspMerchantPrivate'))
{
    class MspMerchantPrivate
    {
        public array|string $data;
        public string $type = 'Abstract';

        public function __construct() {}

        public function AddMerchantPrivateToXML(&$xml_data): void
        {
            if (is_array($this->data))
            {
                $xml_data->Push($this->type);
                $this->_recursiveAdd($xml_data, $this->data);
                $xml_data->Pop($this->type);
            }
            else
            {
                $xml_data->Element($this->type, (string)$this->data);
            }
        }

        /**
         * @since 1.7
         *
         * @access private
         */
        public function _recursiveAdd(&$xml_data, array $data = []): void
        {
            foreach ($data as $name => $value)
            {
                if (is_array($value))
                {
                    $xml_data->Push($name);
                    $this->_recursiveAdd($xml_data, (array)$name);
                    $xml_data->Pop($name);
                }
                else
                {
                    $xml_data->Element($name, (string)$value);
                }
            }
        }
    }
}

/**
 * Class that represents the merchant-private-data.
 *
 * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_merchant-private-data <merchant-private-data>}
 */
if (!class_exists('MspMerchantPrivateData'))
{
    class MspMerchantPrivateData extends MspMerchantPrivate
    {
        /**
         * @since 1.7
         *
         * @param mixed|array $data a string with the data that will go in the
         *                    merchant-private-data tag or an array that will
         *                    be mapped to xml, formatted like (e.g.):
         *                    array('my-order-id' => 34234,
         *                          'stuff' => array('registered' => 'yes',
         *                                           'category' => 'hip stuff'))
         *                    this will map to:
         *                    <my-order-id>
         *                      <stuff>
         *                        <registered>yes</registered>
         *                        <category>hip stuff</category>
         *                      </stuff>
         *                    </my-order-id>
         */
        public function __construct(array $data = [])
        {
            $this->data = $data;
            $this->type = 'merchant-private-data';
        }
    }
}

/**
 * Class that represents a merchant-private-item-data.
 *
 * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_merchant-private-item-data <merchant-private-data>}
 */
if (!class_exists('MspMerchantPrivateItemData'))
{
    class MspMerchantPrivateItemData extends MspMerchantPrivate
    {
        /**
         * @since 1.7
         *
         * @param string|array $data a string with the data that will go in the
         *                           merchant-private-item-data tag or an array that will
         *                           be mapped to xml, formatted like:
         *                           array('my-item-id' => 34234,
         *                           'stuff' => array('label' => 'cool',
         *                           'category' => 'hip stuff'))
         *                           this will map to:
         *                           <my-item-id>
         *                               <stuff>
         *                                   <label>cool</label>
         *                                  <category>hip stuff</category>
         *                               </stuff>
         *                           </my-item-id>
         *
         */
        public function __construct(string|array $data = [])
        {
            $this->data = $data;
            $this->type = 'merchant-private-item-data';
        }
    }
}

/**
 * Copyright (C) 2007 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Classes used to represent an item to be used for Google Checkout
 *
 * @version $Id: googleitem.php 1234 2007-09-25 14:58:57Z ropu $
 */
/**
 * Creates an item to be added to the shopping cart.
 * A new instance of the class must be created for each item to be added.
 *
 * Required fields are the item name, description, quantity and price
 * The private-data and tax-selector for each item can be set in the
 * constructor call or using individual Set functions
 */
if (!class_exists('MspItem'))
{
    class MspItem
    {
        public string $item_name = '';
        public string $item_description = '';
        public float $unit_price = 0.0;
        public int $quantity = 0;
        public mixed $merchant_private_item_data = '';
        public mixed $merchant_item_id = '';
        public string $tax_table_selector = '';
        public bool $email_delivery = false;
        public bool $digital_content = false;
        public string $digital_description = '';
        public string $digital_key = '';
        public string $digital_url = '';
        public string $item_weight = '';
        public float $numeric_weight = 0.0;

        /**
         * Returns the string escaped for use in XML documents
         *
         * @since 1.7
         *
         * @param string|null $str
         *
         * @return string
         */
        public function xmlEscape(?string $str): string
        {
            if (is_null($str))
            {
                return '';
            }

            return htmlspecialchars($str, ENT_COMPAT, 'UTF-8');
        }

        /**
         * Returns the string with all XML escaping removed
         *
         * @since 1.7
         *
         * @param ?string $str
         *
         * @return string
         */
        public function xmlUnescape(?string $str): string
        {
            if (is_null($str))
            {
                return '';
            }

            return html_entity_decode($str, ENT_COMPAT, 'UTF-8');
        }

        /**
         * @since 1.7
         *
         * @param string $desc the description of the item -- required
         * @param int $qty the number of units of this item the customer has in its shopping cart -- required
         * @param float $price the unit price of the item -- required
         * @param string $item_weight the weight unit used to specify the item's weight, one of 'LB' (pounds) or 'KG' (kilograms)
         * @param float $numeric_weight the weight of the item
         * @param string $name the name of the item -- required
         *
         * {@link http://code.google.com/apis/checkout/developer/index.html#tag_item <item>}
         */
        public function __construct(string $name, string $desc, int $qty, float $price, string $item_weight = '', float $numeric_weight = 0.0)
        {
            $this->item_name = $this->xmlEscape($name);
            $this->item_description = $this->xmlEscape($desc);
            $this->unit_price = $price;
            $this->quantity = $qty;

            if (($item_weight !== '') && ($numeric_weight !== 0.0))
            {
                switch (strtoupper($item_weight))
                {
                    case 'KG':
                        $this->item_weight = strtoupper($item_weight);
                        break;
                    case 'LB':
                    default:
                        $this->item_weight = 'LB';
                }
                $this->numeric_weight = $numeric_weight;
            }
        }

        /**
         * @since 1.7
         *
         * @param $private_data
         *
         * @return void
         */
        public function SetMerchantPrivateItemData($private_data): void
        {
            $this->merchant_private_item_data = $private_data;
        }

        /**
         * Set the merchant item id that the merchant uses to uniquely identify an
         * item. Google Checkout will include this value in the
         * merchant calculation callbacks
         *
         * @since 1.7
         *
         * @param mixed $item_id the value that identifies this item on the merchant's side
         *
         * @return void
         *
         * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_merchant-item-id <merchant-item-id>}
         */
        public function SetMerchantItemId(mixed $item_id): void
        {
            $this->merchant_item_id = $item_id;
        }

        /**
         * Sets the tax table selector which identifies an alternate tax table that
         * should be used to calculate tax for a particular item.
         *
         * @since 1.7
         *
         * @param string $tax_selector this value should correspond to the name of an alternate-tax-table.
         *
         * @return void
         *
         * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_tax-table-selector <tax-table-selector>}
         */
        public function SetTaxTableSelector(string $tax_selector): void
        {
            $this->tax_table_selector = $tax_selector;
        }

        /**
         * Used when the item's content is digital, sets whether the merchant will
         * email the buyer explaining how to access the digital content.
         * Email delivery allows the merchant to charge the buyer for an order
         * before allowing the buyer to access the digital content.
         *
         * @since 1.7
         *
         * @param bool $email_delivery true if email_delivery applies, defaults to false
         *
         * @return void
         *
         * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_email-delivery <email-delivery>}
         */
        public function SetEmailDigitalDelivery(bool $email_delivery = false): void
        {
            $this->digital_url = '';
            $this->digital_key = '';
            $this->digital_description = '';
            $this->email_delivery = $email_delivery;
            $this->digital_content = true;
        }

        /**
         * Sets the information related to the digital delivery of the item.
         *
         * @since 1.7
         *
         * @param string $digital_key the key which allows to download or unlock the digital content item -- optional
         * @param string $digital_description instructions for downloading adigital content item, 1024 characters max, can
         *                                     contain xml-escaped HTML -- optional
         * @param string $digital_url the url the customer must go to download the item. --optional
         *
         * @return void
         *
         * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_digital-content <digital-content>}
         */
        public function SetURLDigitalContent(string $digital_url, string $digital_key, string $digital_description): void
        {
            $this->digital_url = $digital_url;
            $this->digital_key = $digital_key;
            $this->digital_description = $digital_description;
            $this->email_delivery = false;
            $this->digital_content = true;
        }
    }
}

/**
 * Copyright (C) 2007 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

/**
 * Classes used to represent shipping types
 *
 * @version $Id: googleshipping.php 1234 2007-09-25 14:58:57Z ropu $
 */

/**
 * Class that represents flat rate shipping
 *
 * {@link http://code.google.com/apis/checkout/developer/index.html#tag_flat-rate-shipping}
 * {@link http://code.google.com/apis/checkout/developer/index.html#shipping_xsd}
 *
 */
if (!class_exists('MspFlatRateShipping'))
{
    class MspFlatRateShipping
    {
        public float $price = 0.0;
        public string $name = '';
        public string $type = 'flat-rate-shipping';
        public mixed $shipping_restrictions;

        /**
         * @since 1.7
         *
         * @param float $price the price for this shipping
         * @param string $name a name for the shipping
         */
        public function __construct(string $name, float $price)
        {
            $this->name = $name;
            $this->price = $price;
        }

        /**
         * Adds a restriction to this shipping
         *
         * @since 1.7
         *
         * @param GoogleShippingFilters $restrictions the shipping restrictions
         *
         */
        public function AddShippingRestrictions(GoogleShippingFilters $restrictions): void
        {
            $this->shipping_restrictions = $restrictions;
        }
    }
}

/**
 *
 * Shipping restrictions contain information about particular areas where
 * items can (or cannot) be shipped.
 *
 * More info:
 * {@link http://code.google.com/apis/checkout/developer/index.html#tag_shipping-restrictions}
 *
 * Address filters identify areas where a particular merchant-calculated
 * shipping method is available or unavailable. Address filters are applied
 * before Google Checkout sends a <merchant-calculation-callback> to the
 * merchant. Google Checkout will not ask you to calculate the cost of a
 * particular shipping method for an address if the address filters in the
 * Checkout API request indicate that the method is not available for the
 * address.
 *
 * More info:
 * {@link http://code.google.com/apis/checkout/developer/index.html#tag_address-filters}
 */
if (!class_exists('MspShippingFilters'))
{
    class MspShippingFilters
    {
        public bool $allow_us_po_box = true;
        public bool $allowed_restrictions = false;
        public bool $excluded_restrictions = false;
        public bool $allowed_world_area = false;
        public array $allowed_country_codes_arr;
        public array $allowed_postal_patterns_arr;
        public string $allowed_country_area = '';
        public array $allowed_state_areas_arr;
        public array $allowed_zip_patterns_arr;
        public array $excluded_country_codes_arr;
        public array $excluded_postal_patterns_arr;
        public string $excluded_country_area = '';
        public array $excluded_state_areas_arr;
        public array $excluded_zip_patterns_arr;

        public function __construct()
        {
            $this->allowed_country_codes_arr = [];
            $this->allowed_postal_patterns_arr = [];
            $this->allowed_state_areas_arr = [];
            $this->allowed_zip_patterns_arr = [];

            $this->excluded_country_codes_arr = [];
            $this->excluded_postal_patterns_arr = [];
            $this->excluded_state_areas_arr = [];
            $this->excluded_zip_patterns_arr = [];
        }

        /**
         * @since 1.7
         *
         * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_allow-us-po-box <allow-us-po-box>}
         *
         * @param bool $allow_us_po_box whether to allow delivery to PO boxes in US,
         * defaults to true
         *
         */
        public function SetAllowUsPoBox(bool $allow_us_po_box = true): void
        {
            $this->allow_us_po_box = $allow_us_po_box;
        }

        /**
         * Set the world as allowed delivery area
         *
         * @since 1.7
         *
         * @param bool $world_area Set worldwide allowed shipping, defaults to true
         *
         * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_world-area <world-area>}
         */
        public function SetAllowedWorldArea(bool $world_area = true): void
        {
            $this->allowed_restrictions = true;
            $this->allowed_world_area = $world_area;
        }

        /**
         * Add a postal area to be allowed for delivery
         *
         * @since 1.7
         *
         * @param string $postal_pattern Pattern that matches the postal areas to
         * be allowed, as defined in {@link http://code.google.com/apis/checkout/developer/index.html#tag_postal-code-pattern}
         *
         * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_postal-area <postal-area>}
         * @param string $country_code 2-letter iso country code
         */
        public function AddAllowedPostalArea(string $country_code, string $postal_pattern = ''): void
        {
            $this->allowed_restrictions = true;
            $this->allowed_country_codes_arr[] = $country_code;
            $this->allowed_postal_patterns_arr[] = $postal_pattern;
        }

        /**
         * Add a US country area to be allowed for delivery.
         *
         * @since 1.7
         *
         * @param string $country_area the area to allow, one of
         *                             'CONTINENTAL_48',
         *                             'FULL_50_STATES' or 'ALL'
         *
         * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_us-country-area <us-country-area>}
         */
        public function SetAllowedCountryArea(string $country_area): void
        {
            switch ($country_area)
            {
                case 'CONTINENTAL_48':
                case 'FULL_50_STATES':
                case 'ALL':
                    $this->allowed_country_area = $country_area;
                    $this->allowed_restrictions = true;
                    break;
                default:
                    $this->allowed_country_area = '';
                    break;
            }
        }

        /**
         * Allow shipping to areas specified by state.
         *
         * @since 1.7
         *
         * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_us-state-area <us-state-area>}
         *
         * @param array $areas Areas to be allowed
         */
        public function SetAllowedStateAreas(array $areas): void
        {
            $this->allowed_restrictions = true;
            $this->allowed_state_areas_arr = $areas;
        }

        /**
         * Allow shipping to areas specified by state
         *
         * @since 1.7
         *
         * @param string $area Area to be allowed
         *
         * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_us-state-area <us-state-area>}
         */
        public function AddAllowedStateArea(string $area): void
        {
            $this->allowed_restrictions = true;
            $this->allowed_state_areas_arr[] = $area;
        }

        /**
         * Allow shipping to areas specified by zip patterns
         *
         * @since 1.7
         *
         * @param array $zips
         *
         * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_us-zip-area <us-zip-area>}
         */
        public function SetAllowedZipPatterns(array $zips): void
        {
            $this->allowed_restrictions = true;
            $this->allowed_zip_patterns_arr = $zips;
        }

        /**
         * Allow shipping to area specified by zip pattern.
         *
         * @since 1.7
         *
         * @param string $zip
         *
         * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_us-zip-area <us-zip-area>}
         */
        public function AddAllowedZipPattern(string $zip): void
        {
            $this->allowed_restrictions = true;
            $this->allowed_zip_patterns_arr[] = $zip;
        }

        /**
         * Exclude postal areas from shipping
         *
         * @since 1.7
         *
         * @param string $postal_pattern
         *
         * @param string $country_code
         *
         * @return void
         *
         * @see AddAllowedPostalArea
         */
        public function AddExcludedPostalArea(string $country_code, string $postal_pattern = ''): void
        {
            $this->excluded_restrictions = true;
            $this->excluded_country_codes_arr[] = $country_code;
            $this->excluded_postal_patterns_arr[] = $postal_pattern;
        }

        /**
         * Exclude state areas from shipping
         *
         * @since 1.7
         *
         * @param array $areas
         *
         * @see SetAllowedStateAreas
         */
        public function SetExcludedStateAreas(array $areas): void
        {
            $this->excluded_restrictions = true;
            $this->excluded_state_areas_arr = $areas;
        }

        /**
         * Exclude state area from shipping
         *
         * @since 1.7
         *
         * @param array $area
         *
         * @see AddAllowedStateArea
         */
        public function AddExcludedStateArea(array $area): void
        {
            $this->excluded_restrictions = true;
            $this->excluded_state_areas_arr[] = $area;
        }

        /**
         * Exclude shipping to area specified by zip pattern.
         *
         * @since 1.7
         *
         * @param array $zips
         *
         * @see SetAllowedZipPatterns
         */
        public function SetExcludedZipPatternsStateAreas(array $zips): void
        {
            $this->excluded_restrictions = true;
            $this->excluded_zip_patterns_arr = $zips;
        }

        /**
         * Exclude shipping to area specified by zip pattern.
         *
         * @since 1.7
         *
         * @param string $zip
         *
         * @see AddExcludedZipPattern
         */
        public function SetAllowedZipPatternsStateArea(string $zip): void
        {
            $this->excluded_restrictions = true;
            $this->excluded_zip_patterns_arr[] = $zip;
        }

        /**
         * Exclude shipping to country area
         *
         * @since 1.7
         *
         * @param string $country_area
         *
         * @see SetAllowedCountryArea
         */
        public function SetExcludedCountryArea(string $country_area): void
        {
            switch ($country_area)
            {
                case 'CONTINENTAL_48':
                case 'FULL_50_STATES':
                case 'ALL':
                    $this->excluded_country_area = $country_area;
                    $this->excluded_restrictions = true;
                    break;

                default:
                    $this->excluded_country_area = '';
                    break;
            }
        }
    }
}

/**
 * Used as a shipping option in which neither a carrier nor a ship-to
 * address is specified
 *
 * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_pickup} <pickup>
 */
if (!class_exists('MspPickUp'))
{
    class MspPickUp
    {
        public float $price = 0.0;
        public string $name = '';
        public string $type = 'pickup';

        /**
         * @since 1.7
         *
         * @param float $price the handling cost (if there is one)
         * @param string $name the name of this shipping option
         */
        public function __construct(string $name, float $price)
        {
            $this->price = $price;
            $this->name = $name;
        }
    }
}

/**
 * Copyright (C) 2006 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Classes used to handle tax rules and tables
 */

/**
 * Represents a tax rule
 *
 * @see GoogleDefaultTaxRule
 * @see GoogleAlternateTaxRule
 *
 * @abstract
 */
if (!class_exists('MspTaxRule'))
{
    class MspTaxRule
    {
        public string $tax_rate = '';
        public bool $world_area = false;
        public array $country_codes_arr = [];
        public array $postal_patterns_arr = [];
        public array $state_areas_arr = [];
        public array $zip_patterns_arr = [];
        public string $country_area = '';

        public function __construct() {}

        /**
         * @since 1.7
         *
         * @param bool $world_area
         *
         * @return void
         */
        public function SetWorldArea(bool $world_area = true): void
        {
            $this->world_area = $world_area;
        }

        /**
         * @since 1.7
         *
         * @param string $postal_pattern
         * @param string $country_code
         *
         * @return void
         */
        public function AddPostalArea(string $country_code, string $postal_pattern = ''): void
        {
            $this->country_codes_arr[] = $country_code;
            $this->postal_patterns_arr[] = $postal_pattern;
        }

        /**
         * @since 1.7
         *
         * @param array|string $areas
         *
         * @return void
         */
        public function SetStateAreas(array|string $areas): void
        {
            if (is_array($areas))
            {
                $this->state_areas_arr = $areas;
            }
            else
            {
                $this->state_areas_arr = [$areas];
            }
        }

        /**
         * @since 1.7
         *
         * @param array|string $zips
         *
         * @return void
         */
        public function SetZipPatterns(array|string $zips): void
        {
            if (is_array($zips))
            {
                $this->zip_patterns_arr = $zips;
            }
            else
            {
                $this->zip_patterns_arr = [$zips];
            }
        }

        /**
         * @since 1.7
         *
         * @param string $country_area
         *
         * @return void
         */
        public function SetCountryArea(string $country_area): void
        {
            switch ($country_area)
            {
                case 'CONTINENTAL_48':
                case 'FULL_50_STATES':
                case 'ALL':
                    $this->country_area = $country_area;
                    break;

                default:
                    $this->country_area = '';
                    break;
            }
        }
    }
}

/**
 * Represents a default tax rule
 *
 * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_default-tax-rule <default-tax-rule>}
 */
if (!class_exists('MspDefaultTaxRule'))
{
    class MspDefaultTaxRule extends MspTaxRule
    {
        public mixed $shipping_taxed = false;

        /**
         * @since 1.7
         *
         * @param $shipping_taxed
         * @param string $tax_rate
         */
        public function __construct(string $tax_rate, $shipping_taxed = 'false')
        {
            $this->tax_rate = $tax_rate;
            $this->shipping_taxed = $shipping_taxed;

            $this->country_codes_arr = [];
            $this->postal_patterns_arr = [];
            $this->state_areas_arr = [];
            $this->zip_patterns_arr = [];
        }
    }
}

/**
 * Represents an alternate tax rule
 *
 * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_alternate-tax-rule <alternate-tax-rule>}
 */
if (!class_exists('MspAlternateTaxRule'))
{
    class MspAlternateTaxRule extends MspTaxRule
    {

        /**
         * @since 1.7
         *
         * @param string $tax_rate
         */
        public function __construct(string $tax_rate)
        {
            $this->tax_rate = $tax_rate;

            $this->country_codes_arr = [];
            $this->postal_patterns_arr = [];
            $this->state_areas_arr = [];
            $this->zip_patterns_arr = [];
        }
    }
}

/**
 * Represents an alternate tax table
 *
 * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_alternate-tax-table <alternate-tax-table>}
 */
if (!class_exists('MspAlternateTaxTable'))
{
    class MspAlternateTaxTable
    {
        public string $name = '';
        public array $tax_rules_arr;
        public string $standalone = 'false';

        /**
         * @since 1.7
         *
         * @param string $standalone
         * @param string $name
         */
        public function __construct(string $name = '', string $standalone = 'false')
        {
            if ($name !== '')
            {
                $this->name = $name;
                $this->tax_rules_arr = [];
                $this->standalone = $standalone;
            }
        }

        /**
         * @since 1.7
         *
         * @param $rules
         *
         * @return void
         */
        public function AddAlternateTaxRules($rules): void
        {
            $this->tax_rules_arr[] = $rules;
        }
    }
}

/**
 * Represents a merchant calculation callback
 */
if (!class_exists('MspCustomFields'))
{
    class MspCustomFields
    {
        public array $fields = [];
        public string $fields_xml_extra = '';

        /**
         * @since 1.7
         *
         * @param $field
         *
         * @return void
         */
        public function AddField($field): void
        {
            $this->fields[] = $field;
        }

        /**
         * @since 1.7
         *
         * @param $xml
         *
         * @return void
         */
        public function SetRaw($xml): void
        {
            $this->fields_xml_extra = $xml;
        }

        /**
         * @since 1.7
         *
         * @return string
         */
        public function GetXml(): string
        {
            $xml_data = new msp_gc_XmlBuilder();
            $xml_data->xml = '';

            foreach ($this->fields as $field)
            {
                $xml_data->Push('field');

                if ($field->standardField)
                {
                    $xml_data->Element('standardtype', $field->standardField);
                }

                if ($field->name)
                {
                    $xml_data->Element('name', $field->name);
                }
                if ($field->type)
                {
                    $xml_data->Element('type', $field->type);
                }
                if ($field->default)
                {
                    $xml_data->Element('default', $field->default);
                }
                if ($field->savevalue)
                {
                    $xml_data->Element('savevalue', $field->savevalue);
                }
                if ($field->label)
                {
                    $this->_GetXmlLocalized($xml_data, 'label', $field->label);
                }

                if (!empty($field->descriptionTop))
                {
                    $xml_data->Push('description-top');
                    if (!empty($field->descriptionTop['style']))
                    {
                        $xml_data->Element('style', $field->descriptionTop['style']);
                    }
                    $this->_GetXmlLocalized($xml_data, 'value', $field->descriptionTop['value']);
                    $xml_data->Pop('description-top');
                }

                if (!empty($field->descriptionRight))
                {
                    $xml_data->Push('description-right');
                    if (!empty($field->descriptionRight['style']))
                    {
                        $xml_data->Element('style', $field->descriptionRight['style']);
                    }
                    $this->_GetXmlLocalized($xml_data, 'value', $field->descriptionRight['value']);
                    $xml_data->Pop('description-right');
                }

                if (!empty($field->descriptionBottom))
                {
                    $xml_data->Push('description-bottom');
                    if (!empty($field->descriptionBottom['style']))
                    {
                        $xml_data->Element('style', $field->descriptionBottom['style']);
                    }
                    $this->_GetXmlLocalized($xml_data, 'value', $field->descriptionBottom['value']);
                    $xml_data->Pop('description-bottom');
                }

                if (!empty($field->options))
                {
                    $xml_data->Push('options');
                    foreach ($field->options as $option)
                    {
                        $xml_data->Push('option');
                        $xml_data->Element('value', $option->value);
                        $this->_GetXmlLocalized($xml_data, 'label', $option->label);
                        $xml_data->Pop('option');
                    }
                    $xml_data->Pop('options');
                }

                if (!empty($field->validation))
                {
                    foreach ($field->validation as $validation)
                    {
                        $xml_data->Push('validation');
                        $xml_data->Element($validation->type, $validation->data);
                        $this->_GetXmlLocalized($xml_data, 'error', $validation->error);
                        $xml_data->Pop('validation');
                    }
                }

                if ($field->filter)
                {
                    $xml_data->Push('field-restrictions');

                    if (!empty($field->filter->allowed_country_codes_arr))
                    {
                        $xml_data->Push('allowed-areas');
                        foreach ($field->filter->allowed_country_codes_arr as $country_code)
                        {
                            $xml_data->Push('postal-area');
                            $xml_data->Element('country-code', $country_code);
                            $xml_data->Pop('postal-area');
                        }
                        $xml_data->Pop('allowed-areas');
                    }

                    if (!empty($field->filter->excluded_country_codes_arr))
                    {
                        $xml_data->Push('excluded-areas');
                        foreach ($field->filter->excluded_country_codes_arr as $country_code)
                        {
                            $xml_data->Push('postal-area');
                            $xml_data->Element('country-code', $country_code);
                            $xml_data->Pop('postal-area');
                        }
                        $xml_data->Pop('excluded-areas');
                    }

                    $xml_data->Pop('field-restrictions');
                }

                $xml_data->Pop('field');
            }

            //$xml_data->Pop('custom-fields');

            return '<custom-fields>' . $xml_data->GetXML() . $this->fields_xml_extra . '</custom-fields>';
        }

        /**
         * @since 1.7
         *
         * @param $field
         * @param $value
         * @param $xml_data
         *
         * @return void
         */
        public function _GetXmlLocalized($xml_data, $field, $value): void
        {
            if (is_array($value))
            {
                foreach ($value as $lang => $text)
                {
                    $xml_data->Element($field, $text, ['xml:lang' => $lang]);
                }
            }
            else
            {
                $xml_data->Element($field, $value);
            }
        }

    }
}

if (!class_exists('MspCustomField'))
{
    class MspCustomField
    {
        public mixed $standardField = null;
        public ?string $name = null;
        public ?string $type = null;
        public mixed $label = null;
        public mixed $default = null;
        public mixed $savevalue = null;
        public array $options = [];
        public array $validation = [];
        public mixed $filter = null;
        public array $descriptionTop = [];
        public array $descriptionRight = [];
        public array $descriptionBottom = [];

        /**
         * @since 1.7
         *
         * @param $type
         * @param $label
         * @param $name
         */
        public function __construct($name = null, $type = null, $label = null)
        {
            $this->name = $name;
            $this->type = $type;
            $this->label = $label;
        }

        /**
         * @since 1.7
         *
         * @param $label
         * @param $value
         *
         * @return void
         */
        public function AddOption($value, $label): void
        {
            $this->options[] = new MspCustomFieldOption($value, $label);
        }

        /**
         * @since 1.7
         *
         * @param $validation
         *
         * @return void
         */
        public function AddValidation($validation): void
        {
            $this->validation[] = $validation;
        }

        /**
         * @since 1.7
         *
         * @param $filter
         *
         * @return void
         */
        public function AddRestrictions($filter): void
        {
            $this->filter = $filter;
        }

        /**
         * @since 1.7
         *
         * @param $optional
         * @param $name
         *
         * @return void
         */
        public function SetStandardField($name, $optional = false): void
        {
            $this->standardField = $name;
            if ($optional)
            {
                $this->AddValidation(new MspCustomFieldValidation('regex', ' ', ''));
            }
        }
    }
}

if (!class_exists('MspCustomFieldOption'))
{
    class MspCustomFieldOption
    {
        public mixed $value;
        public mixed $label;

        /**
         * @since 1.7
         *
         * @param mixed $label
         * @param mixed $value
         */
        public function __construct(mixed $value, mixed $label)
        {
            $this->value = $value;
            $this->label = $label;
        }
    }
}

if (!class_exists('MspCustomFieldValidation'))
{
    class MspCustomFieldValidation
    {
        public string $type = '';
        public array|string $data;
        public string $error = '';

        /**
         * @since 1.7
         *
         * @param array|string $data
         * @param string $error
         * @param string $type
         */
        public function __construct(string $type, array|string $data, string $error)
        {
            $this->type = $type;
            $this->data = $data;
            $this->error = $error;
        }
    }
}

if (!class_exists('MspCustomFieldFilter'))
{
    class MspCustomFieldFilter
    {
        public array $allowed_country_codes_arr;
        public array $excluded_country_codes_arr;

        public function __construct()
        {
            $this->allowed_country_codes_arr = [];
            $this->excluded_country_codes_arr = [];
        }

        /**
         * @since 1.7
         *
         * @param string $country_code
         *
         * @return void
         */
        public function AddAllowedPostalArea(string $country_code): void
        {
            $this->allowed_country_codes_arr[] = $country_code;
        }

        /**
         * @since 1.7
         *
         * @param string $country_code
         *
         * @return void
         */
        public function AddExcludedPostalArea(string $country_code): void
        {
            $this->excluded_country_codes_arr[] = $country_code;
        }
    }
}
