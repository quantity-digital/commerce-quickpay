# Release Notes for QuickPay for Craft Commerce

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 5.0.0-alpha.1 - 2025-02-24

### Added

- Handling of store is on auto capture status change

## 5.0.0-alpha - 2024-12-04

### Added

- Added support for CraftCMS 5

## 4.2.2 - 2024-08-22

### Fixed

- Fixed incorrect import, making case sensitive confirgurations fail on auto capture

## 4.2.1 - 2024-03-18

### Fixed

- Fixed an issue where transactions would be ralated to the wrong parent transaction

## 4.2.0 - 2024-02-20

### Fixed

- Updated capture functionallity to make use of outstanding balance instead of PaymentAmount

### Added

- Added 'EVENT_BEFORE_PAYMENT_CAPTURE_AMOUNT' to modify the amount being captured

## 4.1.9 - 2024-01-22

### Added

- Callback functionallity on Capture events

## 4.1.8 - 2023-11-14

### Added

- Enabled the following gateways: Anyday split, Google pay, Apple pay

## 4.1.7 - 2023-10-30

### Added

- Added events to modify quickpay basket

## 4.1.6 - 2023-10-18

### Fixed

- Updated to use crafts native payment amounts

## 4.1.5 - 2023-10-18

### Fixed

- Updated to use crafts native payment amounts

## 4.1.4 - 2023-08-29

### Fixed

- Fixed bug when running autocapture job

## 4.1.3 - 2023-08-29

### Fixed

- Fixed bug when capturing payment

## 4.1.2 - 2023-08-29 [CRITICAL]

### Fixed

- Fixed critical error, where orders could be marked as complete, before the payment was made & completed in quickpay

## 4.1.1 - 2023-08-25

### Added

- Added new gateway setting, to define if amounts sent to quickpay should be converted into paymentcurrency. Is default set to `false`

## 4.1.1 - 2023-08-25

### Added

- Added new gateway setting, to define if amounts sent to quickpay should be converted into paymentcurrency. Is default set to `false`

## 4.1.0 - 2023-06-23

### Added

- Addded support for `Klarna Payments`

### Changed

- Following methods has been disable for specific selection, as gateway currently doesn't support them: PayPal, Sofort, Resurs Bank, Klarna, Swish
- Payment requests now contain lineitems, adjustments and shipping costs
- Improved error response from quickpay to the frontend. Closes [#4 - Gateway not redirecting](https://github.com/quantity-digital/commerce-quickpay/issues/4)

## 4.0.3 - 2023-05-25

### Change

- Added property `orderId` to the `CapturePayment` queue. `CapturePayment` will now get a fresh copy of the order from the database.

### Fixed

- Fixed `CapturePayment` e-mail sending fails when using ENV variable

### Depricated

`transaction` in `CapturePayment` is depricated, and is replaced by orderId instead. This is to prevent raceconditions on order.

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
