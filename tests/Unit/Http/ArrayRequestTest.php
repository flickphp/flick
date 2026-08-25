<?php

declare(strict_types=1);

use Flick\Http\ArrayRequest;
use Flick\Http\NativeRequest;
use Flick\Http\RequestInterface;

describe('ArrayRequest', function () {
    it('implements RequestInterface', function () {
        $request = new ArrayRequest;

        expect($request)->toBeInstanceOf(RequestInterface::class);
    });

    describe('static factories', function () {
        it('creates GET request', function () {
            $request = ArrayRequest::createGet(['page' => '2', 'sort' => 'name']);

            expect($request->method())->toBe('GET');
            expect($request->query('page'))->toBe('2');
            expect($request->query('sort'))->toBe('name');
            expect($request->postAll())->toBe([]);
        });

        it('creates POST request', function () {
            $request = ArrayRequest::createPost(['email' => 'test@example.com', 'name' => 'John']);

            expect($request->method())->toBe('POST');
            expect($request->post('email'))->toBe('test@example.com');
            expect($request->post('name'))->toBe('John');
        });

        it('creates POST request with query params', function () {
            $request = ArrayRequest::createPost(['email' => 'test@example.com'], ['ref' => 'homepage']);

            expect($request->method())->toBe('POST');
            expect($request->post('email'))->toBe('test@example.com');
            expect($request->query('ref'))->toBe('homepage');
        });

        it('creates AJAX request', function () {
            $request = ArrayRequest::createAjax(['email' => 'test@example.com']);

            expect($request->method())->toBe('POST');
            expect($request->isAjax())->toBeTrue();
            expect($request->post('email'))->toBe('test@example.com');
        });

        it('creates multipart request', function () {
            $request = ArrayRequest::createMultipart(
                ['title' => 'My File'],
                ['file' => ['name' => 'doc.pdf', 'tmp_name' => '/tmp/abc', 'size' => 1024, 'error' => 0]]
            );

            expect($request->method())->toBe('POST');
            expect($request->post('title'))->toBe('My File');
            expect($request->hasFile('file'))->toBeTrue();
        });
    });

    describe('POST data', function () {
        it('returns POST value', function () {
            $request = ArrayRequest::createPost(['email' => 'test@example.com']);

            expect($request->post('email'))->toBe('test@example.com');
        });

        it('returns default for missing POST key', function () {
            $request = ArrayRequest::createPost([]);

            expect($request->post('missing', 'default'))->toBe('default');
        });

        it('returns all POST data', function () {
            $request = ArrayRequest::createPost(['a' => '1', 'b' => '2']);

            expect($request->postAll())->toBe(['a' => '1', 'b' => '2']);
        });

        it('checks if POST key exists', function () {
            $request = ArrayRequest::createPost(['exists' => 'yes']);

            expect($request->hasPost('exists'))->toBeTrue();
            expect($request->hasPost('missing'))->toBeFalse();
        });
    });

    describe('query data', function () {
        it('returns query value', function () {
            $request = ArrayRequest::createGet(['page' => '2']);

            expect($request->query('page'))->toBe('2');
        });

        it('returns default for missing query key', function () {
            $request = ArrayRequest::createGet([]);

            expect($request->query('missing', '1'))->toBe('1');
        });

        it('returns all query data', function () {
            $request = ArrayRequest::createGet(['a' => '1', 'b' => '2']);

            expect($request->queryAll())->toBe(['a' => '1', 'b' => '2']);
        });

        it('checks if query key exists', function () {
            $request = ArrayRequest::createGet(['exists' => 'yes']);

            expect($request->hasQuery('exists'))->toBeTrue();
            expect($request->hasQuery('missing'))->toBeFalse();
        });
    });

    describe('combined input', function () {
        it('returns POST value when key exists in both', function () {
            $request = ArrayRequest::createPost(['key' => 'post_value'], ['key' => 'query_value']);

            expect($request->input('key'))->toBe('post_value');
        });

        it('falls back to query when POST key missing', function () {
            $request = ArrayRequest::createPost([], ['key' => 'query_value']);

            expect($request->input('key'))->toBe('query_value');
        });

        it('returns default when key missing from both', function () {
            $request = ArrayRequest::createPost([]);

            expect($request->input('missing', 'default'))->toBe('default');
        });

        it('merges all input with POST priority', function () {
            $request = ArrayRequest::createPost(
                ['a' => 'post_a', 'b' => 'post_b'],
                ['a' => 'query_a', 'c' => 'query_c']
            );

            expect($request->all())->toBe([
                'a' => 'post_a',
                'c' => 'query_c',
                'b' => 'post_b',
            ]);
        });

        it('checks if key exists in either', function () {
            $request = ArrayRequest::createPost(['post_key' => 'yes'], ['query_key' => 'yes']);

            expect($request->has('post_key'))->toBeTrue();
            expect($request->has('query_key'))->toBeTrue();
            expect($request->has('missing'))->toBeFalse();
        });
    });

    describe('files', function () {
        it('returns file data', function () {
            $request = ArrayRequest::createMultipart([], [
                'avatar' => ['name' => 'photo.jpg', 'tmp_name' => '/tmp/abc', 'size' => 1024, 'error' => 0],
            ]);

            $file = $request->file('avatar');

            expect($file['name'])->toBe('photo.jpg');
            expect($file['size'])->toBe(1024);
        });

        it('returns null for missing file', function () {
            $request = ArrayRequest::createPost([]);

            expect($request->file('missing'))->toBeNull();
        });

        it('returns all files', function () {
            $request = ArrayRequest::createMultipart([], [
                'file1' => ['name' => 'a.txt', 'tmp_name' => '/tmp/a', 'size' => 100, 'error' => 0],
                'file2' => ['name' => 'b.txt', 'tmp_name' => '/tmp/b', 'size' => 200, 'error' => 0],
            ]);

            expect($request->files())->toHaveCount(2);
        });

        it('checks if file was uploaded', function () {
            $request = ArrayRequest::createMultipart([], [
                'uploaded' => ['name' => 'file.txt', 'tmp_name' => '/tmp/x', 'size' => 100, 'error' => UPLOAD_ERR_OK],
                'no_file' => ['name' => '', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_NO_FILE],
            ]);

            expect($request->hasFile('uploaded'))->toBeTrue();
            expect($request->hasFile('no_file'))->toBeFalse();
            expect($request->hasFile('missing'))->toBeFalse();
        });

        it('reports no file for an all-empty array-shaped upload (M1)', function () {
            $request = ArrayRequest::createMultipart([], [
                'photos' => [
                    'name' => ['', ''],
                    'tmp_name' => ['', ''],
                    'size' => [0, 0],
                    'error' => [UPLOAD_ERR_NO_FILE, UPLOAD_ERR_NO_FILE],
                ],
            ]);

            expect($request->hasFile('photos'))->toBeFalse();
        });

        it('reports a file when one slot of an array-shaped upload is populated (M1)', function () {
            $request = ArrayRequest::createMultipart([], [
                'photos' => [
                    'name' => ['', 'b.jpg'],
                    'tmp_name' => ['', '/tmp/b'],
                    'size' => [0, 200],
                    'error' => [UPLOAD_ERR_NO_FILE, UPLOAD_ERR_OK],
                ],
            ]);

            expect($request->hasFile('photos'))->toBeTrue();
        });
    });

    describe('server data', function () {
        it('returns server value', function () {
            $request = ArrayRequest::createGet()->setServer(['CUSTOM_VAR' => 'value']);

            expect($request->server('CUSTOM_VAR'))->toBe('value');
        });

        it('returns default for missing server key', function () {
            $request = ArrayRequest::createGet();

            expect($request->server('MISSING', 'default'))->toBe('default');
        });

        it('returns request method', function () {
            $get = ArrayRequest::createGet();
            $post = ArrayRequest::createPost([]);

            expect($get->method())->toBe('GET');
            expect($post->method())->toBe('POST');
        });

        it('checks request method', function () {
            $request = ArrayRequest::createPost([]);

            expect($request->isMethod('POST'))->toBeTrue();
            expect($request->isMethod('post'))->toBeTrue();
            expect($request->isMethod('GET'))->toBeFalse();
        });

        it('detects AJAX requests', function () {
            $ajax = ArrayRequest::createAjax([]);
            $regular = ArrayRequest::createPost([]);

            expect($ajax->isAjax())->toBeTrue();
            expect($regular->isAjax())->toBeFalse();
        });
    });

    describe('cookies', function () {
        it('returns cookie value', function () {
            $request = ArrayRequest::createGet()->withCookie('session', 'abc123');

            expect($request->cookie('session'))->toBe('abc123');
        });

        it('returns default for missing cookie', function () {
            $request = ArrayRequest::createGet();

            expect($request->cookie('missing', 'default'))->toBe('default');
        });

        it('checks if cookie exists', function () {
            $request = ArrayRequest::createGet()->withCookie('exists', 'yes');

            expect($request->hasCookie('exists'))->toBeTrue();
            expect($request->hasCookie('missing'))->toBeFalse();
        });

        it('deletes cookie', function () {
            $request = ArrayRequest::createGet()->withCookie('to_delete', 'value');

            expect($request->hasCookie('to_delete'))->toBeTrue();

            $request->deleteCookie('to_delete');

            expect($request->hasCookie('to_delete'))->toBeFalse();
            expect($request->wasCookieDeleted('to_delete'))->toBeTrue();
        });
    });

    describe('headers', function () {
        it('returns header value', function () {
            $request = ArrayRequest::createGet()->setServer(['HTTP_ACCEPT' => 'application/json']);

            expect($request->header('Accept'))->toBe('application/json');
        });

        it('handles Content-Type header', function () {
            $request = ArrayRequest::createPost([])->setServer(['CONTENT_TYPE' => 'application/json']);

            expect($request->header('Content-Type'))->toBe('application/json');
        });

        it('handles Content-Type case-insensitively (M2)', function () {
            $request = ArrayRequest::createPost([])->setServer(['CONTENT_TYPE' => 'application/json']);

            expect($request->header('content-type'))->toBe('application/json')
                ->and($request->header('CONTENT-TYPE'))->toBe('application/json');
        });

        it('handles Content-Length case-insensitively (M2)', function () {
            $request = ArrayRequest::createPost([])->setServer(['CONTENT_LENGTH' => '42']);

            expect($request->header('content-length'))->toBe('42');
        });

        it('returns default for missing header', function () {
            $request = ArrayRequest::createGet();

            expect($request->header('Missing', 'default'))->toBe('default');
        });
    });

    describe('environment', function () {
        it('returns env value', function () {
            $request = ArrayRequest::createGet()->setEnv('APP_ENV', 'testing');

            expect($request->env('APP_ENV'))->toBe('testing');
        });

        it('falls back to server value', function () {
            $request = ArrayRequest::createGet()->setServer(['APP_DEBUG' => 'true']);

            expect($request->env('APP_DEBUG'))->toBe('true');
        });

        it('returns default for missing env', function () {
            $request = ArrayRequest::createGet();

            expect($request->env('MISSING', 'default'))->toBe('default');
        });
    });

    describe('IP address', function () {
        it('returns remote address', function () {
            $request = ArrayRequest::createGet()->setIp('192.168.1.1');

            expect($request->ip())->toBe('192.168.1.1');
        });

        it('ignores Client-IP entirely, even behind a private peer', function () {
            // Client-IP is client-supplied end to end (no proxy in a standard
            // chain overwrites it), so honoring it re-opens the spoofing hole
            // that rightmost-untrusted XFF parsing closed.
            $request = ArrayRequest::createGet()->setServer([
                'HTTP_CLIENT_IP' => '6.6.6.6',
                'REMOTE_ADDR' => '127.0.0.1',
            ]);

            expect($request->ip())->toBe('127.0.0.1');
        });

        it('uses X-Forwarded-For when Client-IP is also present', function () {
            $request = ArrayRequest::createGet()->setServer([
                'HTTP_CLIENT_IP' => '6.6.6.6',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.7',
                'REMOTE_ADDR' => '127.0.0.1',
            ]);

            expect($request->ip())->toBe('203.0.113.7');
        });

        it('takes the rightmost public entry from X-Forwarded-For', function () {
            // The rightmost entry was appended by our own proxy and is the
            // only one the client cannot forge; 203.0.113.1 is client-supplied.
            $request = ArrayRequest::createGet()->setServer([
                'HTTP_X_FORWARDED_FOR' => '203.0.113.1, 70.41.3.18',
                'REMOTE_ADDR' => '127.0.0.1',
            ]);

            expect($request->ip())->toBe('70.41.3.18');
        });

        it('is not fooled by a client pre-seeding its own X-Forwarded-For header', function () {
            // Client sent "X-Forwarded-For: 6.6.6.6"; the proxy appended the
            // real client address. First-entry parsing returned the forgery.
            $request = ArrayRequest::createGet()->setServer([
                'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 203.0.113.7',
                'REMOTE_ADDR' => '127.0.0.1',
            ]);

            expect($request->ip())->toBe('203.0.113.7');
        });

        it('walks past internal proxy hops to the rightmost public entry', function () {
            $request = ArrayRequest::createGet()->setServer([
                'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 203.0.113.7, 10.0.0.5, 10.0.0.6',
                'REMOTE_ADDR' => '127.0.0.1',
            ]);

            expect($request->ip())->toBe('203.0.113.7');
        });

        it('skips invalid entries while walking right to left', function () {
            $request = ArrayRequest::createGet()->setServer([
                'HTTP_X_FORWARDED_FOR' => 'garbage, 203.0.113.7, not-an-ip',
                'REMOTE_ADDR' => '127.0.0.1',
            ]);

            expect($request->ip())->toBe('203.0.113.7');
        });

        it('falls back to the leftmost valid entry when every entry is private', function () {
            $request = ArrayRequest::createGet()->setServer([
                'HTTP_X_FORWARDED_FOR' => 'garbage, 10.0.0.9, 192.168.1.3',
                'REMOTE_ADDR' => '127.0.0.1',
            ]);

            expect($request->ip())->toBe('10.0.0.9');
        });

        it('falls back to the remote address when no entry is a valid IP', function () {
            $request = ArrayRequest::createGet()->setServer([
                'HTTP_X_FORWARDED_FOR' => 'garbage1, garbage2',
                'REMOTE_ADDR' => '127.0.0.1',
            ]);

            expect($request->ip())->toBe('127.0.0.1');
        });

        it('ignores spoofed forwarding headers from a direct public client', function () {
            $request = ArrayRequest::createGet()->setServer([
                'HTTP_CLIENT_IP' => '1.2.3.4',
                'HTTP_X_FORWARDED_FOR' => '5.6.7.8',
                'REMOTE_ADDR' => '198.51.100.9',
            ]);

            // peer is a public address, so the client-supplied headers are untrusted
            expect($request->ip())->toBe('198.51.100.9');
        });

        it('resolves the same client IP as NativeRequest for identical server input', function () {
            $cases = [
                ['HTTP_X_FORWARDED_FOR' => '6.6.6.6, 203.0.113.7', 'REMOTE_ADDR' => '127.0.0.1'],
                ['HTTP_X_FORWARDED_FOR' => '203.0.113.1, 70.41.3.18', 'REMOTE_ADDR' => '10.0.0.1'],
                ['HTTP_X_FORWARDED_FOR' => 'garbage, 10.0.0.9', 'REMOTE_ADDR' => '127.0.0.1'],
                ['HTTP_X_FORWARDED_FOR' => '5.6.7.8', 'REMOTE_ADDR' => '198.51.100.9'],
                ['HTTP_CLIENT_IP' => '6.6.6.6', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7', 'REMOTE_ADDR' => '127.0.0.1'],
                ['HTTP_CLIENT_IP' => '6.6.6.6', 'REMOTE_ADDR' => '127.0.0.1'],
            ];

            $backup = $_SERVER;

            try {
                foreach ($cases as $server) {
                    unset($_SERVER['HTTP_CLIENT_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);
                    foreach ($server as $key => $value) {
                        $_SERVER[$key] = $value;
                    }

                    $native = (new NativeRequest)->ip();
                    $array = ArrayRequest::createGet()->setServer($server)->ip();

                    expect($native)->toBe($array);
                }
            } finally {
                $_SERVER = $backup;
            }
        });
    });

    describe('secure detection', function () {
        it('detects HTTPS', function () {
            $request = ArrayRequest::createGet()->asSecure();

            expect($request->isSecure())->toBeTrue();
        });

        it('detects non-secure', function () {
            $request = ArrayRequest::createGet();

            expect($request->isSecure())->toBeFalse();
        });

        it('detects secure via port 443', function () {
            $request = ArrayRequest::createGet()->setServer(['SERVER_PORT' => '443']);

            expect($request->isSecure())->toBeTrue();
        });

        it('detects secure via forwarded proto', function () {
            $request = ArrayRequest::createGet()->setServer(['HTTP_X_FORWARDED_PROTO' => 'https']);

            expect($request->isSecure())->toBeTrue();
        });
    });

    describe('utility methods', function () {
        it('returns request URI', function () {
            $request = ArrayRequest::createGet()->setUri('/users?page=2');

            expect($request->uri())->toBe('/users?page=2');
        });

        it('clears request data', function () {
            $request = ArrayRequest::createPost(['email' => 'test@example.com'], ['page' => '1']);

            $request->clear();

            expect($request->postAll())->toBe([]);
            expect($request->queryAll())->toBe([]);
        });
    });

    describe('fluent setters', function () {
        it('chains setters', function () {
            $request = ArrayRequest::createGet()
                ->setPost(['email' => 'test@example.com'])
                ->setMethod('POST')
                ->withCookie('session', 'abc')
                ->setUri('/submit')
                ->asAjax();

            expect($request->method())->toBe('POST');
            expect($request->post('email'))->toBe('test@example.com');
            expect($request->cookie('session'))->toBe('abc');
            expect($request->uri())->toBe('/submit');
            expect($request->isAjax())->toBeTrue();
        });

        it('adds single POST value', function () {
            $request = ArrayRequest::createPost(['a' => '1'])
                ->addPost('b', '2');

            expect($request->post('a'))->toBe('1');
            expect($request->post('b'))->toBe('2');
        });

        it('adds single query value', function () {
            $request = ArrayRequest::createGet(['a' => '1'])
                ->addQuery('b', '2');

            expect($request->query('a'))->toBe('1');
            expect($request->query('b'))->toBe('2');
        });

        it('sets file with defaults', function () {
            $request = ArrayRequest::createPost([])
                ->setFile('avatar', ['name' => 'photo.jpg']);

            $file = $request->file('avatar');

            expect($file['name'])->toBe('photo.jpg');
            expect($file['error'])->toBe(UPLOAD_ERR_OK);
            expect($file['tmp_name'])->toStartWith('/tmp/');
        });
    });

    describe('proxy trust', function () {
        it('ignores X-Forwarded-Proto from a public peer by default', function () {
            $request = new ArrayRequest(['server' => [
                'REMOTE_ADDR' => '203.0.113.7',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ]]);

            expect($request->isSecure())->toBeFalse();
        });

        it('honors X-Forwarded-Proto from a private peer by default', function () {
            $request = new ArrayRequest(['server' => [
                'REMOTE_ADDR' => '10.0.0.5',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ]]);

            expect($request->isSecure())->toBeTrue();
        });

        it('ignores X-Forwarded-Proto even from a private peer in strict mode', function () {
            $request = new ArrayRequest([
                'server' => [
                    'REMOTE_ADDR' => '10.0.0.5',
                    'HTTP_X_FORWARDED_PROTO' => 'https',
                ],
                'trustedProxies' => [],
            ]);

            expect($request->isSecure())->toBeFalse();
        });

        it('honors X-Forwarded-Proto from a listed public peer', function () {
            $request = new ArrayRequest([
                'server' => [
                    'REMOTE_ADDR' => '203.0.113.7',
                    'HTTP_X_FORWARDED_PROTO' => 'https',
                ],
                'trustedProxies' => ['203.0.113.0/24'],
            ]);

            expect($request->isSecure())->toBeTrue();
        });

        it('ignores X-Forwarded-For even from a private peer in strict mode', function () {
            $request = new ArrayRequest([
                'server' => [
                    'REMOTE_ADDR' => '10.0.0.5',
                    'HTTP_X_FORWARDED_FOR' => '198.51.100.9',
                ],
                'trustedProxies' => [],
            ]);

            expect($request->ip())->toBe('10.0.0.5');
        });

        it('honors X-Forwarded-For from a listed public peer', function () {
            $request = new ArrayRequest([
                'server' => [
                    'REMOTE_ADDR' => '203.0.113.7',
                    'HTTP_X_FORWARDED_FOR' => '198.51.100.9',
                ],
                'trustedProxies' => ['203.0.113.7'],
            ]);

            expect($request->ip())->toBe('198.51.100.9');
        });
    });
});
