<?php

declare(strict_types=1);

namespace BenTools\MercurePHP\Tests\Integration;

use BenTools\MercurePHP\Tests\Unit\Hub\Controller\Auth;
use Clue\React\EventSource\EventSource;
use Clue\React\EventSource\MessageEvent;
use React\EventLoop\Loop;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Process\Process;

use function dirname;
use function explode;
use function sprintf;

it('works 🎉🥳', function () {
    $env = ['X_LISTEN' => $_ENV['X_LISTEN'] ?? '127.0.0.1:8080'];
    foreach (explode(',', $_ENV['SYMFONY_DOTENV_VARS'] ?? '') as $key) {
        $value = $_ENV[$key] ?? null;
        if (null === $value) {
            continue;
        }
        $env[$key] = $_ENV[$key];
    }
    $endpoint = sprintf('http://%s/.well-known/mercure', $env['X_LISTEN']);
    $process = new Process(['bin/mercure',], dirname(__DIR__, 2), $env);
    $process->start();

    usleep(1500000);

    $messages = [];
    Loop::addTimer(0.0, function () use ($endpoint, &$messages) {
        $listener = new EventSource(sprintf('%s?topic=*', $endpoint));
        $listener->on('message', function (MessageEvent $event) use (&$messages) {
            $messages[] = $event->data;
            Loop::stop();
        });
    });
    Loop::addTimer(0.05, function () use ($endpoint) {
        $JWTEncoder = Auth::getJWTEncoder();
        $publisher = HttpClient::create();
        $jwt = $JWTEncoder->encode(['mercure' => ['publish' => ['*']]]);
        $publisher->request('POST', $endpoint, [
            'body' => 'topic=/foo&data=itworks',
            'headers' => [
                'Authorization' => "Bearer $jwt",
                'Content-Type' => 'application/x-www-urlencoded',
            ],
        ]);
    });
    Loop::addTimer(1, fn() => Loop::stop());
    Loop::run();
    expect($messages[0] ?? null)->toBe('itworks');

    echo $process->getOutput();
});