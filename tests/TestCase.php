<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        putenv('SESSION_DRIVER=array');
        putenv('CACHE_STORE=array');
        putenv('QUEUE_CONNECTION=sync');
        putenv('MAIL_MAILER=array');

        $_ENV['SESSION_DRIVER'] = 'array';
        $_ENV['CACHE_STORE'] = 'array';
        $_ENV['QUEUE_CONNECTION'] = 'sync';
        $_ENV['MAIL_MAILER'] = 'array';

        $_SERVER['SESSION_DRIVER'] = 'array';
        $_SERVER['CACHE_STORE'] = 'array';
        $_SERVER['QUEUE_CONNECTION'] = 'sync';
        $_SERVER['MAIL_MAILER'] = 'array';

        parent::setUp();
    }
}
