# wp-phpmail (force php mail function)

We created this quick fix plugin in order to be able to use the php [mail](https://www.php.net/manual/en/function.mail.php) function. It overrides the wp_mail function and redirects to the plain php mail function. You should only install this plugin if you only want to use php mail function and the php mail function of phpmailer is not working for you.
The [PHPMailer](https://github.com/PHPMailer/PHPMailer) is [buggy](https://github.com/PHPMailer/PHPMailer/issues/2858) in the area of getting and setting php ini values and setting them in the option params of the php mail function.

Please note that it is better to use this fix than to use smtp accounts
- do not use smtp accounts on the same network as your business mail.
If wordpress gets hacked and sends out spam, you risk that your day to day business email infrastructure gets blocked on dns blacklists.
- do not use smtp accounts that already exist! If your website gets hacked, they will have access to your mailbox (mostly the same account!)
- if you decide to get a seperate smtp account on a different network, don't get an account on these networks, these are mostly on dns blacklists and often 'forget' to return a dsn/ndr to hide their high failed to deliver rate.
    * sendgrid
    * mailgun
    * sendinblue
    * sparkpost
    * mailchimp
    * everything in the amazon cloud that is not official smtp (smtp-out.amazonses.com etc)
    * it is best to avoid everything that is cheap and used by everyone
    (These are more likely to abuse and therefore be listed on dns blacklists)

- realize that wordpress is the most hacked cms there is.
- wordpress developers are not security experts nor system administration experts. Let them just do your website, nothing else. (Dentists also do not do open-heart surgery)




Our core business is not creating wordpress plugins so please forgive us for inadequacies.



## Getting Started

### Prerequisites

### Installation

## Reporting Issues

## Support

## Contributing

This is a temporary plugin, until [PHPMailer](https://github.com/PHPMailer/PHPMailer) applies a fix for the buggy [params](https://github.com/PHPMailer/PHPMailer/issues/2858) parsing. So it really does not make sense contributing here. 


## Backround issue

We were being harassed by some wordpress ‘developers’ that wanted to use a direct smtp from the web server because they could not get the php mail function to work. For obvious reasons you should not allow this. Since these wordpress ‘developers’ were not able to track down the cause of the issue, we had a look.

It seems that at least since wordpress is using phpmailer 6.5.3, there is an issue with using the mail function. This issue actually only arises when your hosting provider has properly secured the environment. Because it is not to smart to allow users to set any outgoing email address, were it is impossible to track what website is sending the email.
Wordpress is maybe the most hacked environment, so in your hosting environment you should force a configuration that can identify a hacked and spamming website. 

This is a quick fix approach so we are not investigating this in depth, we use of the top of our head knowledge and look only at code directly involved. 

@SyncrhoM Marcus Bointon has added some weird code to [PHPMailer](https://github.com/PHPMailer/PHPMailer). First of all he does not get that the params argument from the php [mail](https://www.php.net/manual/en/function.mail.php) function is optional.

```php
    865         }
    866         //Calling mail() with null params breaks
    867         $this->edebug('Sending with mail()');
```

This looks also crappy, using another ini_get within just 4 lines, while the data is already available in a variable. And if you write code for specific versions, I am used to see something like a variable having the php version that is being tested against. Makes you wonder how the rest is written.

```php
   1708         //PHP 5.6 workaround
   1709         $sendmail_from_value = ini_get('sendmail_from');
   1710         if (empty($this->Sender) && !empty($sendmail_from_value)) {
   1711             //PHP config has a sender address we can use
   1712             $this->Sender = ini_get('sendmail_from');
   1713         }
```


Anyway if we replace his code:

```php
    876             $this->edebug("Additional params: {$params}");
    877             $result = @mail($to, $subject, $body, $header, $params);
    878         }
    879         $this->edebug('Result: ' . ($result ? 'true' : 'false'));
```

With this

```php
    876             $this->edebug("Additional params: {$params}");
    877             $result = @mail($to, $subject, $body, $header);
    878         }
    879         $this->edebug('Result: ' . ($result ? 'true' : 'false'));
```

Mails go out. So he is fucking up somewhere with the params, we are not going to investigate were and what that is. He basically parses incorrect data in parms, which makes the php [mail](https://www.php.net/manual/en/function.mail.php) function fail. In our environment and probably a lot of other, the params is not necessary.
So after ~15 years of hosting [Wordpress](https://github.com/WordPress/WordPress) websites, this guy fucks it up for us, well done @SyncrhoM


### Wordpress plugin development 


#### github guide markdown
https://docs.github.com/en/get-started/writing-on-github/getting-started-with-writing-and-formatting-on-github/basic-writing-and-formatting-syntax

#### wordpress guides plugin development
https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/
