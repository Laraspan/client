<?php

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Notification;
use LaraSpan\Client\EventBuffer;
use LaraSpan\Client\Listeners\HttpClientListener;
use LaraSpan\Client\Listeners\MailListener;
use LaraSpan\Client\Listeners\NotificationListener;
use LaraSpan\Client\Listeners\QueryListener;
use LaraSpan\Client\Listeners\RequestListener;
use Symfony\Component\Mime\Email;

// ---------------------------------------------------------------------------
// HttpClientListener: URI userinfo stripping
// ---------------------------------------------------------------------------

it('strips userinfo from outgoing request URLs', function () {
    $buffer = app(EventBuffer::class);
    $listener = app(HttpClientListener::class);

    $url = 'https://user:password@api.example.com/endpoint';

    $psrRequest = new GuzzleHttp\Psr7\Request('GET', $url);
    $clientRequest = new ClientRequest($psrRequest);

    $psrResponse = new GuzzleHttp\Psr7\Response(200);
    $clientResponse = new ClientResponse($psrResponse);

    $sendingEvent = new RequestSending($clientRequest);
    $listener->handleSending($sendingEvent);

    $responseEvent = new ResponseReceived($clientRequest, $clientResponse);
    $listener->handleResponse($responseEvent);

    expect($buffer->count())->toBe(1);

    $events = $buffer->flush();
    $capturedUrl = $events[0]['payload']['url'];

    expect($capturedUrl)->not->toContain('user:password@');
    expect($capturedUrl)->toContain('api.example.com/endpoint');
});

it('preserves URLs without userinfo', function () {
    $buffer = app(EventBuffer::class);
    $listener = app(HttpClientListener::class);

    $url = 'https://api.example.com/endpoint';

    $psrRequest = new GuzzleHttp\Psr7\Request('GET', $url);
    $clientRequest = new ClientRequest($psrRequest);

    $psrResponse = new GuzzleHttp\Psr7\Response(200);
    $clientResponse = new ClientResponse($psrResponse);

    $sendingEvent = new RequestSending($clientRequest);
    $listener->handleSending($sendingEvent);

    $responseEvent = new ResponseReceived($clientRequest, $clientResponse);
    $listener->handleResponse($responseEvent);

    expect($buffer->count())->toBe(1);

    $events = $buffer->flush();
    expect($events[0]['payload']['url'])->toBe($url);
});

// ---------------------------------------------------------------------------
// QueryListener: file and line capture
// ---------------------------------------------------------------------------

it('captures file and line keys for queries', function () {
    $buffer = app(EventBuffer::class);
    $listener = app(QueryListener::class);

    $event = new QueryExecuted('select * from users where id = ?', [1], 3.5, resolve('db'));
    $listener->handle($event);

    expect($buffer->count())->toBe(1);

    $events = $buffer->flush();
    $payload = $events[0]['payload'];

    expect($payload)->toHaveKeys(['file', 'line']);
});

// ---------------------------------------------------------------------------
// NotificationListener: anonymous class normalization
// ---------------------------------------------------------------------------

it('normalizes anonymous notification class names', function () {
    $buffer = app(EventBuffer::class);
    $listener = app(NotificationListener::class);

    // Create a real anonymous notification class
    $notification = new class extends Notification
    {
        public function via($notifiable): array
        {
            return ['mail'];
        }
    };

    $notifiable = new class
    {
        public function getKey(): int
        {
            return 1;
        }
    };

    $sendingEvent = new NotificationSending($notifiable, $notification, 'mail');
    $listener->handleSending($sendingEvent);

    $sentEvent = new NotificationSent($notifiable, $notification, 'mail', null);
    $listener->handleSent($sentEvent);

    expect($buffer->count())->toBe(1);

    $events = $buffer->flush();
    $notificationClass = $events[0]['payload']['notification_class'];

    // If the class name was anonymous, it should be normalized
    if (str_contains(get_class($notification), '@anonymous')) {
        expect($notificationClass)->toEndWith('@anonymous');
        expect($notificationClass)->not->toMatch('/@anonymous.+\/.+/');
    } else {
        // Named class - just verify it's captured
        expect($notificationClass)->toBeString();
    }
});

// ---------------------------------------------------------------------------
// MailListener: skip mail events from notifications
// ---------------------------------------------------------------------------

it('skips mail events originating from notifications', function () {
    $buffer = app(EventBuffer::class);
    $listener = app(MailListener::class);

    $message = (new Email)
        ->subject('Test Subject')
        ->from('sender@example.com')
        ->to('recipient@example.com');

    $dataWithNotification = ['__laravel_notification' => 'App\\Notifications\\TestNotification'];

    $sendingEvent = new MessageSending($message, $dataWithNotification);
    $listener->handleSending($sendingEvent);

    $sentMessage = Mockery::mock(SentMessage::class);
    $sentMessage->shouldReceive('getOriginalMessage')->andReturn($message);

    $sentEvent = new MessageSent($sentMessage, $dataWithNotification);
    $listener->handleSent($sentEvent);

    expect($buffer->count())->toBe(0);
});

it('captures mail events not from notifications', function () {
    $buffer = app(EventBuffer::class);
    $listener = app(MailListener::class);

    $message = (new Email)
        ->subject('Test Subject')
        ->from('sender@example.com')
        ->to('recipient@example.com');

    $data = [];

    $sendingEvent = new MessageSending($message, $data);
    $listener->handleSending($sendingEvent);

    $sentMessage = Mockery::mock(SentMessage::class);
    $sentMessage->shouldReceive('getOriginalMessage')->andReturn($message);

    $sentEvent = new MessageSent($sentMessage, $data);
    $listener->handleSent($sentEvent);

    expect($buffer->count())->toBe(1);

    $events = $buffer->flush();
    expect($events[0]['type'])->toBe('mail');
    expect($events[0]['payload']['subject'])->toBe('Test Subject');
});

// ---------------------------------------------------------------------------
// RequestListener: payload capture on 500 only
// ---------------------------------------------------------------------------

it('captures request payload on 500 errors when enabled', function () {
    config()->set('laraspan.capture.payload', true);

    $buffer = app(EventBuffer::class);
    $listener = app(RequestListener::class);

    $request = Request::create('/test', 'POST', ['username' => 'john', 'action' => 'login']);
    $request->attributes->set('laraspan_start_time', microtime(true) - 0.1);

    $response = new Response('Server Error', 500);

    $event = new RequestHandled($request, $response);
    $listener->handle($event);

    expect($buffer->count())->toBe(1);

    $events = $buffer->flush();
    $payload = $events[0]['payload'];

    expect($payload)->toHaveKey('request_payload');
    expect($payload['request_payload'])->toHaveKey('username', 'john');
});

it('does not capture request payload on 200 when enabled', function () {
    config()->set('laraspan.capture.payload', true);

    $buffer = app(EventBuffer::class);
    $listener = app(RequestListener::class);

    $request = Request::create('/test', 'POST', ['username' => 'john']);
    $request->attributes->set('laraspan_start_time', microtime(true) - 0.1);

    $response = new Response('OK', 200);

    $event = new RequestHandled($request, $response);
    $listener->handle($event);

    expect($buffer->count())->toBe(1);

    $events = $buffer->flush();
    $payload = $events[0]['payload'];

    expect($payload)->not->toHaveKey('request_payload');
});

it('does not capture request payload on 500 when disabled', function () {
    config()->set('laraspan.capture.payload', false);

    $buffer = app(EventBuffer::class);
    $listener = app(RequestListener::class);

    $request = Request::create('/test', 'POST', ['username' => 'john']);
    $request->attributes->set('laraspan_start_time', microtime(true) - 0.1);

    $response = new Response('Server Error', 500);

    $event = new RequestHandled($request, $response);
    $listener->handle($event);

    expect($buffer->count())->toBe(1);

    $events = $buffer->flush();
    $payload = $events[0]['payload'];

    expect($payload)->not->toHaveKey('request_payload');
});
