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

use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\Services\CreditService;
use GlobalPayments\Api\ServicesConfig;
use GlobalPayments\Api\ServicesContainer;
use GlobalPayments\Api\Entities\Exceptions\GatewayException;
use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\Api\Entities\Enums\PaymentMethodType;
use GlobalPayments\Api\Services\ReportingService;

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
 *     "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard", "visa",
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
  
        // Heartland SDK - Authentication
        // https://developer.heartlandpaymentsystems.com/Documentation/authentication/#authentication
        $config = new ServicesConfig();
        $config->secretApiKey = $this->configuration['secret_key'];
        $config->serviceUrl = "https://cert.api2.heartlandportico.com";
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
            // 'store_card_data' => 'FALSE',
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
            '#description' => $this->t('Get your keys at <a href="https://developer.heartlandpaymentsystems.com/Account/KeysandCredentials" target="_blank">https://developer.heartlandpaymentsystems.com/Account/KeysandCredentials</a>.'),
            '#default_value' => $this->configuration['secret_key'],
            '#required' => true,
        ];

        // $form['store_card_data'] = [
        //     '#type' => 'checkbox',
        //     '#title' => $this->t('Store Card Data'),
        //     '#description' => $this->t('Selet this option to store card data.  You must have multi-use token enabled on your Heartland Payment Systems account.'),
        //     '#default_value' => FALSE,
        // ];
  
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
                $form_state->setError($form['public_key'], $this->t('Your public key does not match the mode (@mode).', ['@mode' => $values['mode']]));
            }

            if (($mode == 'test' && $secret_env == 'prod') || ($mode == 'live' && $secret_env == 'cert')) {
                $form_state->setError($form['secret_key'], $this->t('Your secret key does not match the mode (@mode).', ['@mode' => $values['mode']]));
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
            // $this->configuration['store_card_data'] = $values['store_card_data'];
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
        $remote_id = $payment_method_token = $payment_method->getRemoteId();

        // Heartland SDK - Prepare / Charge Credit Card
        // https://developer.heartlandpaymentsystems.com/Documentation/credit-card-payments/#prepare-to-charge-a-credit-card
        $card = new CreditCardData();
        $card->token = $payment_method_token;

        // Charge or Authorize depending on your Transaction Mode
        // Commerce -> Configuration -> Orders -> Checkout Flows -> Edit -> Payment -> Transaction mode
        try {
            if ($capture) {
                $response = $card->charge($number)
                    ->withCurrency($currency)
                    ->execute();
            } else {
                $response = $card->authorize($number)
                    ->withCurrency($currency)
                    ->execute();
            }

            $remote_id = $response->transactionId;           
            $next_state = $capture ? 'completed' : 'authorization';    
            
            // Commerce plugin sets state and remote Id for next step
            $payment->setState($next_state);
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

        // Set some variables to use for our transaction
        $remote_id = $payment->getRemoteId();
        $amount = $payment->getAmount();
        $number = $amount->getNumber();
        $currency = $amount->getCurrencyCode();

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
            print_r('Void Failed, Try to Refund<br/>');
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
        $required_keys = [
            // The expected keys are payment gateway specific and usually match
            // the PaymentMethodAddForm form elements. They are expected to be valid.
            'token_value',
        ];
        foreach ($required_keys as $required_key) {
            if (empty($payment_details[$required_key])) {
                throw new \InvalidArgumentException(sprintf('$payment_details must contain the %s key.', $required_key));
            }
        }      

        // Keeps card data from being stored during processing.
        // Will need to update this for iteration 2, multi-use tokenization.
        $payment_method->setReusable(false);

        // Set values from form
        $payment_method->card_type = $payment_details['card_type'];
        $payment_method->card_number = $payment_details['last_four'];
        $payment_method->card_exp_month = trim($payment_details['exp_month']);
        $payment_method->card_exp_year = $payment_details['exp_year'];
        $expires = CreditCard::calculateExpirationTimestamp(trim($payment_details['exp_month']), $payment_details['exp_year']);
        $payment_method->setExpiresTime($expires);

        // The remote Id returned by the request.
        $remote_id = $payment_details['token_value'];

        // Remote Id is token here, but later will be Transaction Id from the gateway response.
        $payment_method->setRemoteId($remote_id);
        $payment_method->save();
    }
  
    /**
     * {@inheritdoc}
     */
    public function deletePaymentMethod(PaymentMethodInterface $payment_method)
    {
        // Delete the remote record here, throw an exception if it fails.
        // See \Drupal\commerce_payment\Exception for the available exceptions.
        // Delete the local entity.

        // Will be implemented in iteration 2, multi-use tokenization.
        // Delete token from database.  Not sure if anything needs to happen remote.

        $payment_method->delete();
    }
  
    /**
     * {@inheritdoc}
     */
    public function updatePaymentMethod(PaymentMethodInterface $payment_method)
    {
        // The default payment method edit form only supports updating billing info.
        $billing_profile = $payment_method->getBillingProfile();
  
        // Perform the update request here, throw an exception if it fails.
        // See \Drupal\commerce_payment\Exception for the available exceptions.

        // Will revisit for iteration 2, multi-use tokenization.
    }
}
