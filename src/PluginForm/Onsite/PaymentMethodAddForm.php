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

        $element['card_number'] = [
            '#type' => 'text',
            '#required' => true,
            '#validated' => true,
            '#markup' => '<div>
                <label for="heartlandCardNumber">Card Number:</label>
                <div id="heartlandCardNumber"></div>
                </div>',
        ];

        $element['expiration'] = [
            '#type' => 'text',
            '#markup' => '<div>
                <label for="heartlandCardExpiration">Card Expiration</label>
                <div id="heartlandCardExpiration"></div>
                </div>',
        ];

        $element['security_code'] = [
            '#type' => 'text',
            '#markup' => '<div>
                <label for="heartlandCardCvv">Card CVV</label>
                <div id="heartlandCardCvv"></div>
                </div>',
        ];

        $element['token_value'] = [
            '#type' => 'hidden',
            '#attributes' => ['id' => 'heartland_token_value'],
        ];

        $element['card_type'] = [
            '#type' => 'hidden',
            '#attributes' => ['id' => 'heartland_card_type'],
        ];

        $element['last_four'] = [
            '#type' => 'hidden',
            '#attributes' => ['id' => 'heartland_last_four'],
        ];

        $element['exp_month'] = [
            '#type' => 'hidden',
            '#attributes' => ['id' => 'heartland_exp_month'],
        ];

        $element['exp_year'] = [
            '#type' => 'hidden',
            '#attributes' => ['id' => 'heartland_exp_year'],
        ];

        $element['token_expire'] = [
            '#type' => 'hidden',
            '#attributes' => ['id' => 'heartland_token_expire'],
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
    }
}
