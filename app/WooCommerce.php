<?php

declare(strict_types=1);

namespace BeycanPress\WooCommerce\FreshBooks;

use BeycanPress\FreshBooks\Connection;
use BeycanPress\FreshBooks\Model\Invoice;
use BeycanPress\FreshBooks\Model\InvoiceLine;

class WooCommerce
{
    use Helpers;

    /**
     * @var Invoice|null
     */
    private ?Invoice $invoice = null;

    /**
     * constructor
     */
    public function __construct()
    {
        add_filter('woocommerce_order_actions', [$this, 'addOrderActions'], 10, 2);
        add_action('woocommerce_order_refunded', [$this, 'refundProcess'], 10, 2);
        add_action('woocommerce_order_status_completed', [$this, 'invoiceProcess']);
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'backend'], 10);
        add_action('woocommerce_process_shop_order_meta', [$this, 'executeInvoiceAction'], 50);
    }

    /**
     * @param array<mixed> $actions
     * @param object $order
     * @return array<mixed>
     */
    public function addOrderActions(array $actions, object $order): array
    {
        $invoiceId = get_post_meta($order->get_id(), 'wcfb_invoice_id', true);

        if (!$invoiceId) {
            $actions['create-invoice'] = esc_html__('Create FreshBooks Invoice', 'woocommerce-freshbooks');
        }

        return $actions;
    }

    /**
     * @param int $orderId
     * @return void
     */
    public function executeInvoiceAction(int $orderId): void
    {
        if ('create-invoice' !== filter_input(INPUT_POST, 'wc_order_action')) {
            return;
        }

        $this->invoiceProcess($orderId);
    }

    /**
     * @param object $order
     * @return void
     */
    public function backend(object $order): void
    {
        $invoiceId = get_post_meta($order->get_id(), 'wcfb_invoice_id', true);
        $invoiceNumber = get_post_meta($order->get_id(), 'wcfb_invoice_number', true);
        $accountId = get_post_meta($order->get_id(), 'wcfb_invoice_account_id', true);

        if ($invoiceId) {
            echo '<div class="order_data_column">';
            echo '<h4>' . esc_html__('FreshBooks Invoice', 'woocommerce-freshbooks') . '</h4>';
            echo '<p><strong>' . esc_html__('Invoice ID', 'woocommerce-freshbooks') . ':</strong> ' . esc_html($invoiceId) . '</p>'; // phpcs:ignore
            if ($accountId && $invoiceNumber) {
                echo '<p><strong>' . esc_html__('Account ID', 'woocommerce-freshbooks') . ':</strong> ' . esc_html($accountId) . '</p>'; // phpcs:ignore
                echo '<p><strong>' . esc_html__('Invoice Number', 'woocommerce-freshbooks') . ':</strong> ' . esc_html($invoiceNumber) . '</p>'; // phpcs:ignore
                echo '<p><a href="https://my.freshbooks.com/#/invoice/' . esc_html($accountId) . '-' . esc_html($invoiceId) . '" alt="Go to Invoice" target="_blank">Go to Invoice</a></strong></p>'; // phpcs:ignore
            }
            echo '</div>';
        }
    }

    /**
     * @param object $order
     * @return array<InvoiceLine>
     */
    private function getInvoiceLines(object $order): array
    {
        $lines = [];

        foreach ($order->get_items() as $item) {
            $itemPrice = $this->setting('addDiscountData') ? $item->get_subtotal() : $item->get_total();

            $line = (new InvoiceLine())
                ->setName($item->get_name())
                ->setAmount((object) [
                    "amount" => ($itemPrice / $item->get_quantity()),
                    "code" => $order->get_currency()
                ])
                ->setQuantity($item->get_quantity());

            if ($tax = $order->get_line_tax($item)) {
                $taxes = array_values($order->get_items('tax'));

                if (isset($taxes[0])) {
                    $tax = $taxes[0];
                    $line->setTaxName1($tax->get_label())
                        ->setTaxAmount1($tax->get_rate_percent());
                }

                if (isset($taxes[1])) {
                    $tax = $taxes[1];
                    $line->setTaxName2($tax->get_label())
                        ->setTaxAmount2($tax->get_rate_percent());
                }
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * @param object $order
     * @return void
     */
    private function maybeAddDiscount(object $order): void
    {
        if ($this->setting('addDiscountData')) {
            $totalDiscount = $order->get_total_discount();

            if ($totalDiscount > 0) {
                $totalOrderValue = $order->get_subtotal();
                $discountCodes = $order->get_coupon_codes();
                $discountCodes = implode(',', $discountCodes);

                if ($totalOrderValue > 0) {
                    $discountRate = ($totalDiscount / $totalOrderValue) * 100;
                } else {
                    $discountRate = 0;
                }

                ($this->invoice)
                    ->setDiscountValue($discountRate)
                    ->setDiscountDescription($discountCodes);
            }
        }
    }

    /**
     * @param int $orderId
     * @return void
     */
    public function invoiceProcess(int $orderId): void
    {
        if (!$conn = $this->callFunc('initFbConnection', true)) {
            return;
        }

        try {
            $order = wc_get_order($orderId);

            $paymentMethod = $order->get_payment_method();
            $excludePaymentMethods = $this->setting('excludePaymentMethods', []);
            $excludePaymentMethods = is_array($excludePaymentMethods) ? $excludePaymentMethods : [];
            if (in_array($paymentMethod, $excludePaymentMethods)) {
                return;
            }

            if (get_post_meta($orderId, 'wcfb_invoice_id', true)) {
                return;
            }

            $email = $order->get_billing_email();

            if (!$client = $conn->client()->searchByEmail($email)) {
                $client = $conn->client()
                    ->setEmail($email)
                    ->setFirstName($order->get_billing_first_name())
                    ->setLastName($order->get_billing_last_name())
                    ->setCurrencyCode($order->get_currency())
                    ->create();

                do_action('wcfb_client_created', $this->invoice, $order);
            }

            $lines = $this->getInvoiceLines($order);

            $this->invoice = $conn->invoice()
                ->setStatus("draft")
                ->setCustomerId($client->getId())
                ->setCreateDate(gmdate("Y-m-d"))
                ->setLines($lines);

            $this->maybeAddDiscount($order);

            $this->invoice->create();
            update_post_meta($orderId, 'wcfb_invoice_id', $this->invoice->getId());
            update_post_meta($orderId, 'wcfb_invoice_number', $this->invoice->getInvoiceNumber());
            update_post_meta($orderId, 'wcfb_invoice_account_id', $this->invoice->getAccountId());

            if ($this->setting('sendToEmail')) {
                $this->invoice->sendToEMail($email);
            }

            do_action('wcfb_invoice_created', $conn, $order, $this->invoice);

            $this->paymentCompleted($orderId);
        } catch (\Throwable $th) {
            wp_mail(get_option('admin_email'), 'FreshBooks Invoice Create Error', $th->getMessage());

            $this->debug($th->getMessage(), 'CRITICAL', [
                'trace' => $th->getTrace(),
                'file' => $th->getFile(),
                'line' => $th->getLine()
            ]);
        }
    }

    /**
     * @param int $orderId
     * @return void
     */
    public function paymentCompleted(int $orderId): void
    {
        /** @var Connection $conn */
        if (!$conn = $this->callFunc('initFbConnection', true)) {
            return;
        }

        $order = wc_get_order($orderId);
        if (!$this->invoice) {
            $invoiceId = get_post_meta($orderId, 'wcfb_invoice_id', true);
            $this->invoice = $conn->invoice()->getById($invoiceId);
        }

        if (floatval($this->invoice->getOutstanding()->amount) > 0) {
            try {
                $gateway = $order->get_payment_method();

                if ('stripe' == $gateway || false !== strpos($gateway, 'stripe')) {
                    $type = 'Credit Card';
                } elseif (
                    'ppcp' == $gateway ||
                    'paypal' == $gateway ||
                    'ppec_paypal' == $gateway ||
                    'ppcp-gateway' == $gateway ||
                    'ppcp-oxxo-gateway' == $gateway ||
                    'ppcp-card-button-gateway' == $gateway ||
                    'ppcp-credit-card-gateway' == $gateway ||
                    'ppcp-pay-upon-invoice-gateway' == $gateway ||
                    (false !== strpos($gateway, 'paypal') || false !== strpos($gateway, 'ppcp'))
                ) {
                    $type = 'PayPal';
                } elseif ('bacs' == $gateway) {
                    $type = 'Bank Transfer';
                } elseif ('cod' == $gateway) {
                    $type = 'Cash';
                } elseif ('cheque' == $gateway) {
                    $type = 'Check';
                } else {
                    $type = "Other";
                }

                $type = apply_filters('wcfb_payment_type', $type, $conn, $order, $this->invoice);

                $payment = $conn->payment()
                    ->setInvoiceId($this->invoice->getId())
                    ->setAmount((object) [
                        "amount" => $this->invoice->getOutstanding()->amount
                    ])
                    ->setDate(gmdate("Y-m-d"))
                    ->setType($type)
                    ->create();

                do_action('wcfb_payment_completed', $conn, $order, $this->invoice);

                update_post_meta($orderId, 'wcfb_payment_id', $payment->getId());
            } catch (\Throwable $th) {
                wp_mail(get_option('admin_email'), 'FreshBooks Payment Add Error', $th->getMessage());

                $this->debug($th->getMessage(), 'CRITICAL', [
                    'trace' => $th->getTrace(),
                    'file' => $th->getFile(),
                    'line' => $th->getLine()
                ]);
            }
        }
    }

    /**
     * @param int $orderId
     * @param int $refundId
     * @return void
     */
    public function refundProcess(int $orderId, int $refundId): void
    {
        /** @disregard */
        if (!$conn = $this->callFunc('initFbConnection', true)) {
            return;
        }

        try {
            $paymentId = get_post_meta($orderId, 'wcfb_payment_id', true);
            $invoiceId = get_post_meta($orderId, 'wcfb_invoice_id', true);

            if (!$invoiceId || !$paymentId) {
                return;
            }

            $order = wc_get_order($orderId);
            $refund = new \WC_Order_Refund($refundId);
            $refundAmount = (float) $refund->get_amount();
            $invoice = $conn->invoice()->getById($invoiceId, true);
            $payment =  $conn->payment()->getById($paymentId);

            $amount = floatval($payment->getAmount()->amount);
            $amount = $amount - $refundAmount;

            $payment->setAmount((object) [
                "amount" => strval($amount),
                "code" => "USD"
            ]);

            $invoice->addLine((new InvoiceLine())
            ->setName("Refund - " . $refundId)
            ->setAmount((object) [
                "amount" => '-' . strval($refundAmount),
                "code" => $order->get_currency()
            ])
            ->setQuantity(1));

            if (0 <= $amount) {
                $payment->update();
            }

            $invoice->updateLines();
        } catch (\Throwable $th) {
            wp_mail(get_option('admin_email'), 'FreshBooks - Refund Invoice Error', $th->getMessage());

            $this->debug($th->getMessage(), 'CRITICAL', [
                'trace' => $th->getTrace(),
                'file' => $th->getFile(),
                'line' => $th->getLine()
            ]);
        }
    }
}
