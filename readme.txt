=== Checkout Vipps for WooCommerce ===

Contributors: siglar, wallinga
Tags: checkout, gateway, vipps, woocommerce, boxplugins, pay, mobile
Requires at least: 4.4.0
Tested up to: 5.8
Requires PHP: 5.2 or higher
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl.html
WC requires at least: 3.2
WC tested up to: 5.8

IMPORTANT NOTICE! This plugins is no longer under development, use at own risk.
We instead recommend the official plugin https://wordpress.org/plugins/woo-vipps/
If problems, contact Tommy from Bold Digital at <a href="tel: +47 975 28 712">975 28 712</a> or <a href="mailto: tommy@bolddigital.no">tommy@bolddigital.no</a>

== Description ==

Directly integration with vipps backend api. The only plugin with 100% automated payment capturing for vipps.

__Features included in free edition:__

* Simple setup guide
* Two Step payment
* Automatic capture of payments
* Seamless design
* Single Step checkout
* Shortcode checkout (Checkout from anywhere)
* Manual capture of orders if automation fails
* Manual refund of orders
* Additional themes (Currently 2 themes available)
* Transaction logs for vipps orders
* Transaction lookup in order admin editor

__Feature requests:__

* Manual payment requests with optional features
* Optionaly accept vipps invoice in addition to direct payment
* User registration & login with vipps

== FAQ ==

= How is this plugin different from the official gateway provided by WP Hosting =

We provide an alternative paid solution in addition to the free service, as we believe this will give our customers
the best user experience and better service.

= How stable is this plugin? =

This is the first ever public WooCommerce plugin directly integrated with vipps api and does support both api v1 and api v2 of vipps.
We have several happy customers and published our first public release 1st of May 2018, with many improvements and bugfixes thereafter.
Previously only available from our [website](https://boxplugins.no), before it was released on wordpress.org

= Are your company related to / or partner with vipps? =

No, this plugin is maintained by Siglar Development & Box Media as an alternative integration service,
to provide better stability, extra functionality & proffesional support for the payment service provided by vipps for webshops.

= What does it cost? =

As the plugin is currently not beeing developed, the plugin is now entirely free with all features.

== Installation ==

1. Signup for [vipps på nett](https://vippsbedrift.no/signup/vippspanett/)
2. Collect test credentials from the [test developer portal](https://apitest-portal.vipps.no/) of vipps
3. Paste test credentials into plugin settings, save & validate
4. Ask vipps for live credentials by emailing them
5. Collect credentials from the [developer portal](https://api-portal.vipps.no/) of vipps
6. Paste credentials into plugin settings, save & validate
7. Perform a test payment to ensure it works

== Changelog ==

= 2.1.5/2.1.6/2.1.7 - 14.01.2022 =

* This plugin is now deprecated. Vipps does no longer support API v1.

= 2.1.4 - 14.01.2022 =

* Made PRO features available in free version

= 2.1.3 - 16.11.2018 =

* Better handling of rejection error from Vipps

= 2.1.2 - 21.10.2018 =

* Support for GPDR as required from Vipps
* Many small bug fixes

= 2.1.1 - 03.09.2018 =

* Description update

= 2.1.0 - 27.08.2018 =

* Important security update. This upgrade is absolutely recommended!
* Prefix and suffix of orderid now work properly
* Several design, layout and style fixes
* Manual capture functionality for orders. Ex. if automation fails by network error (Pro feature)
* Manual refund functionality for orders (Pro feature)
* 1 new theme added (Pro feature)
* Transaction logs for vipps orders (Pro feature)
* Transaction lookup in order admin editor (Pro feature)

= 2.0.2 - 25.08.2018 =

* Security upgraded
* Description updated

= 2.0.1 - 24.08.2018 =

* Fixed assets issue

= 2.0.0 - 21.08.2018 =

* Initial release of version 2
* Deprecation of version 1

= 1.4.2 =

* Fixed payment_completed trigger for webhooks
* Added notice if order was already paid

= 1.4.1 =

* Fixed licensing issue

= 1.4.0 =

* Fixed security issue

...