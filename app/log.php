<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

echo "Level: " . esc_html($level) . "<br>";
echo "Log date: " . esc_html($date) . "<br>";
echo "Message: " . esc_html($message) . "<br>";
echo "Context: " . esc_html(print_r($context)) . "<br>";
echo "----------------------------------------<br>";
