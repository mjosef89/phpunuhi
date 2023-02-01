<?php

namespace PHPUnuhi\Bundles\Exchange\HTML\Services;

use PHPUnuhi\Bundles\Exchange\ImportEntry;
use PHPUnuhi\Bundles\Exchange\ImportResult;
use PHPUnuhi\Services\GroupName\GroupNameService;
use PHPUnuhi\Traits\StringTrait;
use SplFileObject;

class HTMLImporter
{

    use StringTrait;

    /**
     * @param string $filename
     * @return ImportResult
     */
    public function import(string $filename): ImportResult
    {
        $foundData = [];

        $groupNameService = new GroupNameService();

        foreach (new SplFileObject($filename) as $line) {

            if ($line === false) {
                $line = '';
            }

            $line = str_replace(PHP_EOL, '', $line);

            if (is_array($line)) {
                $line = '';
            }

            if (trim($line) === '') {
                continue;
            }

            $fullKeyWithLocale = explode('=', $line)[0];

            $localeID = '';
            $group = $groupNameService->getGroupID($fullKeyWithLocale);
            $key = $groupNameService->getPropertyName($fullKeyWithLocale);

            if ($this->stringDoesContain($key, '--')) {
                $localeID = explode('--', $key)[1];
                $key = explode('--', $key)[0];
            }

            $value = explode('=', $line)[1];

            $foundData[] = new ImportEntry(
                $localeID,
                $key,
                $group,
                $value
            );
        }

        return new ImportResult($foundData);
    }

}