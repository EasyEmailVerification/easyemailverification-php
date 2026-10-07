# Changelog

## 2.0.0 (2026-10-07)

Rewrite as a Composer package (`easyemailverification/php-sdk`): `EasyEmailVerification\Client` with verify, verifyBatch (up to 50), credits, bulk upload/status/list/wait/download/delete, `decide()`, `EEVException`, `SANDBOX_API_KEY`. The API key is sent in the `X-API-Key` header. `EasyEmailVerificationClient` (1.0.0) is kept for backward compatibility.

## 1.0.0 (2025)

First version: `EasyEmailVerificationClient::verifyEmail()`.
