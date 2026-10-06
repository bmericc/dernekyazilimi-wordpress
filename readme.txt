=== Dernek Yazılımı ===
Contributors: lkdtr
Tags: donation, membership, volunteer, association, nonprofit
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Donation, volunteer and membership forms for associations that run the open source Dernek Yazılımı portal.

== Description ==

[Dernek Yazılımı](https://github.com/lkdtr/dernekyazilimi) is an open source portal for associations (members, dues, donations, volunteers). This plugin puts the portal's public forms on your WordPress site:

* **Donation** – the donor fills in the form on your site; the card payment page of your payment provider, or the bank accounts for a transfer, follow in a frame.
* **Volunteer registration** – name, email and a mobile phone verified by SMS. The person sets a password from the email the portal sends; no password is typed on your site.
* **Membership application** – the first step opens the portal account on your site; the application itself continues in a frame of the portal.

The forms are plain HTML styled by your theme (fonts, colours, buttons), so they look like the rest of your site. Framed portal pages have no header or menu, a transparent background and resize themselves to their content.

Add a form with its block (search for "Dernek Yazılımı") or its shortcode:

* `[dernekyazilimi_donate]` – optional attributes: `cause="3"` (preselected donation cause), `amount="250"`
* `[dernekyazilimi_volunteer]`
* `[dernekyazilimi_membership]`

= Requirements =

You need your own Dernek Yazılımı portal, served over HTTPS. In the portal's admin panel, under Settings → Organization settings:

1. add the address of your WordPress site to the sites allowed to embed pages,
2. make an API key under "Web site connection".

Then enter the portal address and the key under Settings → Dernek Yazılımı in WordPress.

== External services ==

This plugin connects to the Dernek Yazılımı portal of your own association, at the address you enter in the plugin's settings. It is not a third-party service run by the plugin's authors: the portal is software your association hosts, and your association's own terms and privacy policy apply (the forms link to the privacy policy published in the portal).

What is sent, from your site's server to the portal, and when:

* When a page with a form is shown (cached for five minutes): a request for the forms' configuration. It carries the API key and your site's address.
* When a visitor asks for a verification code or enters it: the phone number and the code.
* When a visitor submits the volunteer or membership form: first name, last name, email, phone number, communication permissions and the acceptance of the privacy policy.
* When a visitor submits the donation form: amount, donation cause, name, email, phone, message, the choice to hide the name, payment method and the acceptance of the privacy policy.

With each of these the visitor's IP address and browser name are passed on, so that the portal can limit abuse and record who accepted the privacy policy.

After a form is submitted, the visitor's browser loads a page of the portal in a frame. For a card donation that frame shows the payment page of the payment provider configured in your portal (for example iyzico – [terms](https://www.iyzico.com/en/legal/terms-of-use), [privacy policy](https://www.iyzico.com/en/privacy-policy)); card details are entered there and never reach your WordPress site.

Source of the plugin: https://github.com/lkdtr/dernekyazilimi-wordpress – source of the portal: https://github.com/lkdtr/dernekyazilimi

== Installation ==

1. Install and activate the plugin.
2. In your portal, allow your site and make an API key (see Requirements).
3. Go to Settings → Dernek Yazılımı, enter the portal address and the API key and save. "Connection status" shows which forms are open.
4. Add a form block or shortcode to a page.

The address and the key can also be set in `wp-config.php` with the constants `DERNEKYAZILIMI_PORTAL_URL` and `DERNEKYAZILIMI_API_KEY`.

== Frequently Asked Questions ==

= A form does not appear on the page =

Visitors see nothing when the portal cannot be reached or the module is off; administrators see the reason in place of the form. Check Settings → Dernek Yazılımı → Connection status.

= The portal answers that the site is not allowed =

The address of your WordPress site (with https) must be listed in the portal's organization settings under the sites allowed to embed pages.

= Does the frame work when third-party cookies are blocked? =

Yes. The portal keeps the frame's session in a partitioned cookie, and the donation pages need no session at all. Under every frame there is also a link to open the page in a new tab.

= Are card details processed by my site? =

No. They are entered on the payment provider's page shown in the frame.

== Changelog ==

= 1.0.0 =
* First release: donation, volunteer registration and membership application forms as blocks and shortcodes.
