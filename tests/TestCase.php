<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Revolution\Google\Photos\Providers\PhotosServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            PhotosServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [];
    }

    /**
     * Define environment setup.
     *
     * @param  Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('google', [
            'scopes' => [
                'https://www.googleapis.com/auth/photoslibrary.appendonly',
                'https://www.googleapis.com/auth/photoslibrary.readonly.appcreateddata',
                'https://www.googleapis.com/auth/photoslibrary.edit.appcreateddata',
                'https://www.googleapis.com/auth/photospicker.mediaitems.readonly',
            ],
            'client_id' => 'test',
            'client_secret' => 'test',
        ]);
    }
}
