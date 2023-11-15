<?php

namespace BeycanPress\WooCommerce\FreshBooks;

use \BeycanPress\FreshBooks\Connection;
use \BeycanPress\FreshBooks\Model\Invoice;
use \BeycanPress\FreshBooks\Model\InvoiceLine;

class WooCommerce
{
    use PluginHero\Helpers;

    /**
     * @var Connection
     */
    private $conn;

    /**
     * @var Invoice
     */
    private $invoice;

    public function __construct()
    {
        add_action('woocommerce_order_status_completed', [$this, 'invoiceProcess']);
    }

    /**
     * @param int $orderId
     * @return void
     */
    public function invoiceProcess(int $orderId)
    {
        if (!$conn = $this->callFunc('initFbConnection', true)) return;

        try {
            $order = wc_get_order($orderId);

            $paymentMethod = $order->get_payment_method();
            if (in_array($paymentMethod, $this->setting('excludePaymentMethods'))) {
                return;
            }
            
            if ($order->get_meta('_wcfb_invoice_id')) {
                return;
            }

            $email = $order->get_billing_email();
            
            if (!$client = $conn->client()->searchByEmail($email)) {
                $client = $conn->client()
                ->setEmail($email)
                ->setFirstName($order->get_billing_first_name())
                ->setLastName($order->get_billing_last_name())
                ->setOrganization($order->get_billing_company())
                ->setMobilePhone($order->get_billing_phone())
                ->setBillingStreet($order->get_billing_address_1())
                ->setBillingStreet2($order->get_billing_address_2())
                ->setBillingCity($order->get_billing_city())
                ->setBillingProvince($order->get_billing_state())
                ->setBillingPostalCode($order->get_billing_postcode())
                ->setBillingCountry($order->get_billing_country())
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
            ->setCreateDate(date("Y-m-d"))
            ->setLines($lines);

            if ($this->setting('addDiscountData')) {
                $totalDiscount = $order->get_total_discount(); 
    
                if ($totalDiscount > 0) {
                    $discountCodes = $order->get_coupon_codes(); 
                    $discountCodes = implode(',', $discountCodes);
                    $totalOrderValue = $order->get_subtotal();
        
                    if ($totalOrderValue > 0) {
                        $discountRate = ($totalDiscount / $totalOrderValue) * 100;
                    } else {
                        $discountRate = 0; 
                    }
    
                    $this->invoice
                    ->setDiscountValue($discountRate)
                    ->setDiscountDescription($discountCodes);
                }
            }

            $this->invoice->create();

            if ($this->invoice) {
                $order->update_meta_data('_wcfb_invoice_id', $this->invoice->getId());
                $order->save();
            }

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
    public function paymentCompleted(int $orderId)
    {
        if (!$conn = $this->callFunc('initFbConnection', true)) return;
        
        $order = wc_get_order($orderId);
        if (!$this->invoice) {
            $invoiceId = (int) $order->get_meta('_wcfb_invoice_id');
            $this->invoice = $conn->invoice()->getById($invoiceId);
        }

        if (floatval($this->invoice->getOutstanding()->amount) > 0) {
            try {
                $conn->payment()
                ->setInvoiceId($this->invoice->getId())
                ->setAmount((object) [
                    "amount" => $this->invoice->getOutstanding()->amount
                ])
                ->setDate(date("Y-m-d"))
                ->setType("Other")
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