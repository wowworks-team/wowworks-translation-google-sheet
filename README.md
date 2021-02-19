# translation-google-sheet-service

Integration with Google-Sheets API.

Installation
-------------

This extension is available at packagist.org and can be installed via composer by following command:

composer require wowworks/translation-google-sheet`

Configuration:

```php
$client = new Google_Client();
$client->setApplicationName('Google Sheets API Wowworks');
$client->setScopes(Google_Service_Sheets::SPREADSHEETS);
$client->setAccessType('offline');
$client->setPrompt('select_account consent');

$pathToCredentialFile = 'pathToCredentialFile';
putenv("GOOGLE_APPLICATION_CREDENTIALS={$pathToCredentialFile}");
$client->useApplicationDefaultCredentials();
$serviceGoogleSheets = new Google_Service_Sheets($client);
$configurationService = new TranslationGoogleSheetConfigurationService(
    ['en_EN', 'ru_RU', 'de_DE'],
    ['ru_RU']
);

$service =  new TranslationGoogleSheetService(
            ['spreadSheetUrl1', 'spreadSheetUrl2'],
            $serviceGoogleSheets,
            $configurationService,
            new TranslationStringConverter()
);
```

Usage:

```php
$allPathsToTranslations = $configurationService->getAllPathsToTranslations(Yii::$app->i18n->translations);
$service->pull($allPathsToTranslations);
$service->push($allPathsToTranslations);
```