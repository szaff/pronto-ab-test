# WordPress Generic Plugin Template

A clean, well-structured WordPress plugin template designed as a starting point for custom plugin development. This template follows WordPress coding standards and best practices while remaining simple and extensible.

## 📁 Project Structure

```
generic-plugin/
├── admin/                          # Admin-only functionality
│   ├── class-generic-plugin-admin.php       # Main admin class
│   └── class-generic-plugin-settings.php    # Settings management
├── assets/                         # Frontend assets
│   ├── css/
│   │   ├── admin.css              # Admin styles (empty)
│   │   └── public.css             # Public styles (empty)
│   └── js/
│       ├── admin.js               # Admin scripts (empty)
│       └── public.js              # Public scripts (empty)
├── docs/                          # Documentation
│   ├── CHANGELOG.md               # Version history (empty)
│   └── README.md                  # Documentation (empty)
├── includes/                      # Core functionality
│   ├── class-generic-plugin-core.php        # Core class (empty)
│   └── class-generic-plugin-helpers.php     # Helper functions (empty)
├── languages/                     # Internationalization
│   └── generic-plugin.pot         # Translation template (empty)
├── public/                        # Frontend functionality
│   └── class-generic-plugin-public.php      # Public-facing features
├── templates/                     # Template files (empty directory)
└── generic-plugin.php             # Main plugin file
```

## 🚀 Quick Start

### 1. Download and Customize

1. **Clone or download** this template
2. **Rename the folder** from `generic-plugin` to your plugin name
3. **Find and replace** the following throughout all files:
   - `Generic_Plugin` → `Your_Plugin_Name`
   - `generic-plugin` → `your-plugin-name`
   - `GP_` → `YPN_` (or your preferred prefix)
   - `gp_` → `ypn_` (or your preferred prefix)

### 2. Update Plugin Headers

Edit `generic-plugin.php` (your main file) and update:

```php
/**
 * Plugin Name: Your Awesome Plugin
 * Plugin URI: https://yourwebsite.com/plugins/your-plugin
 * Description: Description of what your plugin does.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Text Domain: your-plugin-name
 */
```

### 3. Configure Constants

Update the plugin constants in the main file:

```php
define('YPN_VERSION', '1.0.0');
define('YPN_PLUGIN_FILE', __FILE__);
// ... etc
```

## 🔧 Core Features

### What's Included

- **Singleton Pattern**: Main plugin class uses singleton pattern
- **Autoloader**: Automatic class loading from `includes/`, `admin/`, and `public/` directories
- **Hook Management**: Organized WordPress hooks and actions
- **Admin Interface**: Basic settings page in WordPress admin
- **Frontend Integration**: Shortcode support and asset management
- **Internationalization**: Ready for translations
- **Activation/Deactivation**: Proper plugin lifecycle handling
- **Requirements Check**: WordPress and PHP version validation

### What's Ready to Use

1. **Basic Admin Settings Page** (`admin/class-generic-plugin-admin.php`)

   - Located under Settings → Generic Plugin
   - Two sample fields: checkbox and text input
   - Proper sanitization and validation

2. **Public Shortcode** (`public/class-generic-plugin-public.php`)

   - `[generic_plugin]` shortcode available
   - Conditional asset loading (only when shortcode is used)

3. **Settings Management** (`admin/class-generic-plugin-settings.php`)
   - Helper methods for getting/setting options
   - Centralized settings management

## 📝 Development Guide

### Adding New Features

#### 1. Create New Classes

Classes are automatically loaded if they follow the naming convention:

```php
// File: includes/class-your-plugin-name-feature.php
class Your_Plugin_Name_Feature {
    // Your code here
}
```

#### 2. Add Database Tables

Implement in the main class `create_tables()` method:

```php
private function create_tables() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'ypn_custom_table';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name tinytext NOT NULL,
        data text NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
```

#### 3. Register Custom Post Types

Add to the main class `register_post_types()` method:

```php
private function register_post_types() {
    register_post_type('ypn_custom_type', array(
        'labels' => array(
            'name' => __('Custom Items', 'your-plugin-name'),
        ),
        'public' => true,
        'supports' => array('title', 'editor'),
    ));
}
```

#### 4. Add AJAX Handlers

```php
// In your class constructor or init_hooks method
add_action('wp_ajax_ypn_action', array($this, 'handle_ajax'));
add_action('wp_ajax_nopriv_ypn_action', array($this, 'handle_ajax'));

public function handle_ajax() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'ypn_ajax_nonce')) {
        wp_die('Security check failed');
    }

    // Your AJAX logic here

    wp_send_json_success($response_data);
}
```

### File Organization Tips

- **`includes/`** - Core functionality, utilities, shared classes
- **`admin/`** - Admin-only features, settings, meta boxes
- **`public/`** - Frontend features, shortcodes, widgets
- **`assets/`** - CSS, JavaScript, images
- **`templates/`** - Template files for frontend display

## ⚡ Simplification Opportunities

### Current Complexity Issues

1. **Empty Files**: Several files are empty and could be removed until needed:

   - All CSS/JS files in `assets/`
   - `includes/class-generic-plugin-core.php`
   - `includes/class-generic-plugin-helpers.php`
   - Documentation files

2. **Over-Engineering for Simple Plugins**:

   - The autoloader might be overkill for simple plugins
   - Separate admin/public initialization could be simplified
   - Multiple hook methods could be consolidated

3. **Unused Functionality**:
   - Cron job scheduling setup (but not used)
   - Database table creation (but not implemented)
   - Translation setup (but no strings to translate)

### Simplified Starter Version

For simpler plugins, consider this minimal approach:

```php
// Minimal plugin structure - single file
class Simple_Plugin {
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_shortcode('simple_plugin', array($this, 'shortcode'));
    }

    public function init() {
        // Basic initialization
    }

    public function shortcode($atts) {
        return '<p>Hello World!</p>';
    }
}

new Simple_Plugin();
```

## 🔧 Fixing Asset Enqueuing Redundancy

### Current Problem

The template has asset enqueuing in multiple places:

- Main class: `enqueue_public_assets()` and `enqueue_admin_assets()`
- Public class: `enqueue_assets()`
- Admin class: No asset enqueuing (good!)

### Recommended Solution

**Remove asset enqueuing from the main class** and let each component handle its own assets:

#### 1. Update Main Class (`generic-plugin.php`)

```php
// REMOVE these methods from the main class:
// - enqueue_public_assets()
// - enqueue_admin_assets()

// REMOVE these hooks from define_hooks():
// - add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
// - add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
```

#### 2. Keep Public Assets in Public Class

The public class already handles this correctly with conditional loading:

```php
// public/class-generic-plugin-public.php
public function enqueue_assets() {
    // Only load if shortcode is present - GOOD!
    if (! $this->should_load_assets()) {
        return;
    }

    wp_enqueue_style('generic-plugin', GP_ASSETS_URL . 'css/public.css', array(), GP_VERSION);
    wp_enqueue_script('generic-plugin', GP_ASSETS_URL . 'js/public.js', array('jquery'), GP_VERSION, true);
}
```

#### 3. Add Admin Assets to Admin Class

```php
// admin/class-generic-plugin-admin.php
private function init_hooks() {
    add_action('admin_menu', array($this, 'add_admin_menu'));
    add_action('admin_init', array($this, 'init_settings'));
    add_action('admin_enqueue_scripts', array($this, 'enqueue_assets')); // ADD THIS
    add_filter('plugin_action_links_' . GP_PLUGIN_BASENAME, array($this, 'add_action_links'));
}

// ADD THIS METHOD
public function enqueue_assets($hook) {
    // Only load on plugin admin pages
    if (strpos($hook, 'generic-plugin') === false) {
        return;
    }

    wp_enqueue_style(
        'generic-plugin-admin',
        GP_ASSETS_URL . 'css/admin.css',
        array(),
        GP_VERSION
    );

    wp_enqueue_script(
        'generic-plugin-admin',
        GP_ASSETS_URL . 'js/admin.js',
        array('jquery'),
        GP_VERSION,
        true
    );
}
```

### Why This Approach is Better

1. **Single Responsibility**: Each class manages its own assets
2. **Conditional Loading**: Assets only load when needed
3. **No Redundancy**: No duplicate enqueuing logic
4. **Better Performance**: Smarter loading conditions
5. **Easier Maintenance**: Changes to admin assets don't affect public assets

### Alternative: Asset Manager Class

For complex plugins with many assets, consider a dedicated asset manager:

```php
// includes/class-generic-plugin-assets.php
class Generic_Plugin_Assets {

    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin'));
    }

    public function enqueue_public() {
        // Centralized public asset logic
    }

    public function enqueue_admin($hook) {
        // Centralized admin asset logic
    }
}
```

## 🛠️ Customization Examples

### Example 1: Contact Form Plugin

```php
// 1. Rename to: Awesome_Contact_Form
// 2. Add form handling in public class
// 3. Add email settings in admin
// 4. Create form template
```

### Example 2: Custom Widget Plugin

```php
// 1. Add widget class in includes/
// 2. Register widget in main class
// 3. Add widget settings in admin
```

## 📋 Checklist for New Plugin

- [ ] Rename all files and classes
- [ ] Update plugin headers
- [ ] Replace all prefixes (GP* → YOUR*)
- [ ] Update text domain
- [ ] Remove unused empty files
- [ ] Test activation/deactivation
- [ ] Verify admin settings page works
- [ ] Test shortcode functionality
- [ ] Add your custom features
- [ ] Update this README for your plugin

## 🤝 Best Practices

1. **Security**: Always sanitize input and escape output
2. **Performance**: Only load what you need, when you need it
3. **Compatibility**: Test with latest WordPress version
4. **Documentation**: Comment your code and update documentation
5. **Standards**: Follow WordPress Coding Standards

## 📄 License

GPL v2 or later - same as WordPress

---

**Happy Plugin Development!** 🎉

This template provides a solid foundation while remaining flexible enough for any type of WordPress plugin. Start simple and add complexity as needed.
