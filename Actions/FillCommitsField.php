<?php

class Action_FillCommitsField extends Action_ActionAbstract
{
    public function run()
    {
        if (!$this->Hook->masterMerge()
            && !$this->Hook->stagingMerge()
            && $this->Hook->firstPush()
            && preg_match_all($this->Hook::ISSUE_REGEX, $this->Hook->getRefName(), $m)
        ) {
            foreach ($m[1] as $issue) {
                try {
                    //Jira
                    /*$Issue = JiraRestClient::getInstance()->getIssue($issue);
                    if ($Issue) {
                        JiraRestClient::getInstance()->setFieldValue(
                            $issue,
                            'Branch',
                            '{noformat:borderColor=#ffffff|bgColor=#ffffff}' . $this->Hook->getRefName()
                                . '{noformat} [branchlog|' . $this->Hook::GITPHP_URL . '?p='
                                . $this->Hook->getRepoName() . '&a=branchlog&h=' . $this->Hook->getReference()
                                . '] | [branchdiff|' . $this->Hook::GITPHP_URL . '?p=' . $this->Hook->getRepoName()
                                . '&a=branchdiff&branch=' . $this->Hook->getRefName() . ']'
                        );
                    }*/
                    //redmine
                    $Issue = RedmineRestClient::getInstance()->getIssue($issue);
                    if ($Issue) {
                        RedmineRestClient::getInstance()->setCustomFieldValue(
                            $issue,
                            ['review' => $this->Hook->getRefName()
                                . ' "branchlog":' . $this->Hook::GITPHP_URL . '?p='
                                . $this->Hook->getRepoName() . '&a=branchlog&h=' . $this->Hook->getReference()
                                . ' | "branchdiff":' . $this->Hook::GITPHP_URL . '?p=' . $this->Hook->getRepoName()
                                . '&a=branchdiff&branch=' . $this->Hook->getRefName()
                            ]
                        );
                    }
                } catch (Exception $e) {
                    $this->Hook->error($e->getMessage() . " " . $issue);
                }
            }
        }
    }
}
