<?php
namespace Abheranj\Iyzipay;

use Abheranj\Iyzipay\Exceptions\Card\CardMustHaveException;
use Abheranj\Iyzipay\Exceptions\Card\CardRemoveException;
use Abheranj\Iyzipay\Exceptions\Transaction\TransactionSaveException;
use Abheranj\Iyzipay\Exceptions\Transaction\TransactionVoidException;
use Abheranj\Iyzipay\Exceptions\Iyzipay\IyzipayAuthenticationException;
use Abheranj\Iyzipay\Exceptions\Iyzipay\IyzipayConnectionException;
use Abheranj\Iyzipay\Traits\PreparesCreditCardRequest;
use Abheranj\Iyzipay\Traits\PreparesTransactionRequest;
use Exception;
use Illuminate\Support\Collection;
use Iyzipay\Model\ApiTest;
use Iyzipay\Model\Payment;
use Iyzipay\Options;
use Iyzipay\Model\Locale;

class IyzipayService 
{
    use PreparesCreditCardRequest, PreparesTransactionRequest;

    protected $apiOptions;

    public function __construct()
    {
        $this->initializeApiOptions();
        $this->checkApiOptions();
    }

    public function addCreditCard($email, $iyzipay_key, array $attributes = [])
    {   
        $this->validateCreditCardAttributes($attributes);
        $card = $this->createCardOnIyzipay($email, $iyzipay_key, $attributes);
        return $card;
    }

    public function removeCreditCard($iyzipay_key, $token)    
    {
        return $this->removeCardOnIyzipay($iyzipay_key, $token);
    }

    public function singlePayment($creditCard, $buyer_arr, $billaddress, $shipaddress, $products, $currency, $installment, $subscription = false)
    {
        $this->validateHasCreditCard($creditCard);

        try {
            $transaction = $this->createTransactionOnIyzipay(
                $creditCard,
                $buyer_arr, 
                $billaddress,
                $shipaddress,
                compact('products', 'currency', 'installment'),
                $subscription
            );
            return $transaction;
        
        } catch (TransactionSaveException $e) {
            throw new TransactionSaveException($e->getMessage());
        }
    }

    public function cancelPayment($iyzipay_key)
    {
        return $this->createCancelOnIyzipay($iyzipay_key);
    }

    public function refundPayment($iyzipay_key, $price, $currency)
    {
        return $this->createRefundOnIyzipay($iyzipay_key, $price, $currency);
    }

    private function initializeApiOptions()
    {
        $this->apiOptions = new Options();
        $this->apiOptions->setBaseUrl(config('iyzipay.baseUrl'));
        $this->apiOptions->setApiKey(config('iyzipay.apiKey'));
        $this->apiOptions->setSecretKey(config('iyzipay.secretKey'));
    }

    private function checkApiOptions()
    {
        try {
            $check = ApiTest::retrieve($this->apiOptions);
        } catch (Exception $e) {
            throw new IyzipayConnectionException($e->getMessage());
        }

        if ($check->getStatus() != 'success') {
            throw new IyzipayAuthenticationException();
        }
    }

    private function validateHasCreditCard($creditCards): void
    {
        if (empty($creditCards)) {
            throw new CardMustHaveException();
        }
    }

    protected function getLocale(): string
    {
        return config('app.locale') === 'tr' ? Locale::TR : Locale::EN;
    }

    protected function getOptions(): Options
    {
        return $this->apiOptions;
    }
}