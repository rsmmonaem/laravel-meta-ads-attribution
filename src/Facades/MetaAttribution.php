<?php

namespace Antigravity\MetaAdsAttribution\Facades;

use Illuminate\Support\Facades\Facade;
use Antigravity\MetaAdsAttribution\Services\MetaAttributionManager;

class MetaAttribution extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MetaAttributionManager::class;
    }
}
