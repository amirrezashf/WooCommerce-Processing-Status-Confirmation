# WooCommerce Processing Status Confirmation

Requires an exact admin confirmation before changing a WooCommerce order status to processing.

## Description

WooCommerce Processing Status Confirmation adds a confirmation modal to the WooCommerce admin order edit screen.

When an admin or store manager tries to change an order status to `processing`, the plugin asks them to type the exact phrase:

```text
بله
```

The confirmation is also validated server-side using a nonce and submitted form fields.

## Features

- Confirmation modal before setting order status to processing
- Requires exact phrase confirmation
- Server-side validation
- Nonce verification
- Works on WooCommerce admin order edit screens
- Supports classic order storage
- Includes HPOS-compatible object save handling
- No AJAX dependency
- No custom database tables
- No permanent data storage
- Single-file plugin

## Requirements

- PHP 7.4+
- WordPress 6.0+
- WooCommerce 7.0+

## Installation

1. Create a folder named `woocommerce-processing-status-confirmation`.
2. Place `woocommerce-processing-status-confirmation.php` inside it.
3. Upload the folder to:

```text
wp-content/plugins/
```

4. Activate the plugin from WordPress admin.

## Usage / How it Works

1. Open a WooCommerce order in the admin panel.
2. Change the order status to `Processing`.
3. A confirmation modal appears.
4. Type exactly:

```text
بله
```

5. Confirm and update the order.

If the confirmation is missing or invalid, the status change is blocked and the order status is reverted.

## Data Storage

The plugin does not store order data or confirmation logs.

It only uses a short-lived transient to display an admin notice when a status change is blocked.

## Development

Built with:

- WordPress Coding Standards
- Native WordPress APIs
- WooCommerce order hooks
- Nonce verification
- Capability checks
- Escaped admin output
- Translation-ready strings
- HPOS-compatible architecture

## Hooks

- `admin_enqueue_scripts`
- `wp_insert_post_data`
- `woocommerce_before_order_object_save`
- `admin_notices`

## Filters

### `wc_psc_required_capability`

Change the required capability.

Default:

```php
edit_shop_orders
```

Example:

```php
add_filter(
	'wc_psc_required_capability',
	static function () {
		return 'manage_woocommerce';
	}
);
```

### `wc_psc_is_order_edit_screen`

Customize screen detection for loading the confirmation UI.

### `wc_psc_is_order_save_request`

Customize request detection for server-side confirmation enforcement.

## Future Improvements

- Admin settings page
- Custom confirmation phrase
- Role-based bypass option

## License

GPL-2.0-or-later

## Author

Amirreza Shayesteh Far

GitHub: https://github.com/amirrezashf

---

# تأیید تغییر وضعیت سفارش ووکامرس

افزونه‌ای سبک برای ووکامرس که قبل از تغییر وضعیت سفارش به «در حال انجام»، تأیید دقیق مدیر را الزامی می‌کند.

## توضیحات

افزونه WooCommerce Processing Status Confirmation در صفحه ویرایش سفارش ووکامرس یک modal تأیید نمایش می‌دهد.

زمانی که مدیر یا مدیر فروشگاه بخواهد وضعیت سفارش را به `processing` تغییر دهد، باید دقیقاً عبارت زیر را وارد کند:

```text
بله
```

این تأیید در سمت سرور نیز با nonce و فیلدهای فرم بررسی می‌شود.

## ویژگی‌ها

- نمایش modal تأیید قبل از تغییر وضعیت به processing
- نیاز به وارد کردن عبارت دقیق
- اعتبارسنجی سمت سرور
- بررسی nonce
- اجرا در صفحه ویرایش سفارش ووکامرس
- پشتیبانی از ساختار کلاسیک سفارش‌ها
- مدیریت سازگار با HPOS
- بدون وابستگی به AJAX
- بدون جدول اختصاصی دیتابیس
- بدون ذخیره‌سازی دائمی داده
- معماری تک‌فایلی

## نیازمندی‌ها

- PHP 7.4+
- WordPress 6.0+
- WooCommerce 7.0+

## نصب

1. یک پوشه با نام `woocommerce-processing-status-confirmation` بسازید.
2. فایل `woocommerce-processing-status-confirmation.php` را داخل آن قرار دهید.
3. پوشه را در مسیر زیر آپلود کنید:

```text
wp-content/plugins/
```

4. افزونه را از پنل مدیریت وردپرس فعال کنید.

## نحوه استفاده / عملکرد افزونه

1. وارد صفحه ویرایش سفارش ووکامرس شوید.
2. وضعیت سفارش را روی `Processing` قرار دهید.
3. پنجره تأیید نمایش داده می‌شود.
4. دقیقاً عبارت زیر را وارد کنید:

```text
بله
```

5. تأیید کنید و سفارش را به‌روزرسانی کنید.

اگر تأیید انجام نشود یا معتبر نباشد، تغییر وضعیت block می‌شود و سفارش به وضعیت قبلی برمی‌گردد.

## ذخیره‌سازی داده

افزونه هیچ داده‌ای از سفارش یا تأییدها ذخیره نمی‌کند.

فقط برای نمایش notice مدیریتی، از یک transient کوتاه‌مدت استفاده می‌شود.

## توسعه

توسعه داده‌شده بر اساس:

- WordPress Coding Standards
- Native WordPress APIs
- WooCommerce order hooks
- بررسی nonce
- بررسی capability
- خروجی‌های escape شده
- متن‌های آماده ترجمه
- معماری سازگار با HPOS

## هوک‌ها

- `admin_enqueue_scripts`
- `wp_insert_post_data`
- `woocommerce_before_order_object_save`
- `admin_notices`

## فیلترها

### `wc_psc_required_capability`

تغییر capability مورد نیاز.

مقدار پیش‌فرض:

```php
edit_shop_orders
```

نمونه استفاده:

```php
add_filter(
	'wc_psc_required_capability',
	static function () {
		return 'manage_woocommerce';
	}
);
```

### `wc_psc_is_order_edit_screen`

برای تغییر منطق تشخیص صفحه ویرایش سفارش.

### `wc_psc_is_order_save_request`

برای تغییر منطق تشخیص request ذخیره سفارش.

## بهبودهای آینده

- صفحه تنظیمات
- تغییر عبارت تأیید از پنل مدیریت
- امکان bypass بر اساس نقش کاربری

## مجوز

GPL-2.0-or-later

## نویسنده

Amirreza Shayesteh Far

GitHub: https://github.com/amirrezashf
