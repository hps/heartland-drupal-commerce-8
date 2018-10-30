<?php

/**
* @file
* Contains \Drupal\commerce_heartland\Controller\Heartland.php
*/

namespace Drupal\commerce_heartland\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayBase;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_price\Price;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use GlobalPayments\Api\ServicesConfig;
use GlobalPayments\Api\ServicesContainer;
use GlobalPayments\Api\Entities\Address;
use GlobalPayments\Api\Entities\Customer;
use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\Api\Entities\Enums\PaymentMethodType;
use GlobalPayments\Api\Entities\Exceptions\GatewayException;
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\Services\CreditService;

/**
 * Provides the On-site payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "commerce_heartland",
 *   label = "Heartland",
 *   display_label = "Token-based payment gateway from Heartland",
 *   forms = {
 *     "add-payment-method" = "Drupal\commerce_heartland\PluginForm\Onsite\PaymentMethodAddForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "mastercard", "visa",
 *   },
 *   js_library = "commerce_heartland/form"
 * )
 */


class Heartland extends OnsitePaymentGatewayBase implements OnsiteInterface
{

    /**
     * {@inheritdoc}
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, TimeInterface $time)
    {
        parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time);
    }

    protected function authenticate()
    {
        $secretKey = $this->configuration['secret_key'];
        $env = explode('_', $secretKey)[1];

        if ($env == "prod") {
            $serviceUrl = 'https://api2-c.heartlandportico.com';
        } else {
            $serviceUrl = 'https://cert.api2-c.heartlandportico.com';
        }

        // Heartland SDK - Authentication
        // https://developer.heartlandpaymentsystems.com/Documentation/authentication/#authentication
        $config = new ServicesConfig();
        $config->secretApiKey = $secretKey;
        $config->serviceUrl = $serviceUrl;
        $config->developerId = '002914';
        $config->versionNumber = '3114';
        ServicesContainer::configure($config);
    }

    /**
     * {@inheritdoc}
     */
    public function getPublicKey()
    {
        // Used by PaymentMethodAddForm to send key to javascript
        return $this->configuration['public_key'];
    }
  
    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration()
    {
        // Store Card Data will be implemented in iteration 2 multi-use tokenization.
        return [
            'public_key' => '',
            'secret_key' => '',
            'subscriptions' => 'FALSE'
        ] + parent::defaultConfiguration();
    }
  
    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildConfigurationForm($form, $form_state);
  
        // Matching schema in config/schema/commerce_heartland.schema.yml.
        $form['public_key'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Public Key'),
            '#default_value' => $this->configuration['public_key'],
            '#required' => true,
        ];

        $form['secret_key'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Secret Key'),
            '#description' => $this->t('Get your keys at the <a href="https://developer.heartlandpaymentsystems.com/Account/KeysandCredentials" target="_blank">Heartland Developer Portal</a>.'),
            '#default_value' => $this->configuration['secret_key'],
            '#required' => true,
        ];

        $form['subscriptions'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Enable Card Storage / Subscriptions'),
            '#description' => $this->t('This feature requires Multi-Use Tokenization to be enabled on your Heartland Merchant Account.  Contact <a href="https://developer.heartlandpaymentsystems.com/Support" target="_blank">Support</a> for more details.'),
            '#default_value' => $this->configuration['subscriptions'],
        ];

        return $form;
    }
  
    /**
     * {@inheritdoc}
     */
    public function validateConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        parent::validateConfigurationForm($form, $form_state);

        // Check if keys match the environment test/live
        if (!$form_state->getErrors()) {
            $values = $form_state->getValues($form['#parents'])['configuration']['commerce_heartland'];
            $mode = $values['mode'];
            $public_env = explode('_', $values['public_key'])[1];
            $secret_env = explode('_', $values['secret_key'])[1];

            if (($mode == 'test' && $public_env == 'prod') || ($mode == 'live' && $public_env == 'cert')) {
                $form_state->setError($form['public_key'], $this->t('Your public key does not match the mode (@mode).', ['@mode' => $mode]));
            }

            if (($mode == 'test' && $secret_env == 'prod') || ($mode == 'live' && $secret_env == 'cert')) {
                $form_state->setError($form['secret_key'], $this->t('Your secret key does not match the mode (@mode).', ['@mode' => $mode]));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        parent::submitConfigurationForm($form, $form_state);
  
        if (!$form_state->getErrors()) {
            $values = $form_state->getValue($form['#parents']);
            $this->configuration['public_key'] = $values['public_key'];
            $this->configuration['secret_key'] = $values['secret_key'];
            $this->configuration['subscriptions'] = $values['subscriptions'];
        }
    }
  
    /**
     * {@inheritdoc}
     */
    public function createPayment(PaymentInterface $payment, $capture = true)
    {
        $this->assertPaymentState($payment, ['new']);
        $payment_method = $payment->getPaymentMethod();
        $this->assertPaymentMethod($payment_method);

        // Perform the create payment request here, throw an exception if it fails.
        // See \Drupal\commerce_payment\Exception for the available exceptions.
        // Remember to take into account $capture when performing the request.

        // Set some variables to use for our transaction
        $amount = $payment->getAmount();
        $number = $amount->getNumber();
        $currency = $amount->getCurrencyCode();
        $remote_id = mb_substr($payment_method->getRemoteId(), 3);
        $token_type = mb_substr($payment_method->getRemoteId(), 0, 3);
        // Setting this so we can get the expiration month and year
        $values = $payment_method->toArray();

        $this->authenticate();

        // Heartland SDK - Prepare / Charge Credit Card
        // https://developer.heartlandpaymentsystems.com/Documentation/credit-card-payments/#prepare-to-charge-a-credit-card
        $card = new CreditCardData();
        $card->token = $remote_id;
        // User can update expiration date so we need to pass it in each time to make sure it's up-to-date
        $card->expMonth = $values['card_exp_month'][0]['value'];
        $card->expYear = $values['card_exp_year'][0]['value'];

        $address = new Address();
        $address->postalCode = $payment_method->getBillingProfile()->address->first()->getPostalCode();

        // Charge or Authorize depending on your Transaction Mode
        // Commerce -> Configuration -> Orders -> Checkout Flows -> Edit -> Payment -> Transaction mode
        try {
            if ($capture) {
                $response = $card->charge($number);
            } else {
                $response = $card->authorize($number);
            }

            $response = $response->withCurrency($currency)
                ->withAddress($address);

            if ($token_type == 'sut' && $this->configuration['subscriptions']) {
                $response = $response->withRequestMultiUseToken(true);

                $response = $response->execute();

                // Updating the payment method to contain the multi-use token for next time.
                if (isset($response->token) && !empty($response->token)) {
                    $payment_method->setRemoteId('mut'.$response->token);
                    $payment_method->setReusable(true);
                } else {
                    $payment_method->setReusable(false);
                }
                $payment_method->save();
            } else {
                $response = $response->execute();
            }


            $remote_id = $response->transactionId;
            $next_state = $capture ? 'completed' : 'authorization';
            
            // Commerce plugin sets state and remote Id for next step
            $payment->setState($next_state);
            // The remote Id is the transaction Id from the gateway response stored inside the payment, not the payment method.
            $payment->setRemoteId($remote_id);
            $payment->save();
        } catch (GatewayException $e) {
            throw new PaymentGatewayException($e->getMessage());
        }
    }
  
    /**
     * {@inheritdoc}
     */
    public function capturePayment(PaymentInterface $payment, Price $amount = null)
    {
        $this->assertPaymentState($payment, ['authorization']);
        // If not specified, capture the entire amount.
        $amount = $amount ?: $payment->getAmount();
  
        // Perform the capture request here, throw an exception if it fails.
        // See \Drupal\commerce_payment\Exception for the available exceptions.

        // Set some variables to use for our transaction
        $remote_id = $payment->getRemoteId();
        $number = $amount->getNumber();
        $currency = $amount->getCurrencyCode();

        $this->authenticate();

        // Heartland SDK - Capture Authorization
        // https://developer.heartlandpaymentsystems.com/Documentation/credit-card-payments/#capture-an-authorization
        try {
            $response = Transaction::fromId($remote_id)
                ->capture($number)
                ->withCurrency($currency)
                ->execute();

            // Commerce plugin sets state and amount for next step
            $payment->setState('completed');
            $payment->setAmount($amount);
            $payment->save();
        } catch (GatewayException $e) {
            throw new PaymentGatewayException($e->getMessage());
        }
    }
  
    /**
     * {@inheritdoc}
     */
    public function voidPayment(PaymentInterface $payment)
    {
        // The voidPayment function is called when 'Void' is clicked on the orders payment screen,
        // not 'Delete' like the Drupal Commerce docs state
        $this->assertPaymentState($payment, ['authorization']);
      
        // Perform the void request here, throw an exception if it fails.
        // See \Drupal\commerce_payment\Exception for the available exceptions.

        // Set remote Id to use for our transaction
        $remote_id = $payment->getRemoteId();

        $this->authenticate();

        // Heartland SDK - Void Transaction
        // https://developer.heartlandpaymentsystems.com/Documentation/credit-card-payments/#void-a-transaction
        try {
            $response = Transaction::fromId($remote_id, PaymentMethodType::CREDIT)
                ->void()
                ->execute();
  
            // Commerce plugin sets state for next step
            $payment->setState('authorization_voided');
            $payment->save();
        } catch (GatewayException $e) {
            // If void fails because the auth/capture has completed and the batch is closed/settled
            // Do a refund instead
            $this->refundPayment($payment, $amount);
        }
    }
  
    /**
     * {@inheritdoc}
     */
    public function refundPayment(PaymentInterface $payment, Price $amount = null)
    {
        $this->assertPaymentState($payment, ['completed', 'partially_refunded']);
        // If not specified, refund the entire amount.
        $amount = $amount ?: $payment->getAmount();
        $this->assertRefundAmount($payment, $amount);

        // Perform the refund request here, throw an exception if it fails.
        // See \Drupal\commerce_payment\Exception for the available exceptions.

        // Set some variables to use for our transaction
        $remote_id = $payment->getRemoteId();
        $number = $amount->getNumber();
        $currency = $amount->getCurrencyCode();

        $this->authenticate();

        // Heartland SDK - Refund Transaction
        // https://developer.heartlandpaymentsystems.com/Documentation/credit-card-payments/#refund-a-transaction
        try {
            $response = Transaction::fromId($remote_id, PaymentMethodType::CREDIT)
                ->refund($number)
                ->withCurrency($currency)
                ->execute();
  
            // In case more than one refund has occurred, Commerce plugin sets amounts and states
            $old_refunded_amount = $payment->getRefundedAmount();
            $new_refunded_amount = $old_refunded_amount->add($amount);
            if ($new_refunded_amount->lessThan($payment->getAmount())) {
                $payment->setState('partially_refunded');
            } else {
                $payment->setState('refunded');
            }
    
            $payment->setRefundedAmount($new_refunded_amount);
            $payment->save();
        } catch (GatewayException $e) {
            throw new PaymentGatewayException($e->getMessage());
        }
    }
  
    /**
     * {@inheritdoc}
     */
    public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details)
    {
        $route_name = \Drupal::routeMatch()->getRouteName();

        if (empty($payment_details['token_value'])) {
            throw new \InvalidArgumentException(t('token_value is not set in $payment_details.'));
        }

        // Retrieve a multi-use token and store non-sensitive card info if subscriptions option is enabled
        // *** Multi-use tokenization must be enabled on your Heartland merchant account for this to work ***
        if ($this->configuration['subscriptions']) {
            if ($route_name != 'commerce_checkout.form') { // The user is on the Add Payment Method screen, not checking out
                $this->authenticate();

                // Heartland SDK - Prepare / Charge Credit Card
                // https://developer.heartlandpaymentsystems.com/Documentation/credit-card-payments/#prepare-to-charge-a-credit-card
                $card = new CreditCardData();
                $card->token = $payment_details['token_value'];

                $address = new Address();
                $address->postalCode = $payment_method->getBillingProfile()->address->first()->getPostalCode();

                try {
                    // Heartland SDK - Requesting a multi-use token
                    // https://developer.heartlandpaymentsystems.com/Documentation/credit-card-payments/#using-multi-use-tokens
                    $response = $card->tokenize()
                        ->withAddress($address)
                        ->execute();

                    // Multi-use Token
                    if (isset($response->token) && !empty($response->token)) {
                        $remote_id = 'mut'.$response->token;

                        // Enable Drupal to store non-sensitive card data
                        $payment_method->setReusable(true);
                    } else {
                        throw new PaymentGatewayException('There was an issue retrieving multi-use token. Response Code: '.$response->responseCode.' Response Message: '.$response->responseMessage);
                    }
                } catch (GatewayException $e) {
                    throw new PaymentGatewayException($e->getMessage());
                }
            } else {
                // Single-use Token, we will get multi-use token when we charge in createPayment()
                $remote_id = 'sut'.$payment_details['token_value'];
            }
        } else {
            // Don't store payment information
            $payment_method->setReusable(false);

            // Single-use Token
            $remote_id = 'sut'.$payment_details['token_value'];
        }

        // Set values from form
        $payment_method->card_type = $payment_details['card_type'];
        $payment_method->card_number = $payment_details['last_four'];
        $payment_method->card_exp_month = trim($payment_details['exp_month']);
        $payment_method->card_exp_year = $payment_details['exp_year'];
        $expires = CreditCard::calculateExpirationTimestamp(trim($payment_details['exp_month']), $payment_details['exp_year']);
        $payment_method->setExpiresTime($expires);

        // The remote Id is a single or multi-use token stored inside the payment method
        $payment_method->setRemoteId($remote_id);
        $payment_method->save();
    }
  
    /**
     * {@inheritdoc}
     */
    public function deletePaymentMethod(PaymentMethodInterface $payment_method)
    {
        // Delete the local entity.
        $payment_method->delete();
    }
  
    /**
     * {@inheritdoc}
     */
    public function updatePaymentMethod(PaymentMethodInterface $payment_method)
    {
        // The default payment method edit form only supports updating billing info.
        $billing_profile = $payment_method->getBillingProfile();
    }
}
