=== Plugin Name ===
Contributors: f1outsourcing
Tags: fix
Requires at least: 4.7
Tested up to: 6.2
Stable tag: 6.2
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

use server defaults with php mail function

== Description ==

We created this quick fix plugin in order to be able to use the php [mail](https://www.php.net/manual/en/function.mail.php) function. It overrides the wp_mail function and redirects to the plain php mail function. You should only install this plugin if you only want to use php mail function and the php mail function of phpmailer is not working for you.
The [PHPMailer](https://github.com/PHPMailer/PHPMailer) is [buggy](https://github.com/PHPMailer/PHPMailer/issues/2858) in the area of getting and setting php ini values and setting them in the option params of the php mail function.

== Screenshots ==


== Changelog ==