<?php

namespace Amada\HttpProxy;

use Amada\HttpProxy\AuthorizedClient;
use Amada\HttpProxy\PlainClient;
use Amada\HttpProxy\RemoteApiService;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

class RemoteApiServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            $this->configPath() => config_path('api.php'),
        ]);
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom($this->configPath(), 'api');

        $config = $this->app['config']->get('api');
        foreach ($config as $platform => $item) {
            $this->app->bind($platform, function (Application $app) use ($item) {
                /** @var LoggerInterface $logger */
                $logger = $app->make('log');
                if (isset($item['app_key'])) {
                    $client = new AuthorizedClient($item, $logger);
                }
//                elseif (isset($item['private'])) {
//                    $client = new AsymmetricSignClient($item, $logger);
//                }
                else {
                    $client = new PlainClient($item, $logger);
                }
                return new RemoteApiService($client);
            });
        }
    }

    /**
     * @return string
     */
    private function configPath()
    {
        return __DIR__ . '/../config/api.php';
    }
}
