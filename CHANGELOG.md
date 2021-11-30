# Release Notes for QuickPay for Craft Commerce

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 2.2.25 - 2021-11-30

### Fixed

* Fixed bug in `SubscriptionsCronController` that could prevent subscription in beeing reneved

## 2.2.24 - 2021-11-05

### Added

* Added `EVENT_AFTER_CAPTURE_TRANSACTION` to `Subscriptions` service.

## 2.2.23- 2021-10-18

* Fixed the error `SQLSTATE[21000]: Cardinality violation: 1241 Operand should contain 1 column(s)` when searching orders. Fixed by moving `OrderQueryBehavior` event to `ElementQuery::EVENT_BEFORE_PREPARE`

## 2.2.21 - 2021-10-06

### Fixed

* Fixed bug where orderstatus for subscriptions was saved with `id` instead of `handle`. Remember to resave your settings if using subscriptions to apply the new handle.

## 2.2.20 - 2021-10-01

### Changed

* Moved when status is set on a subscription order to enabled order history.

## 2.2.19 - 2021-08-15

### Fixed

* Fixed issue where refund didn't work from backend

## 2.2.18 - 2021-08-11

### Fixed

* Fixed issue where orders recalculationMode was stored as `none` instead of all.

## 2.2.17 - 2021-08-09

### Fixed

* Fixed bug in translation

## 2.2.16 - 2021-08-02

### Fixed

* Fixed error where decimals was beeing removed

### Changed

* Cancel_url now returns to a callback function that set recalculationmode to ALL, and from there redirect to the order cancel_url.

## 2.2.14 - 2021-06-07

### Fixed

* Fixed missing schema change

## 2.2.13 - 2021-06-07

### Changed

* Subscriptions now both have a billing periode, and a subscription periode. This makes it possible to have monthly payments, with a subscription periode for several months.

## 2.2.11 - 2021-03-11

### Fixed

* Fixed error in OrderBehaviour that prevent queue jobs executed by console to run

## 2.2.10 - 2021-19-01

### Fixed

* Fixed error in subscription cron controller preventing recurring subscriptions in being created

## 2.2.9 - 2020-10-27

### Changed

* Removed daily and weekly subscriptions lengths

### Fixed

* Fixed error where lineitem didn't take subscription length into consideration when calculating pric

## 2.2.8 - 2020-10-26

### Added

* Added `couponCode` to the payment order

## 2.2.7 - 2020-10-26

### Fixed

* Fixed error in `QD\commerce\quickpay\elements\Plan` where `afterOrderComplete` wasnt passing the cancelable event to the trigger

## 2.2.6 - 2020-10-23

### Fixed

* Fixed Carbon issue in `calculateFirstPaymentDate` function

## 2.2.5 - 2020-10-22

### Fixed

* Fixed `QD\commerce\quickpay\elements\db\SubscriptionQuery` parsing `cardExpireYear` and `cardExpireMonth` as date instead of string

## 2.2.4 - 2020-10-22

### Added

* Added `cardExpireYear` and `cardExpireMonth` to `QD\commerce\quickpay\elements\db\SubscriptionQuery`

## 2.2.3 - 2020-10-22

### Fixed

* Fixed hardcoded subscription id

## 2.2.2 - 2020-10-19

### Fixed

* Fixed where unexpected code occured

## 2.2.1 - 2020-10-19

### Fixed

* Fixed error where url + orderId was wrong in recurring payment

## 2.2.0 - 2020-10-19

### Added

* Subscriptions now uses orders to handle payments

## 2.1.5 - 2020-10-19

### Added

* Added reactivation function for subscriptions

### Fixed

* Fixed error in capture function, where dateStarted wasn't DateTime object

## 2.1.4 - 2020-10-19

### Added

* Subscriptions now stores the quickpay reference
* Notify callback now updates subscriptions with carddata, if a subscription exists that matches the transaction orderId

## 2.1.3 - 2020-10-18

### Added

* Subscriptions now stores carddata on the subscription element
* Subscriptions now has a dateStarted field, which all payment date is calculated from. Gives the ability to start / pause a subscription

## 2.1.2 - 2020-10-15

### Fixed

* Fixed wrong implementation of event trigger

## 2.1.1 - 2020-10-15

### Added

* Added ``EVENT_BEFORE_SUBSCRIPTION_CREATE` to

## 2.1.0 - 2020-10-13

### Added

* Added Plans field to be able to select plans

## 2.0.3 - 2020-10-12

### Fixed

* Fixed error where plans wasn't saved to each site individually

## 2.0.2 - 2020-10-12

### Fixed

* Minor optmizations

## 2.0.1 - 2020-10-12

### Fixed

* Fixed error in migration `install.php` that prevented installation

## 2.0.0 - 2020-10-12

### Added

* Added install migration, to remove project config on uninstall
* Added Subscriptioj gateway, to support subscriptions
* Added new Purchasable `plan` to be able to add subscriptions to cart

### Fixed

* Fixed error, where the `order reference` wasn't generated when creating payment request

### Changed

* Moved plugin trait into new namespace `QD\commerce\quickpay\base`
* Moved gateway trait into new namespace `QD\commerce\quickpay\base`

## 1.1.2 - 2020-09-13

### Fixed

- Fixed `PaymentRequestModel` using `shortNumber` instead of `reference`

## 1.1.1 - 2020-09-02

- Added .gitattributes to optimize size

## 1.1.0 - 2020-09-02

### Added

- Added new setting, `paymentMethods`, which give the possibility to limit gateway to specific payment methods

### Changed

- OrderID sent to Quickpay now get appended `-xx` if there has been a previous payment request to Quickpay. This is needed because Quickpay requires all request to have a unique OrderID.

### Fixed

- Fixed CallbackController function `actionContinue` to use the `returnUrl` that is set on the `Order element`
- Fixed OrderID sent to quickpay was timestamp used in development

## 1.0.0 - 2020-09-01

Initial release of the Quickpay gateway plugin to the Craft Store
