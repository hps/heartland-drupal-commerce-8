(function ($, Drupal, Heartland, drupalSettings) {
  'use strict';

  Drupal.behaviors.heartlandForm = {
    attach: function (context) {

      if (!drupalSettings.heartland.publicKey) {
        return;
      }

      $('.heartland-form', context).once('heartland-processed').each(function () {
        var key = drupalSettings.heartland.publicKey;
        var form = $(this).closest('form');

        // Create a new `HPS` object with the necessary configuration
        var hps = new Heartland.HPS({
          publicKey: key,
          type: 'iframe',
          // Configure the iframe fields to tell the library where
          // the iframe should be inserted into the DOM and some
          // basic options
          fields: {
            cardNumber: {
              target: 'heartlandCardNumber',
              placeholder: '•••• •••• •••• ••••',
            },
            cardExpiration: {
              target: 'heartlandCardExpiration',
              placeholder: 'MM / YYYY',
            },
            cardCvv: {
              target: 'heartlandCardCvv',
              placeholder: 'CVV',
            }
          },
          // Collection of CSS to inject into the iframes.
          // These properties can match the site's styles
          // to create a seamless experience.
          style: {
            'input[type=text],input[type=tel]': {
              'box-sizing': 'border-box',
              'display': 'block',
              'width': '100%',
              'height': '34px',
              'padding': '6px 12px',
              'font-size': '14px',
              'line-height': '1.42857143',
              'color': '#555',
              'background-color': '#fff',
              'background-image': 'none',
              'border': '1px solid #ccc',
              'border-radius': '4px',
              '-webkit-box-shadow': 'inset 0 1px 1px rgba(0,0,0,.075)',
              'box-shadow': 'inset 0 1px 1px rgba(0,0,0,.075)',
              '-webkit-transition': 'border-color ease-in-out .15s,-webkit-box-shadow ease-in-out .15s',
              '-o-transition': 'border-color ease-in-out .15s,box-shadow ease-in-out .15s',
              'transition': 'border-color ease-in-out .15s,box-shadow ease-in-out .15s'
            },
            'input[type=text]:focus,input[type=tel]:focus': {
              'border-color': '#66afe9',
              'outline': '0',
              '-webkit-box-shadow': 'inset 0 1px 1px rgba(0,0,0,.075),0 0 8px rgba(102,175,233,.6)',
              'box-shadow': 'inset 0 1px 1px rgba(0,0,0,.075),0 0 8px rgba(102,175,233,.6)'
            },
            'input[type=submit]': {
              'box-sizing': 'border-box',
              'display': 'inline-block',
              'padding': '6px 12px',
              'margin-bottom': '0',
              'font-size': '14px',
              'font-weight': '400',
              'line-height': '1.42857143',
              'text-align': 'center',
              'white-space': 'nowrap',
              'vertical-align': 'middle',
              '-ms-touch-action': 'manipulation',
              'touch-action': 'manipulation',
              'cursor': 'pointer',
              '-webkit-user-select': 'none',
              '-moz-user-select': 'none',
              '-ms-user-select': 'none',
              'user-select': 'none',
              'background-image': 'none',
              'border': '1px solid transparent',
              'border-radius': '4px',
              'color': '#fff',
              'background-color': '#337ab7',
              'border-color': '#2e6da4'
            },
            'input[type=submit]:hover': {
              'color': '#fff',
              'background-color': '#286090',
              'border-color': '#204d74'
            },
            'input[type=submit]:focus, input[type=submit].focus': {
              'color': '#fff',
              'background-color': '#286090',
              'border-color': '#122b40',
              'text-decoration': 'none',
              'outline': '5px auto -webkit-focus-ring-color',
              'outline-offset': '-2px'
            }
          },
          // Callback when a token is received from the service
          onTokenSuccess: function (resp) {
            $('#heartland_token_value', form).val(resp.token_value);
            $('#heartland_card_type', form).val(resp.card_type);
            $('#heartland_last_four', form).val(resp.last_four);
            $('#heartland_exp_month', form).val(resp.exp_month);
            $('#heartland_exp_year', form).val(resp.exp_year);
            $('#heartland_token_expire', form).val(resp.token_expire);
            form.get(0).submit();
          },
          // Callback when an error is received from the service
          onTokenError: function (resp) {
            alert('There was an error: ' + resp.error.message);
          }
        });

        // Attach a handler to interrupt the form submission
        form.on('submit.heartland', function (e) {
          // Prevent the form from continuing to the `action` address
          if ($('[name="payment_information[payment_method]"]').is(':checked')) {
            e.preventDefault();
          }
          // Tell the iframes to tokenize the data
          hps.Messages.post(
            {
              accumulateData: true,
              action: 'tokenize',
              message: key
            },
            'cardNumber'
          );
        });
      });

    }
  }
})(jQuery, Drupal, Heartland, drupalSettings);