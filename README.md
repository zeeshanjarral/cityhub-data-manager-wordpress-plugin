# cityhub-data-manager-wordpress-plugin

=== CityHub Data Manager ===
Contributors: cityhub
Tags: events, community, services, listings, rest-api
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.1.1
License: GPLv2 or later
Developer: Zeeshan Ahmed Jarral

Manage CityHub content in WordPress and expose public read-only JSON for custom frontends.

== Description ==

CityHub Data Manager is a powerful, lightweight WordPress plugin designed to simplify the management of city-based content, community listings, events, local services, real estate, marketplaces, promotions, and sponsors—all from one centralized dashboard.

Built for modern city portals and custom frontend websites, CityHub Data Manager uses native WordPress functionality to provide a familiar, reliable content management experience without restricting your website's design.

Key Features:

🏙️ 9 Content Modules: Manage Events, Community, Services, Real Estate, Market, City Hall, Marketplace, Promotions, and Sponsors.

📅 Event Management: Add event dates, start and end times, locations, descriptions, and images.

🖼️ Media Management: Upload featured images and multiple gallery photos using the WordPress Media Library.

🔗 Custom CTA Buttons: Add personalized call-to-action labels and links to listings.

⚙️ Easy Content Management: Create, edit, publish, trash, and bulk-delete listings through the WordPress admin dashboard.

🌐 REST API Integration: Access published content through public, read-only JSON endpoints for custom websites and applications.

🔍 Advanced Filtering: Retrieve listings using search, categories, pagination, and sorting.

📱 Responsive Listing Pages: Display complete listing information while preserving your active WordPress theme's header and footer.

🔄 Backward Compatibility: Reuses existing CityHub post types and API paths to preserve previously stored content.

🔒 Secure Content Access: Public APIs expose only published listings, while content management remains restricted to authenticated WordPress users.

Perfect For: Smart city portals, community directories, local business platforms, municipal websites, event directories, and multi-city content management systems.

CityHub Data Manager — One Dashboard. Nine Content Modules. Unlimited Possibilities.

Each listing has standard WordPress add, edit, trash/delete, bulk select, and bulk
delete actions. Forms include title, description, thumbnail, gallery, and a custom
CTA label/link. Events additionally include date, start time, end time, and location.

== Installation ==

1. Back up your WordPress database.
2. Deactivate the previous CityHub Manager plugin. Do not delete its content.
3. Upload cityhub-data-manager.zip from Plugins > Add New > Upload Plugin.
4. Activate CityHub Data Manager.
5. Open CityHub in the WordPress admin sidebar.

The plugin deliberately reuses the existing chm_* post type identifiers and
cityhub/v1 API paths so existing database records remain available.

== API ==

Collection endpoints:

* /wp-json/cityhub/v1/events
* /wp-json/cityhub/v1/community
* /wp-json/cityhub/v1/services
* /wp-json/cityhub/v1/real-estate
* /wp-json/cityhub/v1/market
* /wp-json/cityhub/v1/city-hall
* /wp-json/cityhub/v1/marketplace
* /wp-json/cityhub/v1/promotions
* /wp-json/cityhub/v1/sponsors

Optional collection parameters: page, per_page, search, category, order, orderby.

Single listing endpoints:

* /wp-json/cityhub/v1/events/123
* /wp-json/cityhub/v1/events/slug/example-event

Only published listings are exposed publicly. Admin creation, editing, and deletion
continue to use authenticated WordPress screens.

Single View Post pages automatically display the thumbnail, structured event details,
categories, description, additional gallery images, and CTA while retaining the active
theme header and footer.

== Changelog ==

= 2.1.1 =
* Fixes collection endpoint HTTP 500 errors when category is omitted.
* Makes category sanitization explicit and safe on current PHP/WordPress versions.

= 2.1.0 =
* Shows complete structured listing data on every single View Post page.
* Adds responsive detail-page styling compatible with the active theme.

= 2.0.0 =
* Adds complete classic admin forms and media gallery controls.
* Preserves CityHub post type and API identifiers.
* Adds public frontend-friendly JSON collection and detail endpoints.

Developed by: Zeeshan Ahmed Jarral
License: GPLv2 or later
