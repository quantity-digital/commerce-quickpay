# Release Notes for QuickPay for Craft Commerce

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 2.2.3 - 2020-10-22

### Added

### Fixed

* Fixed SubscriptionCallbackController not reseting



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

