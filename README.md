# TYPO3 Extension / Utility xliff

## 1 Features

* Migrates XLIFF files from version 1.0 to 1.2
* Generates XLIFF files in defined languages
* Generates XLIFF files in defined languages with automatic translation via Deepl

## 2 Usage

### 2.1 Installation

#### Installation using Composer

The recommended way to install the extension is using Composer.

Run the following command within your [Composer][1] based TYPO3 project:

```
composer require --dev ayacoo/xliff
```

Do not forget to activate the extension in the extension manager and define the deepl api settings if necessary/desired.

Attention: This extension should only be active in development mode!

### 2.2 CLI Commands

#### Basics

This utility searches for XLF files in the specified extension in Resources/Private/Language. Afterwards the XLIFF
header is rebuilt and the trans-unit elements are used from the original file.

#### Migrate XLIFF 1.0 to 1.2

```
vendor/bin/typo3cms xliff:migrate --extension=EXTENSION_NAME --overwrite=(1|0) --empty=(1|0)
```

All XLIFF files will be migrated from version 1.0 to 1.2. If you want, you can also disable the overwriting of the file
and a copy of the original file will be created.

The empty attribute can also be used to migrate empty XLIFF files.

#### Generate and/or translate XLIFF files for defined languages

With this command, a translated variant of locallang.xlf, for example, is to be created from an extension. For this
purpose you can specify a comma-separated list of isocodes in the ```languages``` argument. In course of this, a XLIFF
in version 1.2 is also directly created.

Then target elements are automatically added with the text from the source of the original. CDATA is also taken into
account.

If you use the parameter translate with ```true```, you can also have these texts automatically translated from the
original. For this [deepl][3] is used. For this an API account incl. key must exist.

It is unclear here if there are any limitations on the part of the API of deepl. In case of doubt it is better to
translate file by file.

```
vendor/bin/typo3cms xliff:generate --extension=EXTENSION_NAME --languages=ISOCODES --translate=(0|1)
```

#### Export locallang.xlf into CSV format

Translation agencies sometimes require a different file format. For this reason there is also a CSV export.

```
vendor/bin/typo3cms xliff:export --extension=EXTENSION_NAME --file=FILENAME
```

## 2.3 Current/known problems

- Files with a comment ``<!-- comment -->`` are not yet processed cleanly
- XLF files that have only one trans-unit element are not yet processed

## 3 Documentation

https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Internationalization/Index.html

## 4 Thanks

The development of the extension was supported by the TYPO3 agency [brandung][4].

The DeeplService of the extension [wv_translate][2] was used and slightly adapted.

And of course thanks deepl for their outstanding service

[1]: https://getcomposer.org/

[2]: https://github.com/web-vision/wv_deepltranslate/blob/master/Classes/Service/DeeplService.php

[3]: https://www.deepl.com/de/docs-api/translating-text/example/

[4]: https://www.agentur-brandung.de/
