<?php

namespace Abheranj\Iyzipay\Traits;

use Abheranj\Iyzipay\Exceptions\Card\CardRemoveException;
use Abheranj\Iyzipay\Exceptions\Card\CardSaveException;
use Abheranj\Iyzipay\Exceptions\Fields\CardFieldsException;
use Illuminate\Support\Facades\Validator;
use Iyzipay\Model\Card;
use Iyzipay\Model\CardInformation;
use Iyzipay\Options;
use Iyzipay\Request\CreateCardRequest;
use Iyzipay\Request\DeleteCardRequest;

trait PreparesCreditCardRequest
{

    /**
     * @param $attributes
     * @throws CardFieldsException
     */
    private function validateCreditCardAttributes($attributes): void
    {
        $v = Validator::make($attributes, [
            'alias'     => 'required',
            'holder'    => 'required',
            'number'    => 'required|digits_between:15,16',
            'month'     => 'required|digits:2',
            'year'      => 'required|digits:4'
        ]);

        if ($v->fails()) {
            throw new CardFieldsException(implode(',', $v->errors()->all()));
        }
    }

    /**
     * Prepares credit card on iyzipay.
     *
     * @param $attributes
     * @return Card
     * @throws CardSaveException
     */
    private function createCardOnIyzipay($email, $iyzipay_key, $attributes)
    {
        $cardRequest = $this->createCardRequest($email, $iyzipay_key, $attributes);

        try {
            $card = Card::create($cardRequest, $this->getOptions());
        } catch (\Exception $e) {
            throw new CardSaveException($e->getMessage());
        }
        unset($cardRequest);
        return $card;
    }

    /**
     * Prepare card request class for iyzipay.
     *
     * @param $attributes
     * @return CreateCardRequest
     */
    private function createCardRequest($email, $iyzipay_key, $attributes)
    {
        $cardRequest = new CreateCardRequest();
        $cardRequest->setLocale($this->getLocale());
        $cardRequest->setEmail($email);

        if (!empty($iyzipay_key)) {
            $cardRequest->setCardUserKey($iyzipay_key);
        }

        $cardRequest->setCard($this->createCardInformation($attributes));
        return $cardRequest;
    }

    /**
     * Removes a card on iyzipay
     *
     * @throws CardRemoveException
     */
    private function removeCardOnIyzipay($iyzipay_key, $token)
    {
        try {
            $result = Card::delete($this->removeCardRequest($iyzipay_key, $token), $this->getOptions());
        } catch (\Exception $e) {
            throw new CardRemoveException($e->getMessage());
        }

        return $result;
    }

    /**
     * Prepares remove card request class for iyzipay.
     *
     * @return DeleteCardRequest
     */
    private function removeCardRequest($iyzipay_key, $token)
    {
        $removeRequest = new DeleteCardRequest();
        $removeRequest->setCardUserKey($iyzipay_key);
        $removeRequest->setCardToken($token);
        $removeRequest->setLocale($this->getLocale());

        return $removeRequest;
    }

    /**
     * Prepares card information class for iyzipay
     *
     * @param $attributes
     * @return CardInformation
     */
    private function createCardInformation($attributes)
    {
        $cardInformation = new CardInformation();
        $cardInformation->setCardAlias($attributes['alias']);
        $cardInformation->setCardHolderName($attributes['holder']);
        $cardInformation->setCardNumber($attributes['number']);
        $cardInformation->setExpireMonth($attributes['month']);
        $cardInformation->setExpireYear($attributes['year']);

        return $cardInformation;
    }

    abstract protected function getLocale(): string;

    abstract protected function getOptions(): Options;
}
