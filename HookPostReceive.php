<?php

class HookPostReceive extends HookReceive
{
    public $actions = [
        'Action_NotifyGitwatchers',
        'Action_FillCommitsField',
    ];
    public function run()
    {
        foreach($this->actions as $action) {
            $a = new $action($this);
            $a->run();
            $notices = $a->getNotice();
            if (!empty($notices)) {
                echo "NOTICE: " . $notices . "\n";
            }
        }
    }
}
