<?php

namespace Abheranj\Iyzipay\Facades;

use Illuminate\Support\Facades\Facade;

class IyzipayService extends Facade{

    protected static function getFacadeAccessor()
    {
        return \Abheranj\Iyzipay\IyzipayService::class;
    }
}