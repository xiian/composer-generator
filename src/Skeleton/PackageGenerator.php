<?php

namespace xiian\ComposerGenerator\Skeleton;

class PackageGenerator extends \Pds\Skeleton\PackageGenerator
{
    public function createFileList($validatorResults)
    {
        $files = parent::createFileList($validatorResults);

        // Get rid of unwanted files/folders
        foreach (['config/', 'public/', 'resources/', 'CONTRIBUTING', 'LICENSE'] as $k) {
            unset($files[$k]);
        }
        return $files;
    }

}
