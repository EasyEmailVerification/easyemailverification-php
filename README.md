# Easy Email Verification for PHP

Official PHP client for the [Easy Email Verification API](https://www.easyemailverification.com/en-US/api): check whether email addresses exist and are safe to send to, one by one, in batches of 50 or as bulk lists of up to 16 MB. No email is sent to the addresses you verify.

- PHP 8.1+, only the `curl` and `json` extensions
- Free sandbox key to test without an account or credits

## Install

```bash
composer require easyemailverification/php-sdk
```

## Quick start

```php
use EasyEmailVerification\Client;

// Use Client::SANDBOX_API_KEY to try it, or your own key (by default read from EEV_API_KEY)
$eev = new Client(Client::SANDBOX_API_KEY);

$r = $eev->verify('valid@sandbox.easyemailverification.com');
echo $r['result'], ' ', $r['reason'], "\n";   // valid accepted_email
echo Client::decide($r), "\n";                  // accept
```

Get your API key in the dashboard under [API settings](https://dashboard.easyemailverification.com/apisettings) and keep it on the server, in the `EEV_API_KEY` environment variable. Never put it in browser code; for web forms use the [email verification widget](https://www.easyemailverification.com/en-US/email-verification-widget).

## Results

Every verification returns `result` (`valid`, `invalid` or `unknown`), a `reason` and risk signals:

| Field | Meaning |
| --- | --- |
| `result` | `valid`: the mail server accepted the mailbox. `invalid`: it will bounce. `unknown`: no reliable answer (not invalid). |
| `reason` | Why, for example `accepted_email`, `rejected_email`, `invalid_domain`, `no_mx_record`, `timeout`. |
| `safe_to_send` | Overall recommendation. `false` for invalid and unknown results, catch-all domains and disposable addresses. |
| `did_you_mean` | Corrected address when a typo is detected (`gmial.com` → `gmail.com`), otherwise `''`. |
| `disposable`, `accept_all`, `role`, `free` | Risk signals: temporary inbox, catch-all domain, role address (info@), free provider. |

`Client::decide($r)` turns a result into `'accept'`, `'reject'`, `'suggest'` (show `did_you_mean`) or `'review'` (unknown, catch-all or disposable: your policy decides). Every result code is explained at [easyemailverification.com/en-US/help/result-codes](https://www.easyemailverification.com/en-US/help/result-codes).

## Batch: up to 50 addresses

```php
foreach ($eev->verifyBatch(['anna@example.com', 'mark@gmial.com']) as $r) {
    echo $r['email'], ' ', Client::decide($r), "\n";
}
```

## Bulk lists

For files with thousands of addresses (TXT or CSV, one address per line, up to 16 MB). The account needs enough credits for every address. Bulk does not work with the sandbox key.

```php
$job  = $eev->bulk->upload('leads.csv');           // a path; or upload($csvText, 'leads.csv', true)
$done = $eev->bulk->wait($job['list_id']);         // polls until completed (or failed)
if ($done['status'] === 'completed') {
    $csv = $eev->bulk->download($job['list_id']);  // Email,Result,Reason,...,IsSafeToSend,DidYouMean,...
}
$eev->bulk->list();                                // all jobs of the account
$eev->bulk->delete($job['list_id']);               // results are also deleted after 30 days
```

## Credits

```php
echo $eev->credits()['credits_remaining'];  // free call
```

Each verified address uses one credit; unknown results do not. The free plan includes 50 verifications a day. See [pricing](https://www.easyemailverification.com/en-US/pricing).

## Errors

API errors throw `EasyEmailVerification\EEVException` with the HTTP status and the API message:

```php
use EasyEmailVerification\EEVException;

try {
    $eev->verify('someone@example.com');
} catch (EEVException $e) {
    if ($e->getStatus() === 402) {
        // no credits left
    }
}
```

| Status | Meaning |
| --- | --- |
| 400 | Missing key or parameter, or a non-sandbox address with the sandbox key |
| 401 | Unknown key, or a widget-only key |
| 402 | No credits left |
| 404 | Bulk job not found |
| 429 | Sandbox rate limit |
| 0 | Raised by the client (network error, timeout, more than 50 addresses in a batch) |

Do not retry verifications in a tight loop: a request that timed out may already have used a credit.

## Upgrading from the first version

`EasyEmailVerificationClient` still works as before (`verifyEmail()` returns an array and does not throw), now on top of the new client. New code should use `EasyEmailVerification\Client`.

## Sandbox addresses

With `Client::SANDBOX_API_KEY`, these addresses return fixed answers: `valid@`, `invalid@`, `unknown@`, `disposable@`, `catchall@`, `role@`, `quota@` (402) and `ratelimit@` (429) at `sandbox.easyemailverification.com`, plus `typo@gmial.com` (did you mean). Sandbox calls are limited to 60 per minute per IP.

## Links

- [Email verification API](https://www.easyemailverification.com/en-US/api) and [API reference](https://www.easyemailverification.com/en-US/api/reference)
- [Validate email addresses in PHP](https://www.easyemailverification.com/en-US/guides/validate-email-php): filter_var and a mailbox check, without sending an email
- [Mautic plugin](https://www.easyemailverification.com/en-US/guides/mautic)
- Also distributed on [SourceForge](https://sourceforge.net/projects/email-checker-php-sdk/)
- Support: support@easyemailverification.com

## License

MIT
