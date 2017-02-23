<?php

class HookPreReceive extends HookReceive
{
    public $actions = [
        'Action_CheckMasterPush',
        'Action_CheckPhpSyntax',
    ];
    public function run()
    {
        if (self::ZERO != $this->new_revision) {
            foreach($this->actions as $action) {
                $a = new $action($this);
                $a->run();
                $notices = $a->getNotice();
                if (!empty($notices)) {
                    echo "NOTICE: " . $notices . "\n";
                }
            }
        } else {
            $this->error("You can't delete branches!");
        }
    }
}
