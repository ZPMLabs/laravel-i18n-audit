<?php

declare(strict_types=1);

namespace App\Http\Controllers;

final class HomeController
{
    public function __invoke(): string
    {
        $value = 'name';

        __('auth.failed');
        trans('messages.welcome');
        __('Dynamic ' . $value);

        return 'ok';
    }
}
