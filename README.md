# Iyzipay laravel Package

[![Issues](https://img.shields.io/github/issues/abheranj/iyzipay-laravel?style=flat-square)](https://github.com/abheranj/iyzipay-laravel/issues)
[![Stars](https://img.shields.io/github/stars/abheranj/iyzipay-laravel?style=flat-square)](https://github.com/abheranj/iyzipay-laravel/stargazers)

Iyzipay (by AbheRanj) integration for your Laravel projects.

You can sign up for an iyzipay sandbox account at https://sandbox-merchant.iyzipay.com/auth/login

## Install

```shell
composer require abheranj/iyzipay
```

Service provider

> config/app.php

```php
'providers' => [
    ...
    Abheranj\Iyzipay\IyzipayServiceProvider::class,
];
```

Facades

> config/app.php

```php
'aliases' => [
    ...
    'Iyzipay' => Abheranj\Iyzipay\Facades\IyzipayService::class,
];
```

.env

> .env

```php
# IYZIPAY_PAYMENT_MODE for sanbox will be "test" and for live will be "live" 
IYZIPAY_PAYMENT_MODE="test"
SANDBOX_IYZIPAY_BASE_URL="https://sandbox-api.iyzipay.com"
SANDBOX_IYZIPAY_API_KEY="Sanbox Api Key"
SANDBOX_IYZIPAY_SECRET_KEY="Sanbox Secret Key"

LIVE_IYZIPAY_BASE_URL="Live url"
LIVE_IYZIPAY_API_KEY="Live Api Key"
LIVE_IYZIPAY_SECRET_KEY="Live Secret Key"
```

## Features

- Iyzipay::addCreditCard($email, $iyzipay_key, array $attributes)
- Iyzipay::removeCreditCard($iyzipay_key, $token)
- Iyzipay::singlePayment($creditCard, $buyer_arr, $billaddress, $shipaddress, $products, $currency, $installment, $subscription = false)
- Iyzipay::cancelPayment($iyzipay_key)
- Iyzipay::refundPayment($iyzipay_key, $price, $currency)
- Iyzipay::ThreedsInitializePayment($CardArr, $BuyerArr, $AddressArr, $AddressArr, $ProductArr, $Currency, $Installment, $CallBackUrl);
- Iyzipay::PayThreedsPayment($requestArr)

## Usage

### Add Credit Card

```php
    
    // Add Credit Card

    $card_details = [];
    $card_details['alias']  = 'Card'.Auth::user()->id;
    $card_details['holder'] = $request->card_name;
    $card_details['number'] = $request->card_number;
    $card_details['month']  = $request->exp_month;
    $card_details['year']   = $request->exp_year;
    $iyzipay_key = '';

    $CardRes = Iyzipay::addCreditCard(Auth::user()->email, $iyzipay_key, $card_details);

    if($CardRes->getStatus() != 'success'){
        Session::flash('message', $CardRes->getErrorMessage()); 
        Session::flash('icon', 'warning'); 
        return redirect()->back()->withInput($request->input());
    }

    // Store in MySQL Database 

    $Card = $this->CardsRep->AddEditCards(
        $request->card_name,
        $CardRes->getCardAlias(), 
        $request->card_number, 
        $CardRes->getCardToken(), 
        $CardRes->getCardUserKey(),  //iyzipay_key
        $CardRes->getCardBankName()
    );

```

### Remove Credit Card

```php

    // Remove credit card

    $CardData       = $this->CardsRep->GetCardById($id); // From MySQL Database
    $IyzipayCard    = Iyzipay::removeCreditCard($CardData->iyzipay_key, $CardData->token);
    
    if ($IyzipayCard->getStatus() != 'success') {
        Session::flash('message', $IyzipayCard->getErrorMessage()); 
        Session::flash('icon', 'warning'); 
        return redirect()->back();
    }

    $CardData->delete(); // Delete From MySQL Database

```

### Single Payment

```php

    use Iyzipay\Model\BasketItemType;
    use Iyzipay\Model\Currency;
    use Illuminate\Support\Facades\DB;

    DB::beginTransaction();
        
        $ProductData = $this->ProductRep->GetProductById($request->pid); //From MySQL Database
        
        $CardArr = [];
        $CardArr['iyzipay_key'] = $Card->iyzipay_key; // From MySQL Database 
        $CardArr['token']       = $Card->token;
        
        $BuyerArr = [];
        $BuyerArr['id']             = 'B'.Auth::user()->id;
        $BuyerArr['firstName']      = Auth::user()->name;
        $BuyerArr['lastName']       = Auth::user()->surname;
        $BuyerArr['email']          = Auth::user()->email;
        $BuyerArr['mobileNumber']   = Auth::user()->phone_no;
        $BuyerArr['identityNumber'] = Auth::user()->phone_no;
        $BuyerArr['city']           = $request->city;
        $BuyerArr['country']        = $request->country;
        $BuyerArr['address']        = $request->address;
        
        $AddressArr = [];
        $AddressArr['name']     = $request->name;
        $AddressArr['city']     = $request->city;
        $AddressArr['country']  = $request->country;
        $AddressArr['address']  = $request->address;
        $AddressArr['zipcode']  = $request->postcode;
        
        $ShipAddressArr = $AddressArr;

        $ProductArr = [];
        $ProductArr[0]['id']       = $ProductData['id'];
        $ProductArr[0]['name']     = $ProductData['name'];
        $ProductArr[0]['category'] = 'Cloth';
        $ProductArr[0]['type']     = BasketItemType::VIRTUAL;
        $ProductArr[0]['price']    = $ProductData['price'];
        
        $Currency       = Currency::TL;
        $Installment    = 1;

        $Transaction = Iyzipay::singlePayment($CardArr, $BuyerArr, $AddressArr, $ShipAddressArr, $ProductArr, $Currency, $Installment);
        
        if($Transaction->getStatus() == 'success'){
            
            $Products       = [];
            $PayoutMerchant = [];
            foreach ($Transaction->getPaymentItems() as $paymentItem) {
                $Products[] = [
                    'iyzipay_key'                   => $paymentItem->getPaymentTransactionId(),
                    'paidPrice'                     => $paymentItem->getPaidPrice(),
                    'product'                       => [$ProductData]
                ];
                $PayoutMerchant[] = [
                    'merchantCommissionRate'        => $paymentItem->getMerchantCommissionRate(),
                    'merchantCommissionRateAmount'  => $paymentItem->getMerchantCommissionRateAmount(),
                    'merchantCommissionRateAmount'  => $paymentItem->getMerchantCommissionRateAmount(),
                    'iyziCommissionRateAmount'      => $paymentItem->getIyziCommissionRateAmount(),
                    'iyziCommissionFee'             => $paymentItem->getIyziCommissionFee(),
                    'merchantPayoutAmount'          => $paymentItem->getMerchantPayoutAmount()
                ];
            }
            // $Transaction->getPaymentId() // iyzipay_key
             
            $Payment = $this->TransactionsRep->AddTransaction($Transaction->getPaidPrice(), $Transaction->getPaymentId(), $Transaction->getCurrency(), $Card->id, $Products, $PayoutMerchant, $AddressArr); // Store in MySQL Database 
   
   
            DB::commit();

            Session::flash('message', 'Order placed successfully.'); 
            Session::flash('icon', 'success'); 
            return redirect()->back();

        } else {
            DB::rollback();
            Session::flash('message', $Transaction->getErrorMessage()); 
            Session::flash('icon', 'warning'); 
            return redirect()->back()->withInput($request->input());
        }

```

### Cancel Payment

```php

        $transactionObj =  $this->TransactionsRep->GetTransactionById($id); // Get From MySQL Database
        $IyzipayCancel  = Iyzipay::cancelPayment($transactionObj->iyzipay_key);

        if ($IyzipayCancel->getStatus() != 'success') {
            Session::flash('message', $IyzipayCancel->getErrorMessage()); 
            Session::flash('icon', 'warning'); 
            return redirect()->back();
        }

        $Cancel[] = [
            'type'        => 'cancel',
            'amount'      => $IyzipayCancel->getPrice(),
            'iyzipay_key' => $IyzipayCancel->getPaymentId()
        ];

        $this->TransactionsRep->refundTransaction($transactionObj, $Cancel); // Update record in MySQL Database

```

### Refund Payment

```php

        $transactionObj =  $this->TransactionsRep->GetTransactionById($id); // Get From MySQL Database
        $Currency       = Currency::TL;
        $Products       = json_decode($transactionObj->products);
        $Refunds        = [];

        foreach ($Products as $Product) {
            $IyzipayRefund  = Iyzipay::refundPayment($Product->iyzipay_key, $Product->paidPrice, $Currency);
            if ($IyzipayRefund->getStatus() != 'success') {
                Session::flash('message', $IyzipayRefund->getErrorMessage()); 
                Session::flash('icon', 'warning'); 
                return redirect()->back();
            }
            $Refunds[] = [
                'type'                      => 'refund',
                'amount'                    => $IyzipayRefund->getPrice(),
                'iyzipay_payment_key'       => $IyzipayRefund->getPaymentId(),
                'iyzipay_transaction_key'   => $IyzipayRefund->getPaymentTransactionId(),
                'currency'                  => $IyzipayRefund->getCurrency()
            ];
        }
        
        $this->TransactionsRep->refundTransaction($transactionObj, $Refunds); // Update record in MySQL Database
        $Amount = $transactionObj->currency.' '.$IyzipayRefund->getPrice();

        Session::flash('message', $Amount.' Amount Refunded successfully.'); 
        Session::flash('icon', 'success'); 
        return redirect()->back();

```

## 3D Auth (3D Secure) 

### Initialize 3D Auth payment 

``` php

    try{
        DB::beginTransaction();
        $SubscriptionData = $this->SubscriptionsRep->GetSubscriptionById($request->pid);
        
        $card_details = [];
        $card_details['alias']  = 'Card'.Auth::user()->id;
        $card_details['holder'] = $request->card_name;
        $card_details['number'] = $request->card_number;
        $card_details['month']  = $request->exp_month;
        $card_details['year']   = $request->exp_year;
    
        $CardRes = Iyzipay::addCreditCard(Auth::user()->email, '', $card_details);
        if($CardRes->getStatus() != 'success'){
            Session::flash('message', $CardRes->getErrorMessage()); 
            Session::flash('icon', 'warning'); 
            Session::flash('heading', __('messages.common.warning'));
            return redirect()->back()->withInput($request->input());
        }
        
        $Card = $this->CardsRep->AddEditCards(
            $request->card_name,
            $CardRes->getCardAlias(), 
            $request->card_number, 
            $CardRes->getCardToken(), 
            $CardRes->getCardUserKey(), 
            $CardRes->getCardBankName()
        );
        
        $CardArr = [];
        $CardArr['iyzipay_key'] = $Card->iyzipay_key;
        $CardArr['token']       = $Card->token;
        
        $BuyerArr = [];
        $BuyerArr['id']             = 'B'.Auth::user()->id;
        $BuyerArr['firstName']      = Auth::user()->name;
        $BuyerArr['lastName']       = Auth::user()->surname;
        $BuyerArr['email']          = Auth::user()->email;
        $BuyerArr['mobileNumber']   = Auth::user()->phone_no;
        $BuyerArr['identityNumber'] = Auth::user()->phone_no;
        $BuyerArr['city']           = $request->city;
        $BuyerArr['country']        = $request->country;
        $BuyerArr['address']        = $request->address;
        
        $AddressArr = [];
        $AddressArr['name']     = $request->name;
        $AddressArr['city']     = $request->city;
        $AddressArr['country']  = $request->country;
        $AddressArr['address']  = $request->address;
        $AddressArr['zipcode']  = $request->postcode;
        
        $ProductArr = [];
        $ProductArr[0]['id']       = $SubscriptionData['id'];
        $ProductArr[0]['name']     = $SubscriptionData['name'];
        $ProductArr[0]['category'] = 'Subscription';
        $ProductArr[0]['type']     = BasketItemType::VIRTUAL;
        $ProductArr[0]['price']    = $SubscriptionData['price'];
        
        $Currency       = Currency::TL;
        $Installment    = 1;
        $CallBackUrl    = route('payment_confirm');
        
        $Card->billing_info = json_encode($AddressArr);
        $Card->save();

        $Transaction = Iyzipay::ThreedsInitializePayment($CardArr, $BuyerArr, $AddressArr, $AddressArr, $ProductArr, $Currency, $Installment, $CallBackUrl);
        if($Transaction->getStatus() == 'success'){
            DB::commit();
            return view('subscriptions.payment_response', ['htmlform' => base64_encode($Transaction->getHtmlContent()), 'page_name'=>'Payment response']);
            
        } else {
            Session::flash('message', $Transaction->getErrorMessage()); 
            Session::flash('icon', 'warning'); 
            Session::flash('heading', __('messages.common.warning'));
            return redirect()->back()->withInput($request->input());
        }
    } catch (Exception $e) {
        DB::rollback();
        return response()->view('error.500', ['message'=>$e->getMessage()], 500);
    }

```

### 3D Auth payment 

``` php

    try{
            if($request->mdStatus!="1"){
                Session::flash('message', __('messages.transactions.mdStatus'.$request->mdStatus)); 
                Session::flash('icon', 'warning'); 
                Session::flash('heading', __('messages.common.warning'));
                return redirect()->route('dissubscriptions', ['page_name'=>'Subscription Plans']);
            }

            if ($request->status != 'success') {
                Session::flash('message', __('messages.common.somthing_wrong')); 
                Session::flash('icon', 'warning'); 
                Session::flash('heading', __('messages.common.warning'));
                return redirect()->route('dissubscriptions', ['page_name'=>'Subscription Plans']);
            }
            
            if(empty($request->paymentId)){
                Session::flash('message', __('messages.common.somthing_wrong')); 
                Session::flash('icon', 'warning'); 
                Session::flash('heading', __('messages.common.warning'));
                return redirect()->route('dissubscriptions', ['page_name'=>'Subscription Plans']);
            }
            
            $requestArr = array('paymentId'=>$request->paymentId, 'conversationData'=>$request->conversationData, 'conversationId'=>$request->conversationId);
            $Transaction    = Iyzipay::PayThreedsPayment($requestArr);
            
            if($Transaction->getStatus() == 'success'){
                $Transactions       = $Transaction->getPaymentItems();
                $SubscriptionData   = $this->SubscriptionsRep->GetSubscriptionById($Transactions[0]->getItemId());
                $CardToken          = $Transaction->getCardToken();
                $Card               = $this->CardsRep->GetCardByToken($CardToken); 
                
                if(empty($SubscriptionData)){
                    Session::flash('message', __('messages.subscriptions.not_found')); 
                    Session::flash('icon', 'warning'); 
                    Session::flash('heading', __('messages.common.warning'));
                    return redirect()->route('dissubscriptions', ['page_name'=>'Subscription Plans']);
                }
                
                $Products       = [];
                $PayoutMerchant = [];
                foreach ($Transaction->getPaymentItems() as $paymentItem) {
                    $Products[] = [
                        'iyzipay_key'                   => $paymentItem->getPaymentTransactionId(),
                        'paidPrice'                     => $paymentItem->getPaidPrice(),
                        'product'                       => [$SubscriptionData]
                    ];
                    $PayoutMerchant[] = [
                        'merchantCommissionRate'        => $paymentItem->getMerchantCommissionRate(),
                        'merchantCommissionRateAmount'  => $paymentItem->getMerchantCommissionRateAmount(),
                        'iyziCommissionRateAmount'      => $paymentItem->getIyziCommissionRateAmount(),
                        'iyziCommissionFee'             => $paymentItem->getIyziCommissionFee(),
                        'merchantPayoutAmount'          => $paymentItem->getMerchantPayoutAmount()
                    ];
                }
                
                $Payment = $this->TransactionsRep->AddTransaction($Transaction->getPaidPrice(), $Transaction->getPaymentId(), $Transaction->getCurrency(), $Card->id, $Products, $PayoutMerchant, json_decode($Card->billing_info));
                DB::commit();
                
                Session::flash('message', __('messages.subscriptions.buy_success')); 
                Session::flash('icon', 'success'); 
                Session::flash('heading', __('messages.common.success'));
                return redirect()->route('dissubscriptions', ['page_name'=>'Subscription Plans']);

            } else {
                DB::rollback();
                Session::flash('message', $Transaction->getErrorMessage()); 
                Session::flash('icon', 'warning'); 
                Session::flash('heading', __('messages.common.warning'));
                return redirect()->back()->withInput($request->input());
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->view('error.500', ['message'=>$e->getMessage()], 500);
        }

```

## Test Cards

Test cards that can be used to simulate a *successful* payment:

Card Number      | Bank                       | Card Type
-----------      | ----                       | ---------
5890040000000016 | Akbank                     | Master Card (Debit)  
5526080000000006 | Akbank                     | Master Card (Credit)  
4766620000000001 | Denizbank                  | Visa (Debit)  
4603450000000000 | Denizbank                  | Visa (Credit)
4729150000000005 | Denizbank Bonus            | Visa (Credit)  
4987490000000002 | Finansbank                 | Visa (Debit)  
5311570000000005 | Finansbank                 | Master Card (Credit)  
9792020000000001 | Finansbank                 | Troy (Debit)  
9792030000000000 | Finansbank                 | Troy (Credit)  
5170410000000004 | Garanti Bankası            | Master Card (Debit)  
5400360000000003 | Garanti Bankası            | Master Card (Credit)  
374427000000003  | Garanti Bankası            | American Express  
4475050000000003 | Halkbank                   | Visa (Debit)  
5528790000000008 | Halkbank                   | Master Card (Credit)  
4059030000000009 | HSBC Bank                  | Visa (Debit)  
5504720000000003 | HSBC Bank                  | Master Card (Credit)  
5892830000000000 | Türkiye İş Bankası         | Master Card (Debit)  
4543590000000006 | Türkiye İş Bankası         | Visa (Credit)  
4910050000000006 | Vakıfbank                  | Visa (Debit)  
4157920000000002 | Vakıfbank                  | Visa (Credit)  
5168880000000002 | Yapı ve Kredi Bankası      | Master Card (Debit)  
5451030000000000 | Yapı ve Kredi Bankası      | Master Card (Credit)  

*Cross border* test cards:

Card Number      | Country
-----------      | -------
4054180000000007 | Non-Turkish (Debit)
5400010000000004 | Non-Turkish (Credit)  
6221060000000004 | Iran  

Test cards to get specific *error* codes:

Card Number       | Description
-----------       | -----------
5406670000000009  | Success but cannot be cancelled, refund or post auth
4111111111111129  | Not sufficient funds
4129111111111111  | Do not honour
4128111111111112  | Invalid transaction
4127111111111113  | Lost card
4126111111111114  | Stolen card
4125111111111115  | Expired card
4124111111111116  | Invalid cvc2
4123111111111117  | Not permitted to card holder
4122111111111118  | Not permitted to terminal
4121111111111119  | Fraud suspect
4120111111111110  | Pickup card
4130111111111118  | General error
4131111111111117  | Success but mdStatus is 0
4141111111111115  | Success but mdStatus is 4
4151111111111112  | 3dsecure initialize failed