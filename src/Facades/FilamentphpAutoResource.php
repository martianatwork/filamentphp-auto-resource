<?php

namespace Martianatwork\FilamentphpAutoResource\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Martianatwork\FilamentphpAutoResource\FilamentphpAutoResource
 */
class FilamentphpAutoResource extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Martianatwork\FilamentphpAutoResource\FilamentphpAutoResource::class;
    }
}
