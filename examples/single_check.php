<?php
/**
 * Example: verify one address.
 *   composer require easyemailverification/php-sdk
 *   EEV_API_KEY=your_key php examples/single_check.php someone@example.com
 * Without EEV_API_KEY it uses the public sandbox key (try valid@sandbox.easyemailverification.com).
 */

require __DIR__ . '/../vendor/autoload.php';

use EasyEmailVerification\Client;
use EasyEmailVerification\EEVException;

$email = $argv[1] ?? 'valid@sandbox.easyemailverification.com';
$eev = new Client(getenv('EEV_API_KEY') ?: Client::SANDBOX_API_KEY);

try {
    $r = $eev->verify($email);
} catch (EEVException $e) {
    fwrite(STDERR, 'Error ' . $e->getStatus() . ': ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Email:        {$r['email']}\n";
echo "Result:       {$r['result']} ({$r['reason']})\n";
echo 'Safe to send: ' . ($r['safe_to_send'] ? 'yes' : 'no') . "\n";
echo 'Disposable:   ' . ($r['disposable'] ? 'yes' : 'no') . ', catch-all: ' . ($r['accept_all'] ? 'yes' : 'no') . ', role: ' . ($r['role'] ? 'yes' : 'no') . "\n";
if ($r['did_you_mean'] !== '') {
    echo "Did you mean: {$r['did_you_mean']}\n";
}
echo 'Decision:     ' . Client::decide($r) . "\n";
