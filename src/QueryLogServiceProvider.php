<?php namespace Haruncpi\QueryLog;

use Illuminate\Support\ServiceProvider;

class QueryLogServiceProvider extends ServiceProvider
{

    /**
     * @throws \Exception
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/query-log.php' => config_path('query-log.php'),
            ], 'config');
        }
        if (env('QUERY_LOG', false)) {
            new QueryLog;
        }
    }

    public function register()
    {
    }

}