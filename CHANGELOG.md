# Yii Logging Library Change Log

## 2.0.1 under development

- Bug #84: Change the type of the `$level` parameter in the `Message` constructor to `string` (@dood-)
- New #104: Add new static methods `Logger::assertLevelIsValid()`, `Logger::assertLevelIsString()` and
  `Logger::assertLevelIsSupported()` (@vjik)
- Chg #104: Deprecate method `Logger::validateLevel()` (@vjik)
- Bug #98: Fix error on format trace, when it don't contain "file" and "line" items (@vjik)

## 2.0.0 May 22, 2022

- Chg #68: Raise the minimum `psr/log` version to `^2.0|^3.0` and the minimum PHP version to 8.0 (@xepozz, @rustamwin)

## 1.0.4 March 29, 2022

- Bug #76: Fix time formatter when locale uses comma as a decimal point separator (@terabytesoftw)

## 1.0.3 November 12, 2021

- Chg #74: Replace usage of `yiisoft/yii-web` to `yiisoft/yii-http` in event config (@devanych)

## 1.0.2 May 19, 2021

- Bug #67: Flush logger on the console is terminated (@rustamwin)

## 1.0.1 March 23, 2021

- Chg: Adjust config for new config plugin (@samdark)

## 1.0.0 February 11, 2021

Initial release.
