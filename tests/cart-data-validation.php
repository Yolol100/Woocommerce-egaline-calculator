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

$valid = array(
    'needed_kg' => 10.0,
    'bags' => 2,
    'total_price' => 60.0,
    'kg_per_bag' => 25.0,
    'thickness' => 5.0,
    'area' => 2.0,
    'regular_price' => 30.0,
);
$nested_invalid = array(
    'non-scalar price' => array_replace( $valid, array( 'total_price' => array( 60 ) ) ),
    'non-scalar bags' => array_replace( $valid, array( 'bags' => array( 2 ) ) ),
    'infinite price' => array_replace( $valid, array( 'total_price' => INF ) ),
    'fractional bags' => array_replace( $valid, array( 'bags' => 2.5 ) ),
);
foreach ( $nested_invalid as $label => $data ) {
    $cart_item = array(
        'product_id' => 123,
        'quantity' => 1,
        'data' => new WC_Product_Simple(),
        'calculator_data' => $data,
    );
    $expected = array( 'item' => $cart_item );
    $actual = $calculator->fix_minicart_price_display( $expected );
    if ( $actual !== $expected ) {
        throw new RuntimeException( 'Invalid nested cart data was applied: ' . $label );
    }
}

echo "Egaline malformed cart item regression: OK\n";
