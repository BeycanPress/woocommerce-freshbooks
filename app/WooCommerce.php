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
        if (!is_admin()) {
            add_action('woocommerce_payment_complete', [$this, 'paymentCompleted']);
            add_action('woocommerce_checkout_order_processed', [$this, 'invoiceProcess']);
        } else {
            add_action('woocommerce_order_status_completed', [$this, 'paymentCompleted']);
        }
    }

    /**
     * @param int $orderId
     * @return void
     */
    public function invoiceProcess($orderId)
    {
        if (!$conn = $this->callFunc('initFbConnection', true)) return;

        try {
            $order = wc_get_order($orderId);
            
            $email = $order->get_billing_email();
            
            if (!$client = $conn->client()->searchByEmail($email)) {
                $client = $conn->client()
                ->setFirstName($order->get_billing_first_name())
                ->setLastName($order->get_billing_last_name())
                ->setOrganization($order->get_billing_company())
                ->setEmail($email)
                ->setMobilePhone($order->get_billing_phone())
                ->setBillingStreet($order->get_billing_address_1())
                ->setBillingStreet2($order->get_billing_address_2())
                ->setBillingCity($order->get_billing_city())
                ->setBillingProvince($order->get_billing_state())
                ->setBillingPostalCode($order->get_billing_postcode())
                ->setBillingCountry($order->get_billing_country())
                ->setCurrencyCode($order->get_currency())
                ->create();
            }

            $lines = [];

            foreach ($order->get_items() as $item) {
                
                $line = (new InvoiceLine())
                ->setName($item->get_name())
                ->setAmount((object) [
                    "amount" => ($item->get_total() / $item->get_quantity()),
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
            ->setLines($lines)
            ->create();

            if ($this->invoice) {
                $order->update_meta_data('_wcfb_invoice_id', $this->invoice->getId());
                $order->save();
            }

            if ($this->setting('sendToEmail')) {
                $this->invoice->sendToEMail($email);
            }
        } catch (\Throwable $th) {
            $this->debug($th->getMessage(), 'CRITICAL');
        }
    }

    /**
     * @param int $orderId
     * @return void
     */
    public function paymentCompleted($orderId)
    {
        if (!$conn = $this->callFunc('initFbConnection', true)) return;
        
        if (!$this->invoice) {
            $order = wc_get_order($orderId);
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
            } catch (\Throwable $th) {
                $this->debug($th->getMessage(), 'CRITICAL');
            }
        }
    }
}