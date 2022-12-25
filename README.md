# TYPO3 Extension / Utility xliff

## 1 Features

* Migrates XLIFF files from version 1.0 to 1.2
* Generates XLIFF files in defined languages
* Generates XLIFF files in defined languages with automatic translation via Deepl/Google
* Export XLIFF files in csv or xlsx files
* Export XLIFF files in your custom export schema via event

## 2 Usage

### 2.1 Installation

#### Installation using Composer

The recommended way to install the extension is using Composer.

Run the following command within your [Composer][1] based TYPO3 project:

```
composer require --dev ayacoo/xliff
```

Do not forget to activate the extension in the extension manager and define the deepl api settings if necessary/desired.
And also: Since we use a cache for deepl, a DB Compare is also necessary.

Attention: This extension should only be active in development mode!

### 2.2 CLI Commands

#### Basics

This utility searches for all extension xlf files in the Resources/Private/Language folder. Afterwards the XLIFF
header is rebuilt and the trans-unit elements are used from the original file.

#### Migrate XLIFF 1.0 to 1.2

```
vendor/bin/typo3cms xliff:migrate --extension=EXTENSION_NAME --overwrite=(1|0) --empty=(1|0) --path=SUBFOLDER --file==FILENAME
```

All XLIFF files will be migrated from version 1.0 to 1.2. If you want, you can also disable the overwriting of the file
and a copy of the original file will be created.

The ```empty``` attribute can also be used to migrate empty XLIFF files.

If you want to customize a single file, use the ```file``` attribute. If this file is in a subfolder, add the ```path```
attribute.

#### Generate and/or translate XLIFF files for defined languages

With this command, a translated variant of locallang.xlf, for example, is to be created from an extension. For this
purpose you can specify a comma-separated list of isocodes in the ```languages``` argument. In course of this, a XLIFF
in version 1.2 is also directly created.

Then target elements are automatically added with the text from the source of the original. CDATA is also taken into
account. All xlf files that do not have a target-language attribute are migrated.

If you use the parameter translate with ```true```, you can also have these texts automatically translated from the
original. For this [deepl][3] or [Google Translate][5] is used. For this an API account incl. key must exist.

It is unclear here if there are any limitations on the part of the API of deepl. In case of doubt it is maybe better to
translate file by file.

```
vendor/bin/typo3cms xliff:generate --extension=EXTENSION_NAME --languages=ISOCODES --translate=(0|1)
```

#### Export xliff file

Translation agencies sometimes require a different file format. For this reason there is also a CSV export.
You can export any xlf file or you can control exactly one file with the parameters `file` and `path`.

```
vendor/bin/typo3cms xliff:export --extension=EXTENSION_NAME
vendor/bin/typo3cms xliff:export --extension=EXTENSION_NAME --file=FILENAME --path=PATH
```

If you need to export to Excel format, this is how to do it:

```
vendor/bin/typo3cms xliff:export --extension=EXTENSION_NAME --format=xlsx
```

By default, an export file is created from all xlf files at the end. If you want to have one generated file per xlf file
you can use the parameter `singleFileExport`.

```
vendor/bin/typo3cms xliff:export --extension=EXTENSION_NAME --singleFileExport=(0|1)
```

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

[5]: https://cloud.google.com/translate/docs/reference/rest/

## 6 Support

If you are happy with the extension and would like to support it in any way, I would appreciate the support of social institutions.
