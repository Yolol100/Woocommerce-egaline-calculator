# 🧮 Egaline Calculator Plugin for WooCommerce

[![WordPress Plugin Version](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-6.0%2B-purple.svg)](https://woocommerce.com/)
[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777BB4.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v3-green.svg)](https://www.gnu.org/licenses/gpl-3.0)

A powerful and highly customizable WooCommerce extension that adds an intelligent material calculator to product pages. Enable customers to calculate precise material quantities (kg per m², kg per mm) with real-time price updates and seamless cart integration.

## ✨ Features

### 🧮 **Advanced Material Calculator**
- **Dynamic Quantity Calculation**: Automatically calculates required material based on thickness (mm), area (m²), and bag count
- **Smart Dependencies**: Intelligent adjustment system where thickness changes update area and bag count accordingly
- **Real-time Updates**: Instant feedback with live calculation updates as users adjust parameters
- **Multiple Calculation Modes**: Support for various material calculation methods (kg per mm, alternative modes)

### 💰 **Smart Price Calculation**
- **Dynamic Pricing**: Real-time total price updates based on calculated quantities
- **Bulk Discounts**: Automatic discount application when threshold quantities are reached
- **Variable Product Support**: Seamless integration with WooCommerce variable products
- **Tax Integration**: Proper tax calculation integration with WooCommerce settings

### 🔧 **Seamless WooCommerce Integration**
- **Strategic Placement**: Calculator appears before "Add to Cart" button for optimal user flow
- **Product Type Support**: Compatible with simple and variable WooCommerce products
- **Cart Integration**: Calculated quantities automatically added to cart with proper metadata
- **Order Integration**: Calculator data preserved through checkout and order management

### ⚙️ **Customizable Admin Panel**
- **Product-Specific Settings**: Individual calculator configuration per product via custom metabox
- **Flexible Configuration**:
 - Material weight per bag settings
 - Material density configuration (per mm & per m²)
 - Calculation mode selection
 - Discount threshold and percentage settings
 - Per-product calculator enable/disable toggle

### 🎯 **Optimized User Experience**
- **Input Validation**: Robust validation ensuring only valid numerical inputs
- **LocalStorage Persistence**: Calculator values retained across page refreshes
- **Dark Mode & Reset**: Toggleable dark theme and one-click reset button
- **Responsive Design**: Mobile-friendly interface that works on all devices
- **Loading States**: Clear loading indicators during AJAX operations

### 🛡️ **Performance & Security**
- **AJAX-Powered**: Smooth interactions without page reloads
- **Input Sanitization**: Comprehensive security measures against malicious input
- **Optimized Performance**: Minimal impact on page load times
- **Error Handling**: Graceful error handling with user-friendly messages

## 🔧 Requirements

| Component | Minimum Version | Recommended |
|-----------|----------------|-------------|
| **WordPress** | 6.0+ | Latest Stable |
| **WooCommerce** | 6.0+ | Latest Stable |
| **PHP** | 8.0+ | 8.1+ |
| **MySQL** | 5.6+ | 8.0+ |

### **Server Requirements**
- Memory Limit: 128MB minimum (256MB recommended)
- Max Execution Time: 30 seconds minimum
- cURL support enabled
- JSON support enabled

## 📦 Installation

### Method 1: WordPress Admin Dashboard
1. Navigate to `Plugins > Add New`
2. Click **Upload Plugin**
3. Select the downloaded ZIP file
4. Click **Install Now**
5. Click **Activate Plugin**

### Method 2: Manual Installation
1. Download the plugin ZIP file
2. Extract the ZIP file
3. Upload the `egaline-calculator` folder to `/wp-content/plugins/`
4. Navigate to `Plugins` in WordPress admin
5. Find "Egaline Calculator" and click **Activate**

### Method 3: Git Installation (Developers)
```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/yolol100/egaline-calculator.git
cd egaline-calculator
```
Activate the plugin from the WordPress admin.
