<?php

abstract class Action_ActionAbstract
{
    protected $Hook;
    protected $_notice;

    public function __construct($hook)
    {
        $this->Hook = $hook;
    }

    public function addNotice($new_notice)
    {
        if (!empty($this->_notice)) {
            $this->_notice .= PHP_EOL . $new_notice;
        } else {
            $this->_notice = $new_notice;
        }
    }

    public function getNotice()
    {
        return $this->_notice;
    }

}
