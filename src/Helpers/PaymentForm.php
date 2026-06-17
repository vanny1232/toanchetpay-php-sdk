<?php

declare(strict_types=1);

namespace ToanchetPay\Helpers;

use ToanchetPay\Config\ToanchetPayConfig;
use ToanchetPay\DTO\OpenSessionResponse;
use ToanchetPay\Exceptions\ValidationException;

/**
 * Generates the HTML POST form used to redirect users to the ACLEDA payment page.
 */
class PaymentForm
{
    private ToanchetPayConfig $config;

    public function __construct(ToanchetPayConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Generate the auto-submit HTML form.
     *
     * @param  OpenSessionResponse $session     Result of openSessionV2.
     * @param  array<string,mixed> $txData      Original transaction data.
     * @param  string              $successUrl  Where to redirect on success.
     * @param  string              $errorUrl    Where to redirect on failure.
     * @param  string              $formId      HTML form id attribute.
     * @return string
     */
    public function generate(
        OpenSessionResponse $session,
        array $txData,
        string $successUrl,
        string $errorUrl,
        string $formId = '_toanchetpayForm'
    ): string {
        if (empty($session->sessionId) || empty($session->paymentTokenId)) {
            throw new ValidationException(
                'PaymentForm: sessionId and paymentTokenId are required. Call openSession() first.'
            );
        }

        $fields = [
            'merchantID'      => $this->config->getMerchantId(),
            'sessionid'       => $session->sessionId,
            'paymenttokenid'  => $session->paymentTokenId,
            'description'     => $txData['purchaseDesc']     ?? $txData['description'] ?? '',
            'expirytime'      => $txData['expiryTime']       ?? '5',
            'amount'          => $txData['purchaseAmount']   ?? $txData['amount'] ?? '',
            'quantity'        => $txData['quantity']         ?? '1',
            'item'            => $txData['item']             ?? '1',
            'invoiceid'       => $txData['invoiceid']        ?? $txData['invoiceId'] ?? '',
            'currencytype'    => $txData['purchaseCurrency'] ?? $txData['currency'] ?? 'USD',
            'transactionID'   => $txData['txid']             ?? $txData['transactionId'] ?? '',
            'paymentCard'     => $txData['paymentCard']      ?? '0',
            'successUrlToReturn' => $successUrl,
            'errorUrl'        => $errorUrl,
        ];

        $action  = htmlspecialchars($this->config->getPaymentPageUrl(), ENT_QUOTES, 'UTF-8');
        $formId  = htmlspecialchars($formId, ENT_QUOTES, 'UTF-8');

        $html  = "<!DOCTYPE html>\n";
        $html .= "<html>\n<head><meta charset=\"UTF-8\"><title>Redirecting to payment...</title></head>\n";
        $html .= "<body>\n";
        $html .= "<form id=\"{$formId}\" name=\"{$formId}\" action=\"{$action}\" method=\"post\">\n";

        foreach ($fields as $name => $value) {
            $safeName  = htmlspecialchars((string) $name,  ENT_QUOTES, 'UTF-8');
            $safeValue = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            $html .= "    <input type=\"hidden\" id=\"{$safeName}\" name=\"{$safeName}\" value=\"{$safeValue}\">\n";
        }

        $html .= "    <noscript><button type=\"submit\">Click here to pay</button></noscript>\n";
        $html .= "</form>\n";
        $html .= "<script>\n";
        $html .= "    (function(){\n";
        $html .= "        function preventBack(){ window.history.forward(); }\n";
        $html .= "        window.onload = preventBack;\n";
        $html .= "        window.onpageshow = function(e){ if(e.persisted){ preventBack(); } };\n";
        $html .= "        document.getElementById('{$formId}').submit();\n";
        $html .= "    })();\n";
        $html .= "</script>\n";
        $html .= "</body>\n</html>\n";

        return $html;
    }

    /**
     * Output the form directly and exit — convenience wrapper.
     *
     * @param  OpenSessionResponse $session
     * @param  array<string,mixed> $txData
     * @param  string              $successUrl
     * @param  string              $errorUrl
     */
    public function render(
        OpenSessionResponse $session,
        array $txData,
        string $successUrl,
        string $errorUrl
    ): void {
        echo $this->generate($session, $txData, $successUrl, $errorUrl);
    }
}
