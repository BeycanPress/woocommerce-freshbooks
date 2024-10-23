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
     * @var string
     */
    private string $metaKey = 'freshbooks_invoice_id';

    /**
     * constructor
     */
    public function __construct()
    {
        $this->dailyInvoiceCreatedOrderIdsReset();
        add_action('woocommerce_order_status_completed', [$this, 'invoiceProcess']);
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'backend'], 10);
    }

    /**
     * @return void
     */
    public function dailyInvoiceCreatedOrderIdsReset(): void
    {
        $today = gmdate('Y-m-d');
        $lastReset = get_option('wcfb_invoice_created_order_ids_reset', '');

        if ($today != $lastReset) {
            update_option('wcfb_invoice_created_order_ids', []);
            update_option('wcfb_invoice_created_order_ids_reset', $today);
        }
    }

    /**
     * @param object $order
     * @return void
     */
    public function backend(object $order): void
    {
        $invoiceId = get_post_meta($order->get_id(), $this->metaKey, true);
        $invoiceNumber = get_post_meta($order->get_id(), 'freshbooks_invoice_number', true);
        $accountId = get_post_meta($order->get_id(), 'freshbooks_invoice_account_id', true);

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

            $invoiceCreatedOrderIds = get_option('wcfb_invoice_created_order_ids', []);
            if (in_array($orderId, $invoiceCreatedOrderIds)) {
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

            $this->invoice = $conn->invoice()
                ->setStatus("draft")
                ->setCustomerId($client->getId())
                ->setCreateDate(gmdate("Y-m-d"))
                ->setLines($lines);

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

                    $this->invoice
                        ->setDiscountValue((float) $discountRate)
                        ->setDiscountDescription($discountCodes);
                }
            }

            $this->invoice->create();
            $invoiceCreatedOrderIds[] = $orderId;
            update_option('wcfb_invoice_created_order_ids', $invoiceCreatedOrderIds);
            update_post_meta($orderId, $this->metaKey, $this->invoice->getId());
            update_post_meta($orderId, 'freshbooks_invoice_number', $this->invoice->getInvoiceNumber());
            update_post_meta($orderId, 'freshbooks_invoice_account_id', $this->invoice->getAccountId());

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
        if (!$conn = $this->callFunc('initFbConnection', true)) {
            return;
        }

        $order = wc_get_order($orderId);
        if (!$this->invoice) {
            $invoiceId = (int) $order->get_meta('_wcfb_invoice_id');
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

                $conn->payment()
                    ->setInvoiceId($this->invoice->getId())
                    ->setAmount((object) [
                        "amount" => $this->invoice->getOutstanding()->amount
                    ])
                    ->setDate(gmdate("Y-m-d"))
                    ->setType($type)
                    ->create();

                do_action('wcfb_payment_completed', $conn, $order, $this->invoice);
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
}
