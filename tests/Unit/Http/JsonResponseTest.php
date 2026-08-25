<?php

declare(strict_types=1);

use Flick\Http\JsonResponse;

describe('JsonResponse', function () {
    test('it stores the data', function () {
        $data = ['success' => true, 'message' => 'Form submitted'];
        $response = new JsonResponse($data);

        expect($response->getData())->toBe($data);
    });

    test('it defaults to 200 status code', function () {
        $response = new JsonResponse(['success' => true]);

        expect($response->getStatusCode())->toBe(200);
    });

    test('it accepts custom status code', function () {
        $response = new JsonResponse(['error' => true], 400);

        expect($response->getStatusCode())->toBe(400);
    });

    test('it converts to array correctly', function () {
        $data = ['success' => true, 'data' => ['email' => 'test@example.com']];
        $response = new JsonResponse($data, 201);

        $array = $response->toArray();

        expect($array)->toBe([
            'type' => 'json',
            'data' => $data,
            'statusCode' => 201,
        ]);
    });

    test('it handles empty data array', function () {
        $response = new JsonResponse([]);

        expect($response->getData())->toBe([]);
    });

    test('it handles nested data structures', function () {
        $data = [
            'user' => [
                'name' => 'John',
                'email' => 'john@example.com',
                'addresses' => [
                    ['city' => 'New York', 'zip' => '10001'],
                    ['city' => 'Boston', 'zip' => '02101'],
                ],
            ],
        ];
        $response = new JsonResponse($data);

        expect($response->getData())->toBe($data);
    });

    test('it produces a non-empty body for invalid UTF-8 input (M3)', function () {
        $response = new JsonResponse(['name' => "bad \xB1 utf8"]);

        expect($response->getBody())->not->toBe('')
            ->and($response->getStatusCode())->toBe(200);
    });

    test('it returns a 500 error body when data cannot be encoded (M3)', function () {
        $response = new JsonResponse(['value' => INF]);

        expect($response->getStatusCode())->toBe(500)
            ->and($response->getBody())->not->toBe('')
            ->and($response->getBody())->toContain('error');
    });

    test('every reader agrees about the status of an un-encodable response', function () {
        // getStatusCode() reported the code the caller ASKED for while send()
        // emitted the one it actually used, so a framework integration reading
        // the getters built a 200 around a 500 body.
        $response = new JsonResponse(['value' => INF], 201);

        expect($response->getStatusCode())->toBe(500)
            ->and($response->toArray()['statusCode'])->toBe(500);
    });

    test('an encodable response keeps the status the caller asked for', function () {
        $response = new JsonResponse(['ok' => true], 201);

        expect($response->getStatusCode())->toBe(201)
            ->and($response->toArray()['statusCode'])->toBe(201)
            ->and($response->getBody())->toBe('{"ok":true}');
    });
});
