<?php

class Action_NotifyGitwatchers extends Action_ActionAbstract
{
    protected $email_tags = [];
    protected $notify_email = 'email@domain.com';

    public function run()
    {
        $diff = $this->Hook->getDiff();

        $subject = $this->Hook->getRepoName() . ': [' . $this->Hook->getRefName() . '] '
            . (empty($this->email_tags) ? '' : '[' . implode('] [', $this->email_tags) . ']')
            . ' author: ' . $this->Hook->getUser();

        $tracker_url = '';
        if (preg_match_all($this->Hook::ISSUE_REGEX, $this->Hook->getRefName(), $m)) {
            $tracker_url = "Issue: <a href='" . RedmineRestClient::URL . '/issues/' . $m[1] . "'></a>\n\n";
        }

        $message = $tracker_url . "Files modified: \n"
            . $diff['files'] . "\n\n--------------------------------------\n\n"
            . $diff['log'] . "\n\n--------------------------------------\n\n"
            . $diff['diff'];

        $message = $this->_decorateDiff($message);

        $this->Hook->sendEmail($subject, $message, $this->notify_email, $this->Hook::RELEASE_EMAIL, 'text/html');
    }

    protected function _decorateDiff($message)
    {
        $message = htmlspecialchars($message);
        $message = nl2br($message);
        $message = str_replace(array('  ', "\t"), array('&nbsp;&nbsp;', '&nbsp;&nbsp;&nbsp;&nbsp;'), $message);
        $message = preg_replace(
            '/[0-9a-g]{40}/i',
            '<a href="' . $this->Hook::GITPHP_URL . '?p=' . $this->Hook->getRepoName() . '&a=commitdiff&h=$0" target="_blank">$0</a>',
            $message
        );

        return $message;
    }
}
