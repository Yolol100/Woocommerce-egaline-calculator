<?php
use PHPUnit\Framework\TestCase;

final class EgalineCalculatorCartTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../includes/class-calculator-cart.php';
    }

    public function test_discount_calculation_applies_threshold(): void
    {
        // Setup product meta
        $product_id = 1;
        $GLOBALS['test_post_meta'][$product_id] = [
            '_enable_calculator' => 'yes',
            '_kg_per_bag' => 25,
            '_kg_per_mm' => 10,
            '_kg_per_m2' => 2,
            '_discount_threshold' => 5,
            '_discount_percentage' => 10,
        ];

        // Setup product
        $product = new class {
            public function get_price() { return 10; }
        };
        $GLOBALS['test_products'][$product_id] = $product;

        // Post data
        $_POST['egaline_mm'] = '5';
        $_POST['egaline_m2'] = '3';

        $cart = new Egaline_Calculator_Cart();
        $result = $cart->add_calculator_data_to_cart([], $product_id);
        $data = $result['calculator_data'];

        $this->assertSame(156.0, $data['needed_kg']);
        $this->assertSame(7, $data['bags']);
        $this->assertSame(63.0, $data['total_price']);
        $this->assertSame(7.0, $data['discount_amount']);
    }
}
