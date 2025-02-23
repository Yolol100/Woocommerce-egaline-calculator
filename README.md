## **Egaline Calculator Plugin for WooCommerce**  

A powerful and highly customizable WooCommerce extension that adds a material calculator to product pages. The plugin allows customers to calculate the required material quantity (e.g., kg per m², kg per mm) based on their input. It seamlessly integrates with WooCommerce and ensures accurate price calculations.

## 🚀 **Features**  

### **Advanced Material Calculator**
- Dynamically calculates the required material quantity based on **thickness (mm), area (m²), and number of bags**.
- Allows **real-time adjustments**—when increasing thickness, area and bag count adjust accordingly.
- Ensures logical dependencies:  
  - **If thickness increases**, area and bag count update.  
  - **If area or bag count is modified manually**, thickness remains unchanged.

### **Smart Price Calculation**
- Dynamically updates **total price** based on selected product options.
- **Automatic discount calculation** if a threshold is reached (e.g., bulk purchase discounts).

### **Seamless WooCommerce Integration**
- Appears **before the "Add to Cart" button** on product pages.
- Works with **simple and variable products**.
- Supports **dynamic price updates** when a variation is selected.

### **Customizable Admin Panel**
- Enables product-specific settings through a **custom WooCommerce metabox**.
- Configure:
  - **Material weight per bag**
  - **Material density per mm & per m²**
  - **Calculation mode (kg per mm or alternative modes)**
  - **Discount settings (threshold & percentage)**
  - **Enable/disable calculator per product**

### **Optimized User Experience**
- **Instant feedback**—users see real-time changes in calculated values.
- **Input validation** to ensure only valid numbers are entered.
- **LocalStorage Support**—retains calculator values even after a page refresh.

### **Performance & Security**
- Fully **AJAX-based** for smooth interaction without page reloads.
- **Sanitized user input** to prevent security vulnerabilities.
- Optimized for **speed and performance** with WooCommerce.

## 🛠 **Requirements**
- **WordPress 5.0+** (recommended latest version)
- **WooCommerce 5.0+**
- **PHP 7.4+** (PHP 8+ recommended for best performance)

## 📦 **Installation**

### **1. Install via WordPress Admin**
1. Go to `Plugins > Add New`
2. Click **Upload Plugin** and select the ZIP file.
3. Click **Install Now** and then **Activate**.

### **2. Manual Installation**
1. Download the plugin ZIP file.
2. Extract and upload the folder to `/wp-content/plugins/`.
3. Go to `Plugins` in WordPress and **activate** the plugin.

### **3. Using Git (For Developers)**
```bash
git clone https://github.com/yolol100/egaline-calculator.git
