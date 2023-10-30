# Changelog

All notable changes to this project will be documented in this file. See [standard-version](https://github.com/conventional-changelog/standard-version) for commit guidelines.

## 1.1.0 (2023-10-30)


### Features

* Added events to modify quickpay basket ([4ef5e69](https://github.com/quantity-digital/commerce-quickpay/commit/4ef5e690f856865008dcc614c8755b6877d2fa8f))
* Added new field ([acfa2c5](https://github.com/quantity-digital/commerce-quickpay/commit/acfa2c514265ac7e5c712f99cb60238badb50b3d))
* Added settings option to limit payment methods ([d3097ea](https://github.com/quantity-digital/commerce-quickpay/commit/d3097ea0a1ed7dc4c9eb64c53e5c90784b7962a1))
* New setting that makes it possible to enable 3D-secure on eligible payment methods ([0afee12](https://github.com/quantity-digital/commerce-quickpay/commit/0afee12acd0c15a9c0ba7c2e3c4d0198faabdc5f))


### Bug Fixes

* Added Google analytics ID and branding ID to settingspage ([ad700d3](https://github.com/quantity-digital/commerce-quickpay/commit/ad700d3b08cd985eff3f6d114568a0e9d4e30782))
* added shipping total events ([a98d57b](https://github.com/quantity-digital/commerce-quickpay/commit/a98d57bb9490ca7700cf40f7bddc415af62a760d))
* Error code using non existing id ([#16](https://github.com/quantity-digital/commerce-quickpay/issues/16)) ([0c155a3](https://github.com/quantity-digital/commerce-quickpay/commit/0c155a377b91e89a0e12f95a93cccb9b59429c71))
* Fixed callbackcontroller continue function to use order returnUrl ([12ef147](https://github.com/quantity-digital/commerce-quickpay/commit/12ef14713ed580476849416daecccae5ab9c30a2))
* Fixed error in migration install.php ([05ae6e9](https://github.com/quantity-digital/commerce-quickpay/commit/05ae6e98a85646dbda94aadfc5e466e23421ad2a))
* Fixed error in Plan cancelable event ([95466fb](https://github.com/quantity-digital/commerce-quickpay/commit/95466fb64342dca6c536af65a76492fe18e761c0))
* Fixed error where plans wasnt saving correctly ([4371711](https://github.com/quantity-digital/commerce-quickpay/commit/437171179adfdb38c245ae82ed067642709d73e7))
* Fixed wrong url + orderId ([48db38e](https://github.com/quantity-digital/commerce-quickpay/commit/48db38e921c4224f29018f5c9ea76de4d27eb079))
* Payment order was missing the couponCode ([416a248](https://github.com/quantity-digital/commerce-quickpay/commit/416a24816fcac80b658dba14d37d66f334fa57be))
* Removed gatewayId from subscriptions ([6bb305d](https://github.com/quantity-digital/commerce-quickpay/commit/6bb305d8f9989c72dc614577d4bf1dbe22635beb))
* Updated MIT License ([361c626](https://github.com/quantity-digital/commerce-quickpay/commit/361c6267ecc40c46567ec9d17296dd2d8890d784))
* Updated payment amount to use native transaction values ([016711d](https://github.com/quantity-digital/commerce-quickpay/commit/016711d6c81065dfde1b71f3f034b22240237107))
* Updated payments to handle klarna baskets ([8effcfb](https://github.com/quantity-digital/commerce-quickpay/commit/8effcfb08c07d921ef1ef0c753a9c615e6962903))
* updated to accomidate PR ([8dfd94c](https://github.com/quantity-digital/commerce-quickpay/commit/8dfd94cef8325dbd469c0e05c3620e0b56d88577))

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
