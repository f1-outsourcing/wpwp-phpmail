<?php
/**
 * @package wpwp_phpmail
 * @version 0.1.0
 */
/*
Plugin Name: wpwp-phpmail
Plugin URI: http://wordpress.org/plugins/wp-phpmail/
Description: quick fix plugin in order to be able to use the php mail function. It overrides the wp_mail function and redirects to the plain php mail function. 
Author: None None
Version: 0.1.0
*/

if (!defined('ABSPATH')){
    exit;
}

class PHPMAIL_MAILER {
    
    var $plugin_version = '0.1.0';
    var $plugin_url;
    var $plugin_path;
    
    function __construct() {
        define('PHPMAIL_MAILER_VERSION', $this->plugin_version);
        define('PHPMAIL_MAILER_SITE_URL', site_url());
        define('PHPMAIL_MAILER_HOME_URL', home_url());
        define('PHPMAIL_MAILER_URL', $this->plugin_url());
        define('PHPMAIL_MAILER_PATH', $this->plugin_path());
        $this->loader_operations();
    }

    function loader_operations() {
        add_action('plugins_loaded', array($this, 'plugins_loaded_handler'));
        add_filter('pre_wp_mail', 'smtp_mailer_pre_wp_mail', 10, 2);
    }
    
    function plugin_url() {
        if ($this->plugin_url)
            return $this->plugin_url;
        return $this->plugin_url = plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__));
    }

    function plugin_path() {
        if ($this->plugin_path)
            return $this->plugin_path;
        return $this->plugin_path = untrailingslashit(plugin_dir_path(__FILE__));
    }
}

$GLOBALS['smtp_mailer'] = new PHPMAIL_MAILER();

function smtp_mailer_pre_wp_mail($null, $atts)
{
    if ( isset( $atts['to'] ) ) {
            $to = $atts['to'];
    }

    if ( ! is_array( $to ) ) {
            $to = explode( ',', $to );
    }

    if ( isset( $atts['subject'] ) ) {
            $subject = $atts['subject'];
    }

    if ( isset( $atts['message'] ) ) {
            $message = $atts['message'];
    }

    if ( isset( $atts['headers'] ) ) {
            $headers = $atts['headers'];
    }

    if ( isset( $atts['attachments'] ) ) {
            $attachments = $atts['attachments'];
            if ( ! is_array( $attachments ) ) {
                    $attachments = explode( "\n", str_replace( "\r\n", "\n", $attachments ) );
            }
    }
    
    global $phpmailer;

    // (Re)create it, if it's gone missing.
    if ( ! ( $phpmailer instanceof PHPMailer\PHPMailer\PHPMailer ) ) {
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
            $phpmailer = new PHPMailer\PHPMailer\PHPMailer( true );

            $phpmailer::$validator = static function ( $email ) {
                    return (bool) is_email( $email );
            };
    }

    // Headers.
    $cc       = array();
    $bcc      = array();
    $reply_to = array();

    if ( empty( $headers ) ) {
            $headers = array();
    } else {
            if ( ! is_array( $headers ) ) {
                    // Explode the headers out, so this function can take
                    // both string headers and an array of headers.
                    $tempheaders = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
            } else {
                    $tempheaders = $headers;
            }
            $headers = array();

            // If it's actually got contents.
            if ( ! empty( $tempheaders ) ) {
                    // Iterate through the raw headers.
                    foreach ( (array) $tempheaders as $header ) {
                            if ( strpos( $header, ':' ) === false ) {
                                    if ( false !== stripos( $header, 'boundary=' ) ) {
                                            $parts    = preg_split( '/boundary=/i', trim( $header ) );
                                            $boundary = trim( str_replace( array( "'", '"' ), '', $parts[1] ) );
                                    }
                                    continue;
                            }
                            // Explode them out.
                            list( $name, $content ) = explode( ':', trim( $header ), 2 );

                            // Cleanup crew.
                            $name    = trim( $name );
                            $content = trim( $content );

                            switch ( strtolower( $name ) ) {
                                    // Mainly for legacy -- process a "From:" header if it's there.
                                    case 'from':
                                            $bracket_pos = strpos( $content, '<' );
                                            if ( false !== $bracket_pos ) {
                                                    // Text before the bracketed email is the "From" name.
                                                    if ( $bracket_pos > 0 ) {
                                                            $from_name = substr( $content, 0, $bracket_pos );
                                                            $from_name = str_replace( '"', '', $from_name );
                                                            $from_name = trim( $from_name );
                                                    }

                                                    $from_email = substr( $content, $bracket_pos + 1 );
                                                    $from_email = str_replace( '>', '', $from_email );
                                                    $from_email = trim( $from_email );

                                                    // Avoid setting an empty $from_email.
                                            } elseif ( '' !== trim( $content ) ) {
                                                    $from_email = trim( $content );
                                            }
                                            break;
                                    case 'content-type':
                                            if ( strpos( $content, ';' ) !== false ) {
                                                    list( $type, $charset_content ) = explode( ';', $content );
                                                    $content_type                   = trim( $type );
                                                    if ( false !== stripos( $charset_content, 'charset=' ) ) {
                                                            $charset = trim( str_replace( array( 'charset=', '"' ), '', $charset_content ) );
                                                    } elseif ( false !== stripos( $charset_content, 'boundary=' ) ) {
                                                            $boundary = trim( str_replace( array( 'BOUNDARY=', 'boundary=', '"' ), '', $charset_content ) );
                                                            $charset  = '';
                                                    }

                                                    // Avoid setting an empty $content_type.
                                            } elseif ( '' !== trim( $content ) ) {
                                                    $content_type = trim( $content );
                                            }
                                            break;
                                    case 'cc':
                                            $cc = array_merge( (array) $cc, explode( ',', $content ) );
                                            break;
                                    case 'bcc':
                                            $bcc = array_merge( (array) $bcc, explode( ',', $content ) );
                                            break;
                                    case 'reply-to':
                                            $reply_to = array_merge( (array) $reply_to, explode( ',', $content ) );
                                            break;
                                    default:
                                            // Add it to our grand headers array.
                                            $headers[ trim( $name ) ] = trim( $content );
                                            break;
                            }
                    }
            }
    }

    // Empty out the values that may be set.
    $phpmailer->clearAllRecipients();
    $phpmailer->clearAttachments();
    $phpmailer->clearCustomHeaders();
    $phpmailer->clearReplyTos();
    $phpmailer->Body    = '';
    $phpmailer->AltBody = '';

    /**
     * Filters the email address to send from.
     *
     * @since 2.2.0
     *
     * @param string $from_email Email address to send from.
     */
    $from_email = apply_filters( 'wp_mail_from', $from_email );

    /**
     * Filters the name to associate with the "from" email address.
     *
     * @since 2.3.0
     *
     * @param string $from_name Name associated with the "from" email address.
     */
    $from_name = apply_filters( 'wp_mail_from_name', $from_name );

    // Set mail's subject and body.
    $phpmailer->Subject = $subject;
    $phpmailer->Body    = $message;

    // Set destination addresses, using appropriate methods for handling addresses.
    $address_headers = compact( 'to', 'cc', 'bcc', 'reply_to' );

    foreach ( $address_headers as $address_header => $addresses ) {
            if ( empty( $addresses ) ) {
                    continue;
            }

            foreach ( (array) $addresses as $address ) {
                    try {
                            // Break $recipient into name and address parts if in the format "Foo <bar@baz.com>".
                            $recipient_name = '';

                            if ( preg_match( '/(.*)<(.+)>/', $address, $matches ) ) {
                                    if ( count( $matches ) == 3 ) {
                                            $recipient_name = $matches[1];
                                            $address        = $matches[2];
                                    }
                            }

                            switch ( $address_header ) {
                                    case 'to':
                                            $phpmailer->addAddress( $address, $recipient_name );
                                            break;
                                    case 'cc':
                                            $phpmailer->addCc( $address, $recipient_name );
                                            break;
                                    case 'bcc':
                                            $phpmailer->addBcc( $address, $recipient_name );
                                            break;
                                    case 'reply_to':
                                            $phpmailer->addReplyTo( $address, $recipient_name );
                                            break;
                            }
                    } catch ( PHPMailer\PHPMailer\Exception $e ) {
                            continue;
                    }
            }
    }

	// edit force php mail function to be used
    $phpmailer->isMail();

    // Set Content-Type and charset.
    // If we don't have a Content-Type from the input headers.
    if ( ! isset( $content_type ) ) {
            $content_type = 'text/plain';
    }

    /**
     * Filters the wp_mail() content type.
     *
     * @since 2.3.0
     *
     * @param string $content_type Default wp_mail() content type.
     */
    $content_type = apply_filters( 'wp_mail_content_type', $content_type );

    $phpmailer->ContentType = $content_type;

    // Set whether it's plaintext, depending on $content_type.
    if ( 'text/html' === $content_type ) {
            $phpmailer->isHTML( true );
    }

    // If we don't have a charset from the input headers.
    if ( ! isset( $charset ) ) {
            $charset = get_bloginfo( 'charset' );
    }

    /**
     * Filters the default wp_mail() charset.
     *
     * @since 2.3.0
     *
     * @param string $charset Default email charset.
     */
    $phpmailer->CharSet = apply_filters( 'wp_mail_charset', $charset );

    // Set custom headers.
    if ( ! empty( $headers ) ) {
            foreach ( (array) $headers as $name => $content ) {
                    // Only add custom headers not added automatically by PHPMailer.
                    if ( ! in_array( $name, array( 'MIME-Version', 'X-Mailer' ), true ) ) {
                            try {
                                    $phpmailer->addCustomHeader( sprintf( '%1$s: %2$s', $name, $content ) );
                            } catch ( PHPMailer\PHPMailer\Exception $e ) {
                                    continue;
                            }
                    }
            }

            if ( false !== stripos( $content_type, 'multipart' ) && ! empty( $boundary ) ) {
                    $phpmailer->addCustomHeader( sprintf( 'Content-Type: %s; boundary="%s"', $content_type, $boundary ) );
            }
    }

    if ( isset( $attachments ) && ! empty( $attachments ) ) {
            foreach ( $attachments as $filename => $attachment ) {
                    $filename = is_string( $filename ) ? $filename : '';

                    try {
                            $phpmailer->addAttachment( $attachment, $filename );
                    } catch ( PHPMailer\PHPMailer\Exception $e ) {
                            continue;
                    }
            }
    }

    /**
     * Fires after PHPMailer is initialized.
     *
     * @since 2.2.0
     *
     * @param PHPMailer $phpmailer The PHPMailer instance (passed by reference).
     */
    do_action_ref_array( 'phpmailer_init', array( &$phpmailer ) );

    $mail_data = compact( 'to', 'subject', 'message', 'headers', 'attachments' );

    // edit disable setting sendmail options, use server default
    //var_dump($phpmailer);
    $phpmailer->UseSendmailOptions=false; 


    // Send!
    try {
            $send = $phpmailer->send();

            /**
             * Fires after PHPMailer has successfully sent an email.
             *
             * The firing of this action does not necessarily mean that the recipient(s) received the
             * email successfully. It only means that the `send` method above was able to
             * process the request without any errors.
             *
             * @since 5.9.0
             *
             * @param array $mail_data {
             *     An array containing the email recipient(s), subject, message, headers, and attachments.
             *
             *     @type string[] $to          Email addresses to send message.
             *     @type string   $subject     Email subject.
             *     @type string   $message     Message contents.
             *     @type string[] $headers     Additional headers.
             *     @type string[] $attachments Paths to files to attach.
             * }
             */
            do_action( 'wp_mail_succeeded', $mail_data );

            return $send;
    } catch ( PHPMailer\PHPMailer\Exception $e ) {
            $mail_data['phpmailer_exception_code'] = $e->getCode();

            /**
             * Fires after a PHPMailer\PHPMailer\Exception is caught.
             *
             * @since 4.4.0
             *
             * @param WP_Error $error A WP_Error object with the PHPMailer\PHPMailer\Exception message, and an array
             *                        containing the mail recipient, subject, message, headers, and attachments.
             */
            do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', $e->getMessage(), $mail_data ) );

            return false;
    }
}

