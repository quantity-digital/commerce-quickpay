# Release Notes for QuickPay for Craft Commerce

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 4.0.1 - 2023-05-23

### Changed

- `CapturePayment` QueueJob now implements `yii\queue\RetryableJobInterface` to prevent possible infinite capture loop

### Added

- Added new Notification e-mail setting, making it possible to receive mail if a capture job fails

## 4.0.0 - 2022-20-09

### Changed

- Upgraded to Craft 4.0
- Upgraded to Commerce 4.0
- Upgraded to PHP 8.0
- Changed selectFields to selectizedField
- Changed lightswitchField to booleanMenu

### Fixed

- Small logic changes

### Added

- Added docblocks and typing
- Added TransactionBehavior, to centralize logic
- Notice to README about Quickpay permissions

### Deleted

- Removed everything related to subscriptions and plans
