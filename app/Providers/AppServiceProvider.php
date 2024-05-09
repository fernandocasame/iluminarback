<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('hash', function () {
            return new \App\Sha1Md5Hasher;
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Validator::extend('custom_password', function ($attribute, $value, $parameters, $validator) {
            return Hash::check(md5($value), Auth::user()->password);
        });

        Hash::extend('sha1md5', function($value, $options = []) {
            if (!is_string($value)) {
                throw new InvalidArgumentException("El valor pasado a la función make() debe ser una cadena.");
            }
            return sha1(md5($value));
        });
    }
}
