<?php

class Action_CheckPhpSyntax extends Action_ActionAbstract
{
    protected $excludes = [
        '.phpstorm.meta.php',
        '_ide_helper.php',
        '_ide_helper_models.php',
    ];

    public function run()
    {
        $errors = [];

        foreach ($this->Hook->getDiffFiles(['php', 'phtml', 'inc']) as $file) {
            if (!in_array($file, $this->excludes)) {
                $fullpath = $this->Hook::TEMP . $file;
                @mkdir(dirname($fullpath), 0777, true);
                $this->Hook->raw_exec(
                    'BLOB=`git ls-tree ' . $this->Hook->getNewRevision()
                        . ' ' . $file . ' | awk \'{print $3}\'`; test -z $BLOB || git cat-file blob $BLOB > '
                        . $fullpath . ';'
                );
                $file_valid = false;
                if (file_exists($fullpath)) {
                    $file_valid = $this->validatePhp($fullpath);
                }
                if (!$file_valid) {
                    $errors[] = $file;
                }
            }
        }
        if (!empty($errors)) {
            $this->Hook->error("Php syntax errors:\n" . implode("\n", $errors));
        }
    }

    private function validatePhp($fullpath)
    {
        $out = '';
        $retval = 0;
        $this->Hook->raw_exec('/usr/bin/php -l ' . $fullpath, $out, $retval, false);
        return 0 == $retval;
    }
}
