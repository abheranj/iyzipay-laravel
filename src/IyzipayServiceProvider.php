<?php

namespace Abheranj\Iyzipay;

use Illuminate\Support\ServiceProvider;

class IyzipayServiceProvider extends ServiceProvider
{
    public function boot(){
        $this->publishes([ __DIR__.'/config/iyzipay.php' => config_path('iyzipay.php'), ]);
    }   
    
    public function register(){
        $this->mergeConfigFrom( __DIR__.'/config/iyzipay.php', 'iyzipay' );
    }   
}
