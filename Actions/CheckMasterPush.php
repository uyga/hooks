<?php

class Action_CheckMasterPush extends Action_ActionAbstract
{
    protected $allowed_to_push = ['username1', 'username2', 'username3'];
    public function run()
    {
        if ($this->Hook->masterMerge() && !in_array($this->Hook->getUser(), $this->allowed_to_push)) {
            $this->Hook->error("You are not allowed to push into master branch!");
        }
    }
}
