<?php

namespace Antigravity\MetaAdsAttribution\Commands;

use Illuminate\Console\Command;

class MetaAttributionInstallCommand extends Command
{
    protected $signature = 'meta-attribution:install';

    protected $description = 'Install and publish Meta Ads Attribution & Delivered Conversions package assets';

    public function handle()
    {
        $this->info('🚀 Installing Meta Ads Attribution & Delivered Conversions Package...');

        $this->comment('Publishing configuration...');
        $this->call('vendor:publish', [
            '--provider' => "Antigravity\MetaAdsAttribution\MetaAdsAttributionServiceProvider",
            '--tag' => "meta-attribution-config",
        ]);

        $this->comment('Publishing migrations...');
        $this->call('vendor:publish', [
            '--provider' => "Antigravity\MetaAdsAttribution\MetaAdsAttributionServiceProvider",
            '--tag' => "meta-attribution-migrations",
        ]);

        $this->comment('Publishing views...');
        $this->call('vendor:publish', [
            '--provider' => "Antigravity\MetaAdsAttribution\MetaAdsAttributionServiceProvider",
            '--tag' => "meta-attribution-views",
        ]);

        if ($this->confirm('Would you like to run the package migrations now?', true)) {
            $this->call('migrate');
        }

        $this->info('✅ Meta Ads Attribution package installed successfully!');
        $this->line('');
        $this->info('Next steps:');
        $this->line('1. Set your Meta credentials in .env:');
        $this->line('   META_PIXEL_ID=your_pixel_id');
        $this->line('   META_ACCESS_TOKEN=your_capi_access_token');
        $this->line('');
        $this->line('2. Add the HasMetaAttribution trait to your Order model:');
        $this->line('   use Antigravity\MetaAdsAttribution\Traits\HasMetaAttribution;');
        $this->line('');
        $this->line('3. Add @metaPixel to your main layout head section.');
        $this->line('4. Visit /admin/meta-attribution for your analytics dashboard!');
    }
}
