<?php
use PHPUnit\Framework\TestCase;

final class CalculatorDataExampleTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../includes/class-calculator-cart.php';
    }

    public function test_calculator_data_no_discount(): void
    {
        $product_id = 2;
        $GLOBALS['test_post_meta'][$product_id] = [
            '_enable_calculator' => 'yes',
            '_kg_per_bag' => 20,
            '_kg_per_mm' => 8,
            '_kg_per_m2' => 1,
            '_discount_threshold' => 5,
            '_discount_percentage' => 15,
        ];

        $product = new class {
            public function get_price() { return 5; }
        };
        $GLOBALS['test_products'][$product_id] = $product;

        $_POST['egaline_mm'] = '4';
        $_POST['egaline_m2'] = '2';

        $cart = new Egaline_Calculator_Cart();
        $result = $cart->add_calculator_data_to_cart([], $product_id);
        $data = $result['calculator_data'];

        $this->assertSame(66.0, $data['needed_kg']);
        $this->assertSame(4, $data['bags']);
        $this->assertSame(20.0, $data['total_price']);
        $this->assertSame(0.0, $data['discount_amount']);
    }
}
