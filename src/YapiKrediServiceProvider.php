<?php


namespace KumsalAgency\Payment\YapiKredi;


use Illuminate\Support\ServiceProvider;
use KumsalAgency\Payment\Payment;

class YapiKrediServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->afterResolving(Payment::class, function (Payment $payment) {
            $payment->extend("yapikredi", function ($application,$config) use ($payment) {
                return new YapiKredi($application,$config);
            });
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

    }
}