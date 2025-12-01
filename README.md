# Debug Toggle

A WordPress plugin to manage debug settings from your dashboard. Toggle debug modes and prevent unauthorized changes with ease.

![Version](https://img.shields.io/badge/version-1.7.8-blue.svg)
![WordPress](https://img.shields.io/badge/wordpress-5.2%2B-brightgreen.svg)
![PHP](https://img.shields.io/badge/php-5.6%2B-8892BF.svg)
![License](https://img.shields.io/badge/license-GPLv2%2B-red.svg)

## Description

Debug Toggle simplifies WordPress debug mode management by providing an intuitive dashboard interface to control debug constants without manually editing wp-config.php. Perfect for developers who frequently need to toggle debug modes during development.

## Features

- **Dashboard Control** - Manage all WordPress debug constants from one place
- **Debug Monitoring** - Automatically enforce debug settings and prevent unauthorized changes
- **Admin Bar Quick Toggle** - Enable/disable debug modes directly from the WordPress admin bar
- **Multiple Debug Constants** - Control WP_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY, SCRIPT_DEBUG, and SAVEQUERIES
- **Scheduled Monitoring** - Configure automatic checks (1-24 hour intervals) to enforce debug settings
- **Safe wp-config.php Updates** - Automatically manages debug constants in your configuration file
- **Translation Ready** - Full internationalization support
- **Security First** - Nonce verification and capability checks throughout

## Installation

### From GitHub

1. Download the latest release or clone this repository
2. Upload the `debug-toggle` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to Settings > Debug Toggle to configure

### Manual Installation

1. Download `debug_toggle.php`
2. Upload to `/wp-content/plugins/`
3. Activate through WordPress admin
4. Configure via Settings > Debug Toggle

## Usage

### Basic Configuration

1. Go to **Settings > Debug Toggle** in your WordPress admin
2. Toggle individual debug constants on/off
3. Click **Save Changes**

### Debug Monitoring

Enable Debug Monitoring to:
- Automatically disable all debug modes
- Prevent manual changes to debug settings
- Enforce debug-off state at scheduled intervals

When monitoring is enabled, all debug settings are locked and cannot be changed until monitoring is disabled.

### Admin Bar Quick Access

When enabled, the admin bar displays current debug status with quick access to:
- Enable all debug modes
- Disable all debug modes  
- Access plugin settings

### Configuration Options

- **Debug Monitoring** - Enable/disable automatic enforcement
- **Monitoring Interval** - Set check frequency (1-24 hours)
- **Admin Bar Feature** - Show/hide admin bar menu
- **Remove Data on Uninstall** - Choose whether to clean up plugin data on uninstall

## Debug Constants Managed

- **WP_DEBUG** - Enable/disable WordPress debug mode
- **WP_DEBUG_LOG** - Log errors to wp-content/debug.log
- **WP_DEBUG_DISPLAY** - Display errors on screen
- **SCRIPT_DEBUG** - Use development versions of core JS/CSS files
- **SAVEQUERIES** - Save database queries for analysis

## Requirements

- WordPress 5.2 or higher
- PHP 5.6 or higher
- Write permissions on wp-config.php

## Frequently Asked Questions

**Q: Is it safe to use on production sites?**  
A: Yes, but debug mode should typically be disabled on production. Use Debug Monitoring to enforce this.

**Q: Will this conflict with other debug plugins?**  
A: The plugin manages debug constants in wp-config.php. If another plugin or manual changes exist, this plugin will override them.

**Q: Can I use this on multisite?**  
A: Yes, but it manages constants at the WordPress installation level, not per-site.

**Q: What happens when I deactivate the plugin?**  
A: Debug constants added by the plugin are removed from wp-config.php. Your site returns to its pre-plugin state.

## Changelog

### 1.7.8
- Current stable release
- Full WordPress 5.2+ compatibility
- Enhanced security with nonce verification
- Translation support
- Scheduled monitoring with configurable intervals

## Support

For support, feature requests, or bug reports:
- Open an issue on [GitHub](https://github.com/webjive/debug-toggle/issues)
- Visit [WebJIVE](https://www.web-jive.com)

## Author

**Eric Caldwell - WebJIVE**
- Website: [https://www.web-jive.com](https://www.web-jive.com)
- 19+ years of digital marketing experience serving Arkansas businesses

## License

This plugin is licensed under the GPLv2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

---

Made with ❤️ by WebJIVE - Digital Marketing Agency in Little Rock, Arkansas
