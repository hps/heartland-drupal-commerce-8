## **This repository is no longer maintained. For a list of supported plugins, visit us [here](https://github.com/globalpayments).**

<a href="http://developer.heartlandpaymentsystems.com" target="_blank">
	<img src="http://developer.heartlandpaymentsystems.com/Resource/Download/sdk-readme-heartland-logo" alt="Heartland logo" title="Heartland" align="right" />
</a>

# Heartland Payment Gateway Module for Drupal 8 Commerce
This module makes it easy to process payments through your Drupal 8 store.

Supported features include:

* Process payments using charge or delayed capture
* Full and partial refunds
* Void or capture authorizations
* Card Storage
* Recurring Payments


## Developer Support

You are not alone! If you have any questions while you are working through your development process, please feel free to <a href="https://developer.heartlandpaymentsystems.com/Support" target="_blank">reach out to our team for assistance</a>!


## Installation

Using [Composer](https://getcomposer.org/)? Require this library in your `composer.json`:

```json
{
    "require": {
        "hps/commerce_heartland": "*"
    }
}
```

and run `composer update` to pull down the dependency and update your autoloader.

### Recurring Payments

Additional setup is required to accomplish recurring payments.  Please install and configure [Commerce Recurring](https://www.drupal.org/project/commerce_recurring) and [Advanced Queue](https://www.drupal.org/project/advancedqueue).

Both plugins can be installed manually or using composer.


## API Keys

To begin creating test transactions you will need to obtain a set of public and private keys. These are easily obtained by creating an account on our [developer portal](http://developer.heartlandpaymentsystems.com/).
Your keys are located under your profile information. 

[![Developer Keys](http://developer.heartlandpaymentsystems.com/Resource/Download/sdk-readme-devportal-keys)](http://developer.heartlandpaymentsystems.com/Account/KeysAndCredentials)


## Contributing

All our code is open sourced and we encourage fellow developers to contribute and help improve it!

1. Fork it
2. Create your feature branch (`git checkout -b my-new-feature`)
3. Ensure SDK tests are passing
4. Commit your changes (`git commit -am 'Add some feature'`)
5. Push to the branch (`git push origin my-new-feature`)
6. Create new Pull Request
