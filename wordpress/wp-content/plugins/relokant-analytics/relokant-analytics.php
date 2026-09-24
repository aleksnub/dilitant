<?php
/**
 * Plugin Name: Relokant Analytics
 * Description: Google Analytics / Google Ads tracking for Relokant projects.
 * Version: 1.2.0
 * Author: Relokant
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Relokant_Analytics
{
    private const GA_MEASUREMENT_ID = 'G-78Z4FY0NH4';

    private const CROSS_DOMAIN_LINKER_DOMAINS = [
        'relokant.am',
    ];

    public static function init(): void
    {
        add_action('wp_head', [self::class, 'print_google_tag'], 1);
        add_action('woocommerce_thankyou', [self::class, 'print_purchase_event'], 20);
    }

    public static function print_google_tag(): void
    {
        ?>
        <!-- Relokant Analytics: Google tag -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr(self::GA_MEASUREMENT_ID); ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}

            gtag('js', new Date());

            gtag('config', '<?php echo esc_js(self::GA_MEASUREMENT_ID); ?>', {
                linker: {
                    domains: <?php echo wp_json_encode(self::CROSS_DOMAIN_LINKER_DOMAINS); ?>
                }
            });
        </script>
        <?php
    }

    public static function print_purchase_event($order_id): void
    {
        if (!function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($order_id);

        if (!$order || !$order->is_paid()) {
            return;
        }

        if ($order->get_meta('_relokant_ga4_purchase_sent', true)) {
            return;
        }

        $items = [];

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();

            $items[] = [
                'item_id' => $product ? (string) $product->get_id() : '',
                'item_name' => $item->get_name(),
                'quantity' => (int) $item->get_quantity(),
                'price' => (float) $order->get_item_total($item, false),
            ];
        }

        $payload = [
            'transaction_id' => (string) $order->get_id(),
            'value' => (float) $order->get_total(),
            'tax' => (float) $order->get_total_tax(),
            'shipping' => (float) $order->get_shipping_total(),
            'currency' => $order->get_currency() ?: 'AMD',
            'items' => $items,
        ];

        $order->update_meta_data('_relokant_ga4_purchase_sent', '1');
        $order->save();

        ?>
        <!-- Relokant Analytics: GA4 purchase -->
        <script>
            gtag('event', 'purchase', <?php echo wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>);
        </script>
        <?php
    }
}

Relokant_Analytics::init();