<?php

namespace xuanhieu080\ChannelMessaging;

use Illuminate\Support\ServiceProvider;
use xuanhieu080\ChannelMessaging\Console\Commands\SyncEbayMessagesCommand;
use xuanhieu080\ChannelMessaging\Console\Commands\SyncFacebookMessagesCommand;
use xuanhieu080\ChannelMessaging\Console\Commands\SyncShopifyMessagesCommand;
use xuanhieu080\ChannelMessaging\Console\Commands\SyncWalmartMessagesCommand;

class ChannelMessagingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/message-hub.php',
            'message-hub'
        );
    }

    public function boot(): void
    {
        // publish config
        $this->publishes([
            __DIR__.'/../config/message-hub.php' => config_path('message-hub.php'),
        ], 'message-hub-config');

        // load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncEbayMessagesCommand::class,
                SyncFacebookMessagesCommand::class,
                SyncShopifyMessagesCommand::class,
                SyncWalmartMessagesCommand::class,
            ]);
        }
    }
}
