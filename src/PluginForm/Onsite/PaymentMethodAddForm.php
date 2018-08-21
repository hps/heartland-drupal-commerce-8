<?php

namespace Drupal\commerce_heartland\PluginForm\Onsite;

use Drupal\commerce_payment\PluginForm\PaymentMethodAddForm as BasePaymentMethodAddForm;
use Drupal\Core\Form\FormStateInterface;

class PaymentMethodAddForm extends BasePaymentMethodAddForm
{

    /**
     * {@inheritdoc}
     */
    protected function buildCreditCardForm(array $element, FormStateInterface $form_state)
    {
        $plugin = $this->plugin;

        $element['#attributes']['class'][] = 'heartland-form';

        $element['#attached']['drupalSettings']['heartland'] = [
            'publicKey' => $plugin->getPublicKey(),
        ];
        $element['#attached']['library'][] = 'commerce_heartland/secure-submit';
        $element['#attached']['library'][] = 'commerce_heartland/form';

        $element['number'] = [
            '#type' => 'text',
            '#required' => true,
            '#validated' => true,
            '#markup' => '<div class="form-group">
                <label for="iframesCardNumber">Card Number:</label>
                <div class="iframeholder" id="iframesCardNumber"></div>
                </div>',
        ];

        $element['expiration'] = [
            '#type' => 'text',
            '#markup' => '<div class="form-group">
                <label for="iframesCardExpiration">Card Expiration</label>
                <div class="iframeholder" id="iframesCardExpiration"></div>
                </div>',
        ];

        $element['security_code'] = [
            '#type' => 'text',
            '#markup' => '<div class="form-group">
                <label for="iframesCardCvv">Card CVV</label>
                <div class="iframeholder" id="iframesCardCvv"></div>
                </div>',
        ];

        $element['token_value'] = [
            '#type' => 'hidden',
            '#attributes' => ['id' => 'token_value'],
        ];

        $element['card_type'] = [
            '#type' => 'hidden',
            '#attributes' => ['id' => 'card_type'],
        ];

        $element['last_four'] = [
            '#type' => 'hidden',
            '#attributes' => ['id' => 'last_four'],
        ];

        $element['exp_month'] = [
            '#type' => 'hidden',
            '#attributes' => ['id' => 'exp_month'],
        ];

        $element['exp_year'] = [
            '#type' => 'hidden',
            '#attributes' => ['id' => 'exp_year'],
        ];

        $element['token_expire'] = [
            '#type' => 'hidden',
            '#attributes' => ['id' => 'token_expire'],
        ];

        return $element;
    }

    /**
     * {@inheritdoc}
     */
    protected function validateCreditCardForm(array &$element, FormStateInterface $form_state)
    {
        // The JS library performs its own validation.
    }

    /**
     * {@inheritdoc}
     */
    public function submitCreditCardForm(array $element, FormStateInterface $form_state)
    {
        // The payment gateway plugin will process the submitted payment details.
        $values = $form_state->getValues();

        if (!empty($values['contact_information']['email'])) {
            // then we are dealing with anonymous user. Adding a customer email.
            $payment_details = $values['payment_information']['add_payment_method']['payment_details'];
            $payment_details['customer_email'] = $values['contact_information']['email'];
            $form_state->setValue(['payment_information', 'add_payment_method', 'payment_details'], $payment_details);
        }
    }
}
