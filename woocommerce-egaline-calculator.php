<?php
/**
 * Plugin Name: WooCommerce Egaline Calculator
 * Plugin URI: https://webactueel.nl/
 * Description: Voegt een professionele egalinecalculator toe aan WooCommerce-producten.
 * Version: 1.0.9
 * Author: Webactueel
 * Author URI: https://webactueel.nl/
 * Text Domain: egaline-calculator
 * Domain Path: /languages
 * Requires PHP: 8.2
 * Requires at least: 6.8
 * Tested up to: 6.9.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 10.7.0
 * Network: false
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Webactueel\EgalineCalculator;

if (!defined('ABSPATH')) {
    exit;
}

final class EgalineCalculatorInit
{
    private const VERSION = '1.0.9';
    private const TEXT_DOMAIN = 'egaline-calculator';
    private const ADMIN_PRODUCTS_PAGE_SLUG = 'egaline-calculator-products';
    private const ADMIN_SETTINGS_PAGE_SLUG = 'egaline-calculator-settings';
    private const OPTION_ADMIN_BAR_ENABLED = 'egaline_calculator_admin_bar_enabled';
    private const OPTION_ITEMS_PER_PAGE = 'egaline_calculator_items_per_page';
    private const OPTION_SETTINGS = 'egaline_calculator_settings';
    private const DEFAULT_ADMIN_ITEMS_PER_PAGE = 20;
    private const MIN_ADMIN_ITEMS_PER_PAGE = 5;
    private const MAX_ADMIN_ITEMS_PER_PAGE = 100;

    private string $pluginPath;
    private string $pluginUrl;

    public function __construct()
    {
        $this->pluginPath = \plugin_dir_path(__FILE__);
        $this->pluginUrl = \plugin_dir_url(__FILE__);
        $this->includeRequiredFiles();
        $this->registerHooks();
    }

    private function includeRequiredFiles(): void
    {
        foreach (['includes/class-calculator-display.php', 'includes/class-calculator-metabox.php', 'includes/class-calculator-cart.php'] as $file) {
            $filePath = $this->pluginPath . $file;
            if (\is_readable($filePath)) {
                require_once $filePath;
            } else {
                $this->logError("Bestand niet gevonden of niet leesbaar: {$filePath}");
            }
        }
    }

    private function registerHooks(): void
    {
        \add_action('before_woocommerce_init', [$this, 'declareWooCommerceFeatureCompatibility']);
        \add_action('plugins_loaded', [$this, 'loadTextDomain']);
        \add_action('plugins_loaded', [$this, 'checkDependencies']);
        \add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        \add_action('admin_menu', [$this, 'registerAdminMenu']);
        \add_action('admin_init', [$this, 'handleAdminSettingsPosts']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueueAdminPageAssets']);
        \add_action('admin_bar_menu', [$this, 'addAdminBarMenu'], 999);
    }

    public function loadTextDomain(): void
    {
        \load_plugin_textdomain(self::TEXT_DOMAIN, false, \dirname(\plugin_basename(__FILE__)) . '/languages');
    }

    public function declareWooCommerceFeatureCompatibility(): void
    {
        if (\class_exists('\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }

    public function checkDependencies(): void
    {
        if (!\class_exists('WooCommerce')) {
            \add_action('admin_notices', static function (): void {
                echo '<div class="notice notice-error"><p>' . \esc_html__('WooCommerce Egaline Calculator vereist dat WooCommerce actief is.', self::TEXT_DOMAIN) . '</p></div>';
            });
        }
    }

    public function registerAdminMenu(): void
    {
        if (!$this->currentUserCanAccessCalculatorMenu()) {
            return;
        }
        $capability = $this->getAdminMenuCapability();
        \add_menu_page(\esc_html__('Egaline Calculator', self::TEXT_DOMAIN), \esc_html__('Egaline Calculator', self::TEXT_DOMAIN), $capability, self::ADMIN_PRODUCTS_PAGE_SLUG, [$this, 'renderEnabledProductsAdminPage'], 'dashicons-clipboard', 56);
        \add_submenu_page(self::ADMIN_PRODUCTS_PAGE_SLUG, \esc_html__('Producten met calculator', self::TEXT_DOMAIN), \esc_html__('Producten', self::TEXT_DOMAIN), $capability, self::ADMIN_PRODUCTS_PAGE_SLUG, [$this, 'renderEnabledProductsAdminPage']);
        \add_submenu_page(self::ADMIN_PRODUCTS_PAGE_SLUG, \esc_html__('Egaline Calculator instellingen', self::TEXT_DOMAIN), \esc_html__('Instellingen', self::TEXT_DOMAIN), $capability, self::ADMIN_SETTINGS_PAGE_SLUG, [$this, 'renderSettingsAdminPage']);
    }

    public function renderEnabledProductsAdminPage(): void
    {
        if (!$this->currentUserCanAccessCalculatorMenu()) {
            \wp_die(\esc_html__('Je hebt geen rechten om deze pagina te bekijken.', self::TEXT_DOMAIN));
        }
        $paged = isset($_GET['paged']) ? \max(1, \absint(\wp_unslash($_GET['paged']))) : 1;
        $searchTerm = $this->getProductsAdminSearchTerm();
        $statusFilter = $this->getProductsAdminStatusFilter();
        if ($statusFilter === '' && !isset($_GET['product_status'])) {
            $statusFilter = $this->getDefaultProductsStatusFilter();
        }
        $postStatuses = $statusFilter !== '' ? [$statusFilter] : ['publish', 'draft', 'pending', 'private'];
        $defaultSort = $this->getProductsDefaultSort();
        $queryArgs = [
            'post_type' => 'product',
            'post_status' => $postStatuses,
            'posts_per_page' => $this->getAdminItemsPerPage(),
            'paged' => $paged,
            'orderby' => \in_array($defaultSort, ['price', 'stock'], true) ? 'meta_value_num' : $defaultSort,
            'order' => \in_array($defaultSort, ['price', 'stock'], true) ? 'DESC' : 'ASC',
            'ignore_sticky_posts' => true,
            'meta_query' => [[ 'key' => '_enable_calculator', 'value' => 'yes', 'compare' => '=' ]],
        ];
        if (\in_array($defaultSort, ['price', 'stock'], true)) {
            $queryArgs['meta_key'] = $defaultSort === 'price' ? '_price' : '_stock';
        }
        if ($searchTerm !== '') {
            $queryArgs['s'] = $searchTerm;
        }
        $query = new \WP_Query($queryArgs);
        $total = (int) $query->found_posts;
        $totalPages = \max(1, (int) $query->max_num_pages);
        $allEnabledCount = $this->countEnabledCalculatorProducts();
        $statusCounts = $this->countEnabledCalculatorProductsByStatus();
        $productTypeLabels = \function_exists('wc_get_product_types') ? \wc_get_product_types() : [];
        $showThumbColumn = $this->getBooleanSetting('admin_show_image_column', true);
        $showTypeColumn = $this->getBooleanSetting('admin_show_type_column', true);
        $showPriceColumn = $this->getBooleanSetting('admin_show_price_column', true);
        $showStockColumn = $this->getBooleanSetting('admin_show_stock_column', true);
        $baseUrl = \admin_url('admin.php?page=' . self::ADMIN_PRODUCTS_PAGE_SLUG);
        ?>
        <div class="wrap egaline-calculator-admin-page <?php echo \esc_attr($this->getAdminStyleClass()); ?>">
            <h1 class="wp-heading-inline"><?php echo \esc_html__('Egaline Calculator', self::TEXT_DOMAIN); ?></h1>
            <a href="<?php echo \esc_url(\admin_url('post-new.php?post_type=product')); ?>" class="page-title-action"><?php echo \esc_html__('Nieuw product toevoegen', self::TEXT_DOMAIN); ?></a>
            <a href="<?php echo \esc_url(\admin_url('edit.php?post_type=product')); ?>" class="page-title-action"><?php echo \esc_html__('Alle producten', self::TEXT_DOMAIN); ?></a>
            <hr class="wp-header-end">

            <ul class="subsubsub">
                <li class="all"><a href="<?php echo \esc_url($baseUrl); ?>" class="<?php echo $statusFilter === '' ? 'current' : ''; ?>"><?php echo \esc_html__('Alle', self::TEXT_DOMAIN); ?> <span class="count">(<?php echo \esc_html(\number_format_i18n($allEnabledCount)); ?>)</span></a></li>
                <?php foreach (['publish', 'draft', 'pending', 'private'] as $statusName) : ?>
                    <?php $count = $statusCounts[$statusName] ?? 0; if ($count < 1) { continue; } $statusObject = \get_post_status_object($statusName); ?>
                    <li class="<?php echo \esc_attr($statusName); ?>"> | <a href="<?php echo \esc_url(\add_query_arg('product_status', $statusName, $baseUrl)); ?>" class="<?php echo $statusFilter === $statusName ? 'current' : ''; ?>"><?php echo \esc_html($statusObject ? $statusObject->label : $statusName); ?> <span class="count">(<?php echo \esc_html(\number_format_i18n($count)); ?>)</span></a></li>
                <?php endforeach; ?>
            </ul>

            <form method="get">
                <input type="hidden" name="page" value="<?php echo \esc_attr(self::ADMIN_PRODUCTS_PAGE_SLUG); ?>">
                <?php if ($statusFilter !== '') : ?><input type="hidden" name="product_status" value="<?php echo \esc_attr($statusFilter); ?>"><?php endif; ?>
                <p class="search-box">
                    <label class="screen-reader-text" for="egaline-product-search-input"><?php echo \esc_html__('Producten zoeken', self::TEXT_DOMAIN); ?></label>
                    <input type="search" id="egaline-product-search-input" name="s" value="<?php echo \esc_attr($searchTerm); ?>">
                    <input type="submit" class="button" value="<?php echo \esc_attr__('Producten zoeken', self::TEXT_DOMAIN); ?>">
                    <?php if ($searchTerm !== '') : ?><a class="button" href="<?php echo \esc_url($statusFilter !== '' ? \add_query_arg('product_status', $statusFilter, $baseUrl) : $baseUrl); ?>"><?php echo \esc_html__('Zoekfilter wissen', self::TEXT_DOMAIN); ?></a><?php endif; ?>
                </p>

                <div class="tablenav top">
                    <div class="alignleft actions">
                        <label class="screen-reader-text" for="filter-by-product-status"><?php echo \esc_html__('Filter op productstatus', self::TEXT_DOMAIN); ?></label>
                        <select name="product_status" id="filter-by-product-status">
                            <option value=""><?php echo \esc_html__('Alle statussen', self::TEXT_DOMAIN); ?></option>
                            <?php foreach (['publish', 'draft', 'pending', 'private'] as $statusName) : $statusObject = \get_post_status_object($statusName); ?>
                                <option value="<?php echo \esc_attr($statusName); ?>" <?php \selected($statusFilter, $statusName); ?>><?php echo \esc_html($statusObject ? $statusObject->label : $statusName); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="submit" class="button" value="<?php echo \esc_attr__('Filteren', self::TEXT_DOMAIN); ?>">
                        <?php if ($statusFilter !== '') : ?><a class="button" href="<?php echo \esc_url($searchTerm !== '' ? \add_query_arg('s', $searchTerm, $baseUrl) : $baseUrl); ?>"><?php echo \esc_html__('Statusfilter wissen', self::TEXT_DOMAIN); ?></a><?php endif; ?>
                    </div>
                    <div class="tablenav-pages one-page"><span class="displaying-num"><?php printf(\esc_html(\_n('%s item', '%s items', $total, self::TEXT_DOMAIN)), \esc_html(\number_format_i18n($total))); ?></span></div>
                    <br class="clear">
                </div>

                <table class="wp-list-table widefat fixed striped table-view-list posts">
                    <caption class="screen-reader-text"><?php echo \esc_html__('Overzicht van WooCommerce producten waarbij de Egaline Calculator actief is.', self::TEXT_DOMAIN); ?></caption>
                    <thead><tr>
                        <?php if ($showThumbColumn) : ?><th scope="col" class="manage-column column-thumb"><?php echo \esc_html__('Afbeelding', self::TEXT_DOMAIN); ?></th><?php endif; ?>
                        <th scope="col" class="manage-column column-primary column-title"><?php echo \esc_html__('Product', self::TEXT_DOMAIN); ?></th>
                        <th scope="col" class="manage-column"><?php echo \esc_html__('Status', self::TEXT_DOMAIN); ?></th>
                        <?php if ($showTypeColumn) : ?><th scope="col" class="manage-column"><?php echo \esc_html__('Type', self::TEXT_DOMAIN); ?></th><?php endif; ?>
                        <?php if ($showPriceColumn) : ?><th scope="col" class="manage-column"><?php echo \esc_html__('Prijs', self::TEXT_DOMAIN); ?></th><?php endif; ?>
                        <?php if ($showStockColumn) : ?><th scope="col" class="manage-column"><?php echo \esc_html__('Voorraad', self::TEXT_DOMAIN); ?></th><?php endif; ?>
                        <th scope="col" class="manage-column"><?php echo \esc_html__('Acties', self::TEXT_DOMAIN); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php if ($query->have_posts()) : ?>
                        <?php while ($query->have_posts()) : $query->the_post(); ?>
                            <?php
                            $productId = \get_the_ID();
                            $product = \function_exists('wc_get_product') ? \wc_get_product($productId) : null;
                            $statusObject = \get_post_status_object(\get_post_status($productId));
                            $productType = $product ? $product->get_type() : '';
                            $productTypeLabel = $productType !== '' && isset($productTypeLabels[$productType]) ? $productTypeLabels[$productType] : $productType;
                            $editLink = \get_edit_post_link($productId, 'raw');
                            $calculatorSettingsLink = $editLink ? $editLink . '#woocommerce-product-data' : '';
                            $viewLink = \get_permalink($productId);
                            ?>
                            <tr>
                                <?php if ($showThumbColumn) : ?><td class="thumb column-thumb" data-colname="<?php echo \esc_attr__('Afbeelding', self::TEXT_DOMAIN); ?>"><?php if (\has_post_thumbnail($productId)) : echo \wp_kses_post(\get_the_post_thumbnail($productId, [40, 40])); else : ?><span class="dashicons dashicons-format-image" aria-hidden="true"></span><span class="screen-reader-text"><?php echo \esc_html__('Geen productafbeelding', self::TEXT_DOMAIN); ?></span><?php endif; ?></td><?php endif; ?>
                                <td class="title column-title has-row-actions column-primary" data-colname="<?php echo \esc_attr__('Product', self::TEXT_DOMAIN); ?>"><strong><?php if ($editLink) : ?><a class="row-title" href="<?php echo \esc_url($editLink); ?>"><?php echo \esc_html(\get_the_title($productId)); ?></a><?php else : echo \esc_html(\get_the_title($productId)); endif; ?></strong><div class="row-actions"><?php if ($editLink) : ?><span class="edit"><a href="<?php echo \esc_url($editLink); ?>"><?php echo \esc_html__('Bewerken', self::TEXT_DOMAIN); ?></a></span><?php endif; ?><?php if ($calculatorSettingsLink) : ?><span class="settings"> | <a href="<?php echo \esc_url($calculatorSettingsLink); ?>"><?php echo \esc_html__('Calculator instellingen', self::TEXT_DOMAIN); ?></a></span><?php endif; ?><?php if ($viewLink) : ?><span class="view"> | <a href="<?php echo \esc_url($viewLink); ?>" target="_blank" rel="noopener noreferrer"><?php echo \esc_html__('Bekijken', self::TEXT_DOMAIN); ?></a></span><?php endif; ?></div><button type="button" class="toggle-row"><span class="screen-reader-text"><?php echo \esc_html__('Meer details tonen', self::TEXT_DOMAIN); ?></span></button></td>
                                <td data-colname="<?php echo \esc_attr__('Status', self::TEXT_DOMAIN); ?>"><span class="post-state"><?php echo \esc_html($statusObject ? $statusObject->label : \get_post_status($productId)); ?></span></td>
                                <?php if ($showTypeColumn) : ?><td data-colname="<?php echo \esc_attr__('Type', self::TEXT_DOMAIN); ?>"><?php echo $productTypeLabel !== '' ? \esc_html($productTypeLabel) : '&mdash;'; ?></td><?php endif; ?>
                                <?php if ($showPriceColumn) : ?><td data-colname="<?php echo \esc_attr__('Prijs', self::TEXT_DOMAIN); ?>"><?php echo $product ? \wp_kses_post($product->get_price_html()) : '&mdash;'; ?></td><?php endif; ?>
                                <?php if ($showStockColumn) : ?><td data-colname="<?php echo \esc_attr__('Voorraad', self::TEXT_DOMAIN); ?>"><?php echo $product && \function_exists('wc_get_stock_html') ? \wp_kses_post(\wc_get_stock_html($product)) : '&mdash;'; ?></td><?php endif; ?>
                                <td data-colname="<?php echo \esc_attr__('Acties', self::TEXT_DOMAIN); ?>"><?php if ($calculatorSettingsLink) : ?><a class="button button-small" href="<?php echo \esc_url($calculatorSettingsLink); ?>"><?php echo \esc_html__('Openen', self::TEXT_DOMAIN); ?></a><?php else : ?>&mdash;<?php endif; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <tr><td colspan="<?php echo \esc_attr((string) (3 + ($showThumbColumn ? 1 : 0) + ($showTypeColumn ? 1 : 0) + ($showPriceColumn ? 1 : 0) + ($showStockColumn ? 1 : 0))); ?>"><?php echo $searchTerm !== '' ? \esc_html__('Geen producten gevonden voor deze zoekopdracht.', self::TEXT_DOMAIN) : \esc_html__('Er zijn nog geen producten waarbij de Egaline Calculator actief is.', self::TEXT_DOMAIN); ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </form>
            <?php if ($totalPages > 1) : ?><div class="tablenav bottom"><div class="tablenav-pages"><?php echo \wp_kses_post(\paginate_links(['base' => \add_query_arg(['page' => self::ADMIN_PRODUCTS_PAGE_SLUG, 's' => $searchTerm, 'product_status' => $statusFilter, 'paged' => '%#%'], \admin_url('admin.php')), 'format' => '', 'current' => $paged, 'total' => $totalPages, 'prev_text' => '&lsaquo;', 'next_text' => '&rsaquo;'])); ?></div><br class="clear"></div><?php endif; ?>
        </div>
        <?php
        \wp_reset_postdata();
    }

    public function handleAdminSettingsPosts(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { return; }
        $page = isset($_GET['page']) ? \sanitize_key(\wp_unslash((string) $_GET['page'])) : '';
        if ($page !== self::ADMIN_SETTINGS_PAGE_SLUG) { return; }
        if (!$this->currentUserCanAccessCalculatorMenu()) { \wp_die(\esc_html__('Je hebt geen rechten om deze instellingen op te slaan.', self::TEXT_DOMAIN)); }
        \check_admin_referer('egaline_calculator_save_settings');

        $settings = $this->getDefaultSettings();
        foreach ($settings as $key => $defaultValue) {
            if (\is_bool($defaultValue)) { $settings[$key] = isset($_POST[$key]) && \sanitize_key(\wp_unslash((string) $_POST[$key])) === 'yes'; continue; }
            if (\is_int($defaultValue)) { $settings[$key] = isset($_POST[$key]) ? \absint(\wp_unslash($_POST[$key])) : $defaultValue; continue; }
            if (\is_float($defaultValue)) { $raw = isset($_POST[$key]) ? \sanitize_text_field(\wp_unslash((string) $_POST[$key])) : (string) $defaultValue; $settings[$key] = \is_numeric(\str_replace(',', '.', $raw)) ? (float) \str_replace(',', '.', $raw) : $defaultValue; continue; }
            $settings[$key] = isset($_POST[$key]) ? \sanitize_text_field(\wp_unslash((string) $_POST[$key])) : (string) $defaultValue;
        }
        $settings['items_per_page'] = \min(self::MAX_ADMIN_ITEMS_PER_PAGE, \max(self::MIN_ADMIN_ITEMS_PER_PAGE, (int) $settings['items_per_page']));
        $settings['max_mm'] = \min(100000.0, \max(0.1, (float) $settings['max_mm']));
        $settings['max_m2'] = \min(100000.0, \max(0.1, (float) $settings['max_m2']));
        $settings['min_bags'] = \min(100000, \max(1, (int) $settings['min_bags']));
        $settings['max_bags'] = \min(100000, \max((int) $settings['min_bags'], (int) $settings['max_bags']));
        $settings['price_decimals'] = \min(4, \max(0, (int) $settings['price_decimals']));
        $settings['default_kg_per_bag'] = \max(1.0, (float) $settings['default_kg_per_bag']);
        $settings['default_kg_per_mm'] = \max(0.0, (float) $settings['default_kg_per_mm']);
        $settings['default_kg_per_m2'] = \max(0.0, (float) $settings['default_kg_per_m2']);
        $settings['default_discount_threshold'] = \max(0, (int) $settings['default_discount_threshold']);
        $settings['default_discount_percentage'] = \min(100.0, \max(0.0, (float) $settings['default_discount_percentage']));
        $settings['default_sort'] = \in_array($settings['default_sort'], ['title', 'date', 'price', 'stock'], true) ? $settings['default_sort'] : 'title';
        $settings['default_status'] = \in_array($settings['default_status'], ['', 'publish', 'draft', 'pending', 'private'], true) ? $settings['default_status'] : '';
        \update_option(self::OPTION_SETTINGS, $settings, false);
        \update_option(self::OPTION_ITEMS_PER_PAGE, (int) $settings['items_per_page'], false);
        \update_option(self::OPTION_ADMIN_BAR_ENABLED, $settings['admin_bar_enabled'] ? 'yes' : 'no', false);
        \wp_safe_redirect(\add_query_arg(['page' => self::ADMIN_SETTINGS_PAGE_SLUG, 'updated' => 'true'], \admin_url('admin.php')));
        exit;
    }

    public function renderSettingsAdminPage(): void
    {
        if (!$this->currentUserCanAccessCalculatorMenu()) { \wp_die(\esc_html__('Je hebt geen rechten om deze pagina te bekijken.', self::TEXT_DOMAIN)); }
        $settings = $this->getSettings();
        ?>
        <div class="wrap egaline-calculator-admin-page <?php echo \esc_attr($this->getAdminStyleClass()); ?>">
            <h1><?php echo \esc_html__('Egaline Calculator instellingen', self::TEXT_DOMAIN); ?></h1>
            <?php if (isset($_GET['updated']) && $_GET['updated'] === 'true') : ?><div class="notice notice-success is-dismissible"><p><?php echo \esc_html__('Instellingen opgeslagen.', self::TEXT_DOMAIN); ?></p></div><?php endif; ?>
            <form method="post" action="" class="egaline-settings-form">
                <?php \wp_nonce_field('egaline_calculator_save_settings'); ?>
                <div class="egaline-admin-shell">
                    <div class="egaline-admin-card">
                        <?php $this->renderSettingsSectionFrontend($settings); ?>
                        <?php $this->renderSettingsSectionCalculation($settings); ?>
                        <?php $this->renderSettingsSectionDefaults($settings); ?>
                        <?php $this->renderSettingsSectionProductOverview($settings); ?>
                        <?php $this->renderSettingsSectionWooCommerce($settings); ?>
                        <?php \submit_button(\esc_html__('Instellingen opslaan', self::TEXT_DOMAIN)); ?>
                    </div>
                    <div class="egaline-admin-side-card">
                        <h2><?php echo \esc_html__('Snelle acties', self::TEXT_DOMAIN); ?></h2>
                        <p><a class="button button-primary" href="<?php echo \esc_url(\admin_url('admin.php?page=' . self::ADMIN_PRODUCTS_PAGE_SLUG)); ?>"><?php echo \esc_html__('Bekijk producten', self::TEXT_DOMAIN); ?></a></p>
                        <p><a class="button" href="<?php echo \esc_url(\admin_url('post-new.php?post_type=product')); ?>"><?php echo \esc_html__('Nieuw product toevoegen', self::TEXT_DOMAIN); ?></a></p>
                        <p><a class="button" href="<?php echo \esc_url(\admin_url('edit.php?post_type=product')); ?>"><?php echo \esc_html__('Alle WooCommerce producten', self::TEXT_DOMAIN); ?></a></p>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    private function renderSettingsSectionFrontend(array $s): void { ?>
        <h2><?php echo \esc_html__('Frontend-weergave', self::TEXT_DOMAIN); ?></h2><table class="form-table" role="presentation"><tbody>
        <?php $this->renderCheckboxRow('show_total_price', __('Toon totaalprijs', self::TEXT_DOMAIN), $s['show_total_price']); ?><?php $this->renderCheckboxRow('show_discount_text', __('Toon kortingstekst', self::TEXT_DOMAIN), $s['show_discount_text']); ?><?php $this->renderCheckboxRow('show_dark_mode_button', __('Toon dark mode knop', self::TEXT_DOMAIN), $s['show_dark_mode_button']); ?><?php $this->renderCheckboxRow('show_reset_button', __('Toon reset knop', self::TEXT_DOMAIN), $s['show_reset_button']); ?><?php $this->renderCheckboxRow('default_dark_mode', __('Dark mode standaard aan', self::TEXT_DOMAIN), $s['default_dark_mode']); ?><?php $this->renderTextRow('intro_text', __('Tekst boven calculator', self::TEXT_DOMAIN), $s['intro_text']); ?><?php $this->renderTextRow('label_mm', __('Label dikte/mm', self::TEXT_DOMAIN), $s['label_mm']); ?><?php $this->renderTextRow('label_m2', __('Label m²', self::TEXT_DOMAIN), $s['label_m2']); ?><?php $this->renderTextRow('label_bags', __('Label aantal zakken', self::TEXT_DOMAIN), $s['label_bags']); ?><?php $this->renderTextRow('tax_label', __('Prijsregel onder totaalprijs', self::TEXT_DOMAIN), $s['tax_label']); ?></tbody></table><?php }

    private function renderSettingsSectionCalculation(array $s): void { ?>
        <h2><?php echo \esc_html__('Berekening en limieten', self::TEXT_DOMAIN); ?></h2><table class="form-table" role="presentation"><tbody>
        <?php $this->renderCheckboxRow('round_up_bags', __('Rond zakken naar boven af', self::TEXT_DOMAIN), $s['round_up_bags']); ?><?php $this->renderNumberRow('min_bags', __('Minimaal aantal zakken', self::TEXT_DOMAIN), $s['min_bags'], 1, 100000, 1); ?><?php $this->renderNumberRow('max_bags', __('Maximaal aantal zakken', self::TEXT_DOMAIN), $s['max_bags'], 1, 100000, 1); ?><?php $this->renderNumberRow('max_m2', __('Maximale m² invoer', self::TEXT_DOMAIN), $s['max_m2'], 0.1, 100000, 0.1); ?><?php $this->renderNumberRow('max_mm', __('Maximale mm invoer', self::TEXT_DOMAIN), $s['max_mm'], 0.1, 100000, 0.1); ?><?php $this->renderNumberRow('price_decimals', __('Aantal decimalen bij totaalprijs', self::TEXT_DOMAIN), $s['price_decimals'], 0, 4, 1); ?><?php $this->renderCheckboxRow('require_valid_calculation', __('Blokkeer winkelwagen bij ongeldige calculatorwaarden', self::TEXT_DOMAIN), $s['require_valid_calculation']); ?></tbody></table><?php }

    private function renderSettingsSectionDefaults(array $s): void { ?>
        <h2><?php echo \esc_html__('Standaard productwaarden', self::TEXT_DOMAIN); ?></h2><table class="form-table" role="presentation"><tbody>
        <?php $this->renderCheckboxRow('default_enable_calculator', __('Nieuwe producten standaard calculator actief', self::TEXT_DOMAIN), $s['default_enable_calculator']); ?><?php $this->renderNumberRow('default_kg_per_bag', __('Standaard kg per zak', self::TEXT_DOMAIN), $s['default_kg_per_bag'], 1, 100000, 0.1); ?><?php $this->renderNumberRow('default_kg_per_mm', __('Standaard kg per mm', self::TEXT_DOMAIN), $s['default_kg_per_mm'], 0, 100000, 0.01); ?><?php $this->renderNumberRow('default_kg_per_m2', __('Standaard kg per m²', self::TEXT_DOMAIN), $s['default_kg_per_m2'], 0, 100000, 0.01); ?><?php $this->renderNumberRow('default_discount_threshold', __('Standaard kortingsdrempel', self::TEXT_DOMAIN), $s['default_discount_threshold'], 0, 100000, 1); ?><?php $this->renderNumberRow('default_discount_percentage', __('Standaard kortingspercentage', self::TEXT_DOMAIN), $s['default_discount_percentage'], 0, 100, 0.1); ?></tbody></table><?php }

    private function renderSettingsSectionProductOverview(array $s): void { ?>
        <h2><?php echo \esc_html__('Productoverzicht', self::TEXT_DOMAIN); ?></h2><table class="form-table" role="presentation"><tbody>
        <?php $this->renderNumberRow('items_per_page', __('Producten per pagina', self::TEXT_DOMAIN), $s['items_per_page'], self::MIN_ADMIN_ITEMS_PER_PAGE, self::MAX_ADMIN_ITEMS_PER_PAGE, 1); ?><?php $this->renderCheckboxRow('admin_show_image_column', __('Toon afbeelding-kolom', self::TEXT_DOMAIN), $s['admin_show_image_column']); ?><?php $this->renderCheckboxRow('admin_show_stock_column', __('Toon voorraad-kolom', self::TEXT_DOMAIN), $s['admin_show_stock_column']); ?><?php $this->renderCheckboxRow('admin_show_price_column', __('Toon prijs-kolom', self::TEXT_DOMAIN), $s['admin_show_price_column']); ?><?php $this->renderCheckboxRow('admin_show_type_column', __('Toon producttype-kolom', self::TEXT_DOMAIN), $s['admin_show_type_column']); ?><?php $this->renderSelectRow('default_sort', __('Standaard sortering', self::TEXT_DOMAIN), $s['default_sort'], ['title' => __('Titel', self::TEXT_DOMAIN), 'date' => __('Datum', self::TEXT_DOMAIN), 'price' => __('Prijs', self::TEXT_DOMAIN), 'stock' => __('Voorraad', self::TEXT_DOMAIN)]); ?><?php $this->renderSelectRow('default_status', __('Standaard statusfilter', self::TEXT_DOMAIN), $s['default_status'], ['' => __('Alle statussen', self::TEXT_DOMAIN), 'publish' => __('Gepubliceerd', self::TEXT_DOMAIN), 'draft' => __('Concept', self::TEXT_DOMAIN), 'pending' => __('In afwachting', self::TEXT_DOMAIN), 'private' => __('Privé', self::TEXT_DOMAIN)]); ?><?php $this->renderCheckboxRow('admin_bar_enabled', __('Toon menu in zwarte WordPress adminbalk', self::TEXT_DOMAIN), $s['admin_bar_enabled']); ?></tbody></table><?php }

    private function renderSettingsSectionWooCommerce(array $s): void { ?>
        <h2><?php echo \esc_html__('Winkelwagen, checkout en orderregels', self::TEXT_DOMAIN); ?></h2><table class="form-table" role="presentation"><tbody>
        <?php $this->renderCheckboxRow('show_cart_data', __('Toon calculatorgegevens in winkelwagen', self::TEXT_DOMAIN), $s['show_cart_data']); ?><?php $this->renderCheckboxRow('show_checkout_data', __('Toon calculatorgegevens in checkout', self::TEXT_DOMAIN), $s['show_checkout_data']); ?><?php $this->renderCheckboxRow('show_order_data', __('Toon calculatorgegevens in bestelling/admin order', self::TEXT_DOMAIN), $s['show_order_data']); ?><?php $this->renderTextRow('order_label', __('Label voor orderregels', self::TEXT_DOMAIN), $s['order_label']); ?></tbody></table><?php }

    private function renderCheckboxRow(string $name, string $label, bool $checked): void { ?><tr><th scope="row"><?php echo \esc_html($label); ?></th><td><label><input type="checkbox" name="<?php echo \esc_attr($name); ?>" value="yes" <?php \checked($checked); ?>> <?php echo \esc_html__('Ingeschakeld', self::TEXT_DOMAIN); ?></label></td></tr><?php }
    private function renderTextRow(string $name, string $label, string $value): void { ?><tr><th scope="row"><label for="egaline-<?php echo \esc_attr($name); ?>"><?php echo \esc_html($label); ?></label></th><td><input type="text" class="regular-text" id="egaline-<?php echo \esc_attr($name); ?>" name="<?php echo \esc_attr($name); ?>" value="<?php echo \esc_attr($value); ?>"></td></tr><?php }
    private function renderNumberRow(string $name, string $label, int|float $value, int|float $min, int|float $max, int|float $step): void { ?><tr><th scope="row"><label for="egaline-<?php echo \esc_attr($name); ?>"><?php echo \esc_html($label); ?></label></th><td><input type="number" class="small-text" id="egaline-<?php echo \esc_attr($name); ?>" name="<?php echo \esc_attr($name); ?>" value="<?php echo \esc_attr((string) $value); ?>" min="<?php echo \esc_attr((string) $min); ?>" max="<?php echo \esc_attr((string) $max); ?>" step="<?php echo \esc_attr((string) $step); ?>"></td></tr><?php }
    private function renderSelectRow(string $name, string $label, string $value, array $options): void { ?><tr><th scope="row"><label for="egaline-<?php echo \esc_attr($name); ?>"><?php echo \esc_html($label); ?></label></th><td><select id="egaline-<?php echo \esc_attr($name); ?>" name="<?php echo \esc_attr($name); ?>"><?php foreach ($options as $optionValue => $optionLabel) : ?><option value="<?php echo \esc_attr((string) $optionValue); ?>" <?php \selected($value, (string) $optionValue); ?>><?php echo \esc_html((string) $optionLabel); ?></option><?php endforeach; ?></select></td></tr><?php }

    private function getDefaultSettings(): array
    {
        return [
            'items_per_page' => self::DEFAULT_ADMIN_ITEMS_PER_PAGE, 'admin_bar_enabled' => true,
            'show_total_price' => true, 'show_discount_text' => true, 'show_dark_mode_button' => true, 'show_reset_button' => true, 'default_dark_mode' => false,
            'intro_text' => __('Bereken eenvoudig hoeveel egaline u nodig heeft.', self::TEXT_DOMAIN), 'label_mm' => __('Hoe dik egaliseren in mm?', self::TEXT_DOMAIN), 'label_m2' => __('Aantal m² egaliseren?', self::TEXT_DOMAIN), 'label_bags' => __('Aantal zakken', self::TEXT_DOMAIN), 'tax_label' => __('Inclusief BTW', self::TEXT_DOMAIN),
            'round_up_bags' => true, 'min_bags' => 1, 'max_bags' => 100000, 'max_m2' => 100000.0, 'max_mm' => 100000.0, 'price_decimals' => 2, 'require_valid_calculation' => true,
            'default_enable_calculator' => false, 'default_kg_per_bag' => 1.0, 'default_kg_per_mm' => 0.0, 'default_kg_per_m2' => 0.0, 'default_discount_threshold' => 0, 'default_discount_percentage' => 0.0,
            'admin_show_image_column' => true, 'admin_show_stock_column' => true, 'admin_show_price_column' => true, 'admin_show_type_column' => true, 'default_sort' => 'title', 'default_status' => '',
            'show_cart_data' => true, 'show_checkout_data' => true, 'show_order_data' => true, 'order_label' => __('Egaline berekening', self::TEXT_DOMAIN),
        ];
    }

    private function getSettings(): array
    {
        $stored = \get_option(self::OPTION_SETTINGS, []);
        $settings = \is_array($stored) ? \array_merge($this->getDefaultSettings(), $stored) : $this->getDefaultSettings();
        $settings['items_per_page'] = \absint(\get_option(self::OPTION_ITEMS_PER_PAGE, $settings['items_per_page']));
        $settings['admin_bar_enabled'] = \get_option(self::OPTION_ADMIN_BAR_ENABLED, $settings['admin_bar_enabled'] ? 'yes' : 'no') !== 'no';
        return $settings;
    }

    private function getBooleanSetting(string $key, bool $default): bool { $settings = $this->getSettings(); return isset($settings[$key]) ? (bool) $settings[$key] : $default; }
    private function getStringSetting(string $key, string $default): string { $settings = $this->getSettings(); return isset($settings[$key]) ? (string) $settings[$key] : $default; }
    private function getProductsDefaultSort(): string { $sort = $this->getStringSetting('default_sort', 'title'); return \in_array($sort, ['title', 'date', 'price', 'stock'], true) ? $sort : 'title'; }
    private function getDefaultProductsStatusFilter(): string { $status = $this->getStringSetting('default_status', ''); return \in_array($status, ['publish', 'draft', 'pending', 'private'], true) ? $status : ''; }
    private function getAdminStyleClass(): string { return 'egaline-admin-style-combined'; }
    private function getAdminItemsPerPage(): int { $settings = $this->getSettings(); $items = \absint($settings['items_per_page']); return \min(self::MAX_ADMIN_ITEMS_PER_PAGE, \max(self::MIN_ADMIN_ITEMS_PER_PAGE, $items)); }
    private function isAdminBarEnabled(): bool { return $this->getBooleanSetting('admin_bar_enabled', true); }

    public function addAdminBarMenu(\WP_Admin_Bar $adminBar): void
    {
        if (!\is_admin_bar_showing() || !$this->isAdminBarEnabled() || !$this->currentUserCanAccessCalculatorMenu()) { return; }
        $adminBar->add_node(['id' => 'egaline-calculator', 'parent' => 'top-secondary', 'title' => \esc_html__('Egaline Calculator', self::TEXT_DOMAIN), 'href' => \esc_url(\admin_url('admin.php?page=' . self::ADMIN_PRODUCTS_PAGE_SLUG)), 'meta' => ['class' => 'egaline-calculator-admin-bar']]);
        $adminBar->add_node(['parent' => 'egaline-calculator', 'id' => 'egaline-calculator-enabled-products', 'title' => \esc_html__('Producten met calculator', self::TEXT_DOMAIN), 'href' => \esc_url(\admin_url('admin.php?page=' . self::ADMIN_PRODUCTS_PAGE_SLUG))]);
        $adminBar->add_node(['parent' => 'egaline-calculator', 'id' => 'egaline-calculator-settings', 'title' => \esc_html__('Instellingen', self::TEXT_DOMAIN), 'href' => \esc_url(\admin_url('admin.php?page=' . self::ADMIN_SETTINGS_PAGE_SLUG))]);
        $adminBar->add_node(['parent' => 'egaline-calculator', 'id' => 'egaline-calculator-products', 'title' => \esc_html__('Alle producten bekijken', self::TEXT_DOMAIN), 'href' => \esc_url(\admin_url('edit.php?post_type=product'))]);
        $adminBar->add_node(['parent' => 'egaline-calculator', 'id' => 'egaline-calculator-add-product', 'title' => \esc_html__('Nieuw product toevoegen', self::TEXT_DOMAIN), 'href' => \esc_url(\admin_url('post-new.php?post_type=product'))]);
    }

    private function currentUserCanAccessCalculatorMenu(): bool { return \current_user_can('edit_products') || \current_user_can('manage_woocommerce') || \current_user_can('manage_options'); }
    private function getAdminMenuCapability(): string { if (\current_user_can('edit_products')) { return 'edit_products'; } if (\current_user_can('manage_woocommerce')) { return 'manage_woocommerce'; } return 'manage_options'; }

    public function enqueueAssets(): void
    {
        if (!\is_product()) { return; }
        $handle = $this->enqueueScript('assets/js/calculator.js', ['jquery', 'wp-i18n'], true);
        if ($handle !== '') { \wp_set_script_translations($handle, self::TEXT_DOMAIN, $this->pluginPath . 'languages'); \wp_localize_script($handle, 'egalineCalculatorSettings', $this->getPublicScriptSettings()); }
        $this->enqueueStyle('assets/css/calculator.css');
    }

    private function getPublicScriptSettings(): array
    {
        $s = $this->getSettings();
        return ['showTotalPrice' => (bool) $s['show_total_price'], 'showDiscountText' => (bool) $s['show_discount_text'], 'defaultDarkMode' => (bool) $s['default_dark_mode'], 'labelMm' => (string) $s['label_mm'], 'labelM2' => (string) $s['label_m2'], 'labelBags' => (string) $s['label_bags'], 'roundUpBags' => (bool) $s['round_up_bags'], 'minBags' => (int) $s['min_bags'], 'maxBags' => (int) $s['max_bags'], 'maxM2' => (float) $s['max_m2'], 'maxMm' => (float) $s['max_mm'], 'priceDecimals' => (int) $s['price_decimals'], 'requireValidCalculation' => (bool) $s['require_valid_calculation']];
    }

    public function enqueueAdminPageAssets(): void
    {
        $page = isset($_GET['page']) ? \sanitize_key(\wp_unslash((string) $_GET['page'])) : '';
        if (!\in_array($page, [self::ADMIN_PRODUCTS_PAGE_SLUG, self::ADMIN_SETTINGS_PAGE_SLUG], true)) { return; }
        $this->enqueueStyle('assets/css/admin-calculator.css');
    }

    private function getProductsAdminSearchTerm(): string { return isset($_GET['s']) ? \sanitize_text_field(\wp_unslash((string) $_GET['s'])) : ''; }
    private function getProductsAdminStatusFilter(): string { if (!isset($_GET['product_status'])) { return ''; } $status = \sanitize_key(\wp_unslash((string) $_GET['product_status'])); return \in_array($status, ['publish', 'draft', 'pending', 'private'], true) ? $status : ''; }
    private function countEnabledCalculatorProducts(): int { return \array_sum($this->countEnabledCalculatorProductsByStatus()); }
    private function countEnabledCalculatorProductsByStatus(): array
    {
        $counts = [];
        foreach (['publish', 'draft', 'pending', 'private'] as $status) {
            $query = new \WP_Query(['post_type' => 'product', 'post_status' => $status, 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => false, 'ignore_sticky_posts' => true, 'update_post_meta_cache' => false, 'update_post_term_cache' => false, 'meta_query' => [[ 'key' => '_enable_calculator', 'value' => 'yes', 'compare' => '=' ]]]);
            $counts[$status] = (int) $query->found_posts;
            \wp_reset_postdata();
        }
        return $counts;
    }

    private function enqueueScript(string $relativePath, array $dependencies = [], bool $inFooter = false): string
    {
        $filePath = $this->pluginPath . $relativePath;
        if (!\file_exists($filePath)) { $this->logError("JS-bestand ontbreekt: {$filePath}"); return ''; }
        $handle = $this->generateAssetHandle($relativePath);
        \wp_enqueue_script($handle, $this->pluginUrl . $relativePath, $dependencies, (string) \filemtime($filePath), $inFooter);
        return $handle;
    }

    private function enqueueStyle(string $relativePath): void
    {
        $filePath = $this->pluginPath . $relativePath;
        if (!\file_exists($filePath)) { $this->logError("CSS-bestand ontbreekt: {$filePath}"); return; }
        \wp_enqueue_style($this->generateAssetHandle($relativePath), $this->pluginUrl . $relativePath, [], (string) \filemtime($filePath));
    }

    private function generateAssetHandle(string $relativePath): string { return 'egaline-calculator-' . \sanitize_key(\str_replace(['/', '.', '_'], '-', $relativePath)); }
    private function logError(string $message): void { if (\defined('WP_DEBUG') && WP_DEBUG) { \error_log('[WooCommerce Egaline Calculator] ' . $message); } }
}

new EgalineCalculatorInit();
