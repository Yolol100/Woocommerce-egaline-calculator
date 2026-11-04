<?php
/**
 * Isolated WooCommerce cart-data resilience checks.
 *
 * Executed after activating the plugin in a disposable WordPress installation.
 */

if ( ! class_exists( 'Egaline_Calculator_Cart' ) ) {
    throw new RuntimeException( 'Egaline cart hooks were not loaded.' );
}

$calculator = new Egaline_Calculator_Cart();
$invalid = array( 'not-an-array', 123, new stdClass(), array() );

foreach ( $invalid as $index => $payload ) {
    $cart_item = array(
        'product_id' => 123,
        'quantity' => 1,
        'calculator_data' => $payload,
    );
    $expected = $cart_item;

    $mini_cart = $calculator->fix_minicart_price_display( array( 'item' => $cart_item ) );
    if ( $mini_cart !== array( 'item' => $expected ) ) {
        throw new RuntimeException( 'Malformed minicart item was changed: ' . $index );
    }

    $session = $calculator->restore_cart_item_data( $cart_item, $cart_item );
    if ( $session !== $expected ) {
        throw new RuntimeException( 'Malformed session item was changed: ' . $index );
    }

    if ( $calculator->filter_cart_item_price( 'original', $cart_item, 'item' ) !== 'original' ) {
        throw new RuntimeException( 'Malformed cart price was changed: ' . $index );
    }
    if ( $calculator->filter_cart_item_subtotal( 'original', $cart_item, 'item' ) !== 'original' ) {
        throw new RuntimeException( 'Malformed cart subtotal was changed: ' . $index );
    }
}

echo "Egaline malformed cart item regression: OK\n";
