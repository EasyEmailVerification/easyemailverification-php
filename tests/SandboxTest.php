<?php

namespace EasyEmailVerification\Tests;

use EasyEmailVerification\Client;
use EasyEmailVerification\EEVException;
use PHPUnit\Framework\TestCase;

/** Live tests against the public sandbox (no account, no credits). */
final class SandboxTest extends TestCase
{
    private const S = 'sandbox.easyemailverification.com';
    private Client $eev;

    protected function setUp(): void
    {
        $this->eev = new Client(Client::SANDBOX_API_KEY);
    }

    public function testVerifyAnswersAndDecisions(): void
    {
        $cases = ['valid' => ['valid', 'accept'], 'invalid' => ['invalid', 'reject'], 'unknown' => ['unknown', 'review'],
                  'catchall' => ['valid', 'review'], 'disposable' => ['valid', 'review']];
        foreach ($cases as $local => [$result, $decision]) {
            $r = $this->eev->verify("$local@" . self::S);
            $this->assertSame($result, $r['result'], $local);
            $this->assertSame($decision, Client::decide($r), $local);
        }
        $typo = $this->eev->verify('typo@gmial.com');
        $this->assertSame('typo@gmail.com', $typo['did_you_mean']);
        $this->assertSame('suggest', Client::decide($typo));
    }

    public function testPlusIsKept(): void
    {
        $this->assertSame('valid+tag@' . self::S, $this->eev->verify('valid+tag@' . self::S)['email']);
    }

    public function testBatch(): void
    {
        $r = $this->eev->verifyBatch(['valid@' . self::S, 'typo@gmial.com']);
        $this->assertSame(['accept', 'suggest'], array_map([Client::class, 'decide'], $r));
    }

    public function testBatchLimit(): void
    {
        $this->expectException(EEVException::class);
        $this->eev->verifyBatch(array_fill(0, 51, 'valid@' . self::S));
    }

    public function testCredits(): void
    {
        $this->assertIsInt($this->eev->credits()['credits_remaining']);
    }

    public function testErrors(): void
    {
        try {
            $this->eev->verify('quota@' . self::S);
            $this->fail('expected 402');
        } catch (EEVException $e) {
            $this->assertSame(402, $e->getStatus());
        }
        try {
            (new Client('wrong-key'))->verify('valid@' . self::S);
            $this->fail('expected 401');
        } catch (EEVException $e) {
            $this->assertSame(401, $e->getStatus());
        }
    }

    public function testMissingKey(): void
    {
        $saved = getenv('EEV_API_KEY');
        putenv('EEV_API_KEY');
        try {
            $this->expectException(EEVException::class);
            new Client();
        } finally {
            if ($saved !== false) putenv('EEV_API_KEY=' . $saved);
        }
    }

    public function testLegacyClientKeepsItsBehavior(): void
    {
        $legacy = new \EasyEmailVerificationClient(Client::SANDBOX_API_KEY);
        $this->assertSame('valid', $legacy->verifyEmail('valid@' . self::S)['result']);
        $err = $legacy->verifyEmail('quota@' . self::S);
        $this->assertFalse($err['success']);
        $this->assertSame('402', $err['http_code']);
    }
}
