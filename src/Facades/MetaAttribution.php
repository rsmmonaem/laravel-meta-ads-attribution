<?php

namespace RsmMonaem\MetaAdsAttribution\Facades;

use Illuminate\Support\Facades\Facade;
use RsmMonaem\MetaAdsAttribution\Services\MetaAttributionManager;

class MetaAttribution extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MetaAttributionManager::class;
    }
}
