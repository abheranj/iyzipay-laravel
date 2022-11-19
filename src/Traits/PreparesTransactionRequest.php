<?php
namespace Abheranj\Iyzipay\Traits;

use Abheranj\Iyzipay\Exceptions\Fields\TransactionFieldsException;
use Abheranj\Iyzipay\Exceptions\Transaction\TransactionSaveException;
use Abheranj\Iyzipay\Exceptions\Transaction\TransactionVoidException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Iyzipay\Model\Address;
use Iyzipay\Model\BasketItem;
use Iyzipay\Model\Buyer;
use Iyzipay\Model\Cancel;
use Iyzipay\Model\Currency;
use Iyzipay\Model\Payment;
use Iyzipay\Model\PaymentCard;
use Iyzipay\Model\PaymentChannel;
use Iyzipay\Model\PaymentGroup;
use Iyzipay\Model\Refund;
use Iyzipay\Options;
use Iyzipay\Request\CreateCancelRequest;
use Iyzipay\Request\CreateRefundRequest;
use Iyzipay\Request\CreatePaymentRequest;
use Carbon\Carbon;
use Iyzipay\Model\ThreedsInitialize;
use Iyzipay\Model\ThreedsPayment;
use Iyzipay\Request\CreateThreedsPaymentRequest;

trait PreparesTransactionRequest
{

    /**
     * Validation for the transaction
     *
     * @param $attributes
     */
    protected function validateTransactionFields($attributes): void
    {
        $totalPrice = 0;
        foreach ($attributes['products'] as $product) {
            $totalPrice += $product['price'];
        }

        $v = Validator::make($attributes, [
            'installment' => 'required|numeric|min:1',
            'currency' => 'required|in:' . implode(',', [
                    Currency::TL,
                    Currency::EUR,
                    Currency::GBP,
                    Currency::IRR,
                    Currency::USD
                ]),
            'paid_price' => 'numeric|max:' . $totalPrice
        ]);

        if ($v->fails()) {
            throw new TransactionFieldsException();
        }
    }

    /**
     * Creates transaction on iyzipay.
     *
     * @param CreditCard $creditCard
     * @param array $attributes
     * @param bool $subscription
     *
     * @return Payment
     * @throws TransactionSaveException
     */
    protected function createTransactionOnIyzipay($creditCard, $buyer_arr, $billaddress, $shipaddress, array $attributes, $subscription = false)
    {
        $this->validateTransactionFields($attributes);
        $paymentRequest = $this->createPaymentRequest($attributes, $subscription);
        $paymentRequest->setPaymentCard($this->preparePaymentCard($creditCard));
        $paymentRequest->setBuyer($this->prepareBuyer($buyer_arr));
        $paymentRequest->setShippingAddress($this->prepareAddress($shipaddress));
        $paymentRequest->setBillingAddress($this->prepareAddress($billaddress));
        $paymentRequest->setBasketItems($this->prepareBasketItems($attributes['products']));

        try {
            $payment = Payment::create($paymentRequest, $this->getOptions());
        } catch (\Exception $e) {
            throw new TransactionSaveException($e->getMessage());
        }

        unset($paymentRequest);

        return $payment;
    }

    protected function createPayThreedsPayment($request_arr)
    {
        $paymentRequest = $this->createThreedsPaymentRequest($request_arr);
        try {
            $payment = ThreedsPayment::create($paymentRequest, $this->getOptions());
        } catch (\Exception $e) {
            throw new TransactionSaveException($e->getMessage());
        }
        unset($paymentRequest);
        return $payment;
    }
    

    protected function createThreedsInitializeTransactionOnIyzipay($creditCard, $buyer_arr, $billaddress, $shipaddress, array $attributes, $subscription = false)
    {
        $this->validateTransactionFields($attributes);
        $paymentRequest = $this->createPaymentRequest($attributes, $subscription);
        $paymentRequest->setPaymentCard($this->preparePaymentCard($creditCard));
        $paymentRequest->setBuyer($this->prepareBuyer($buyer_arr));
        $paymentRequest->setShippingAddress($this->prepareAddress($shipaddress));
        $paymentRequest->setBillingAddress($this->prepareAddress($billaddress));
        $paymentRequest->setBasketItems($this->prepareBasketItems($attributes['products']));

        try {
            $payment = ThreedsInitialize::create($paymentRequest, $this->getOptions());
        } catch (\Exception $e) {
            throw new TransactionSaveException($e->getMessage());
        }

        unset($paymentRequest);

        return $payment;
    }

    /**
     *
     * @return Cancel
     * @throws TransactionVoidException
     */
    protected function createCancelOnIyzipay($iyzipay_key)
    {
        $cancelRequest = $this->prepareCancelRequest($iyzipay_key);

        try {
            $cancel = Cancel::create($cancelRequest, $this->getOptions());
        } catch (\Exception $e) {
            throw new TransactionVoidException($e->getMessage());
        }

        unset($cancelRequest);
        return $cancel;
    }

    /**
     *
     * @return Refund
     * @throws TransactionVoidException
     */
    protected function createRefundOnIyzipay($iyzipay_key, $price, $currency)
    {
        $refundRequest = $this->prepareRefundRequest($iyzipay_key, $price, $currency);

        try {
            $refund = Refund::create($refundRequest, $this->getOptions());
        } catch (\Exception $e) {
            throw new TransactionVoidException($e->getMessage());
        }

        unset($refundRequest);
        return $refund;
    }

    /**
     * Prepares create payment request class for iyzipay.
     *
     * @param array $attributes
     * @param bool $subscription
     * @return CreatePaymentRequest
     */
    private function createPaymentRequest(array $attributes, $subscription = false)
    {
        $paymentRequest = new CreatePaymentRequest();
        $paymentRequest->setLocale($this->getLocale());

        $totalPrice = 0;
        foreach ($attributes['products'] as $product) {
            $totalPrice += $product['price'];
        }

        $paymentRequest->setPrice($totalPrice);
        $paymentRequest->setPaidPrice($totalPrice); // @todo this may change
        $paymentRequest->setCurrency($attributes['currency']);
        $paymentRequest->setInstallment($attributes['installment']);
        $paymentRequest->setPaymentChannel(PaymentChannel::WEB);
        $paymentRequest->setPaymentGroup(($subscription) ? PaymentGroup::SUBSCRIPTION : PaymentGroup::PRODUCT);
        if(isset($attributes['call_Back_url']) && $attributes['call_Back_url']!=""){
            $paymentRequest->setCallbackUrl($attributes['call_Back_url']);
        }

        return $paymentRequest;
    }

    private function createThreedsPaymentRequest(array $attributes)
    {
        $paymentRequest = new CreateThreedsPaymentRequest();
        $paymentRequest->setLocale($this->getLocale());
        $paymentRequest->setConversationId($attributes['conversationId']);
        $paymentRequest->setPaymentId($attributes['paymentId']); // @todo this may change
        $paymentRequest->setConversationData($attributes['conversationData']);

        return $paymentRequest;
    }

    /**
     * Prepares cancel request class for iyzipay
     *
     * @param $iyzipayKey
     * @return CreateCancelRequest
     */
    private function prepareCancelRequest($iyzipayKey)
    {
        $cancelRequest = new CreateCancelRequest();
        $cancelRequest->setPaymentId($iyzipayKey);
        $cancelRequest->setIp(request()->ip());
        $cancelRequest->setLocale($this->getLocale());

        return $cancelRequest;
    }

    /**
     * Prepares refund request class for iyzipay
     *
     * @param $iyzipayKey
     * @param $price
     * @param $currency
     * @return CreateRefundRequest
     */
    private function prepareRefundRequest($iyzipayKey, $price, $currency)
    {
        $refundRequest = new CreateRefundRequest();
        $refundRequest->setPaymentTransactionId($iyzipayKey);
        $refundRequest->setPrice($price);
        $refundRequest->setCurrency($currency);
        $refundRequest->setIp(request()->ip());
        $refundRequest->setLocale($this->getLocale());

        return $refundRequest;
    }

    /**
     * Prepares payment card class for iyzipay
     *
     * @param CreditCard $creditCard
     * @return PaymentCard
     */
    private function preparePaymentCard($creditCard)
    {
        $paymentCard = new PaymentCard();
        $paymentCard->setCardUserKey($creditCard['iyzipay_key']);
        $paymentCard->setCardToken($creditCard['token']);

        return $paymentCard;
    }

    /**
     * Prepares buyer class for iyzipay
     *
     * @return Buyer
     */
    private function prepareBuyer($buyer_arr)
    {
        $CurrentDateTime = Carbon::now()->format('Y-m-d H:i:s');
        $buyer = new Buyer();
        $buyer->setId($buyer_arr['id']);
        $buyer->setName($buyer_arr['firstName']);
        $buyer->setSurname($buyer_arr['lastName']);
        $buyer->setEmail($buyer_arr['email']);
        $buyer->setGsmNumber($buyer_arr['mobileNumber']);
        $buyer->setIdentityNumber($buyer_arr['identityNumber']);
        $buyer->setCity($buyer_arr['city']);
        $buyer->setCountry($buyer_arr['country']);
        $buyer->setRegistrationAddress($buyer_arr['address']);
        $buyer->setIp(request()->ip());
        $buyer->setLastLoginDate($CurrentDateTime);
        $buyer->setRegistrationDate($CurrentDateTime);

        return $buyer;
    }

    /**
     * Prepares address class for iyzipay.
     *
     * @param string $type
     * @return Address
     */
    private function prepareAddress($address_arr)
    {
        $address = new Address();
        $address->setContactName($address_arr['name']);
        $address->setCountry($address_arr['country']);
        $address->setAddress($address_arr['address']);
        $address->setCity($address_arr['city']);

        return $address;
    }

    /**
     * Prepares basket items class for iyzipay.
     *
     * @param $products
     * @return array
     */
    private function prepareBasketItems($products): array
    {
        $basketItems = [];

        foreach ($products as $product) {
            $item = new BasketItem();
            $item->setId($product['id']);
            $item->setName($product['name']);
            $item->setCategory1($product['category']);
            $item->setPrice($product['price']);
            $item->setItemType($product['type']);
            $basketItems[] = $item;
        }

        return $basketItems;
    }

    abstract protected function getLocale(): string;

    abstract protected function getOptions(): Options;
}