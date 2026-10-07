<?php

namespace EasyEmailVerification\Tests;

use EasyEmailVerification\Client;
use EasyEmailVerification\EEVException;
use PHPUnit\Framework\TestCase;

/** Bulk endpoints against a local fake API (tests/fake-api.php; the sandbox key does not work on bulk). */
final class BulkTest extends TestCase
{
    private static $proc;
    private static string $base;
    private static Client $eev;

    public static function setUpBeforeClass(): void
    {
        $port = random_int(20000, 40000);
        $id = bin2hex(random_bytes(4));
        self::$proc = proc_open(['php', '-S', "127.0.0.1:$port", __DIR__ . '/fake-api.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, ['EEV_FAKE_ID' => $id] + getenv());
        self::$base = "http://127.0.0.1:$port";
        for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) usleep(100000);
        self::$eev = new Client('live_key', self::$base);
    }

    public static function tearDownAfterClass(): void
    {
        proc_terminate(self::$proc);
    }

    private function state(): array
    {
        return json_decode(file_get_contents(self::$base . '/state', false, stream_context_create(['http' => ['header' => 'X-API-Key: live_key']])), true);
    }

    public function testUploadFromPath(): void
    {
        $file = sys_get_temp_dir() . '/leads.csv';
        file_put_contents($file, "a@example.com\nb@example.com\n");
        $r = self::$eev->bulk->upload($file);
        $this->assertSame('abc123', $r['list_id']);
        $up = $this->state()['upload'];
        $this->assertSame('leads.csv', $up['name']);
        $this->assertStringContainsString('b@example.com', $up['content']);
    }

    public function testUploadFromContent(): void
    {
        $this->assertSame('accepted', self::$eev->bulk->upload("a@example.com\n", 'x.csv', true)['status']);
        $this->assertSame('x.csv', $this->state()['upload']['name']);
    }

    public function testWaitListDownloadDelete(): void
    {
        $this->assertSame('completed', self::$eev->bulk->wait('abc123', 0.01)['status']);
        $this->assertSame(1, self::$eev->bulk->list()['total_lists']);
        $this->assertStringStartsWith('Email,Result', self::$eev->bulk->download('abc123'));
        $this->assertSame('deleted', self::$eev->bulk->delete('abc123')['status']);
    }

    public function testErrors(): void
    {
        try {
            self::$eev->bulk->status('nope');
            $this->fail('expected 404');
        } catch (EEVException $e) {
            $this->assertSame([404, 'Not found'], [$e->getStatus(), $e->getMessage()]);
        }
    }
}
