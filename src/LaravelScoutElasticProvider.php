<?php

namespace Tamayo\LaravelScoutElastic;

use Exception;
use Elasticsearch\ClientBuilder;
use Laravel\Scout\EngineManager;
use Illuminate\Support\ServiceProvider;
use Tamayo\LaravelScoutElastic\Engines\ElasticsearchEngine;

class LaravelScoutElasticProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->ensureElasticClientIsInstalled();

        resolve(EngineManager::class)->extend('elasticsearch', function () {
            $verify = config('scout.elasticsearch.ca_bundle') ?? config('scout.elasticsearch.verify');

            $builder = ClientBuilder::create()
                ->setHosts(config('scout.elasticsearch.hosts')); // logs requests, responses, errors

            if ($verify === false) {
                // cURL error 51 is a HOST (SAN) mismatch, not a CA-trust failure.
                // setSSLVerification(false) may only clear VERIFYPEER on es-php 7.17,
                // so disable BOTH peer and host checks explicitly.
                $builder->setConnectionParams([
                    'client' => [
                        'curl' => [
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_SSL_VERIFYHOST => 0,
                        ],                        
                        'connect_timeout' => config('scout.elasticsearch.connect_timeout', 2),
                        'timeout'         => config('scout.elasticsearch.timeout', 10),
                    ],
                ]);
            } else {
                // true  -> verify against system CA bundle
                // string -> verify against the given CA bundle path
                $builder->setSSLVerification($verify);
            }

            return new ElasticsearchEngine($builder->build());
        });
    }


    /**
     * Ensure the Elastic API client is installed.
     *
     * @return void
     *
     * @throws \Exception
     */
    protected function ensureElasticClientIsInstalled()
    {
        if (class_exists(ClientBuilder::class)) {
            return;
        }

        throw new Exception('Please install the Elasticsearch PHP client: elasticsearch/elasticsearch.');
    }
}
